<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'reporter_id',
        'reportable_type',
        'reportable_id',
        'violation_type_id',
        'severity_level',
        'score_contribution',
        'auto_action_taken',
        'auto_actioned_at',
        'reason',
        'description',
        'status',
        'action_taken',
        'action_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'auto_actioned_at' => 'datetime',
            'score_contribution' => 'integer',
        ];
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportable()
    {
        return $this->morphTo();
    }

    public function evidence()
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function violationType()
    {
        return $this->belongsTo(ViolationType::class, 'violation_type_id');
    }

    /**
     * Tự động xác nhận (resolve) các báo cáo đang chờ xử lý liên quan đến đối tượng.
     */
    public static function resolvePendingReportsForTarget(Model $target, string $actionTaken, User $admin, ?string $customReason = null): void
    {
        $reports = self::query()
            ->where('reportable_type', get_class($target))
            ->where('reportable_id', $target->getKey())
            ->whereIn('status', ['pending', 'reviewing'])
            ->get();

        if ($reports->isEmpty()) {
            return;
        }

        $defaultNote = 'Cảm ơn bạn đã gửi báo cáo. Chúng tôi đã tiếp nhận và ghi nhận nội dung phản ánh của bạn. Hệ thống sẽ tiếp tục xử lý theo quy định của SportGo.';
        $note = !empty($customReason) ? $customReason : $defaultNote;

        // Tìm chủ sở hữu của đối tượng
        $targetOwner = null;
        if ($target instanceof User) {
            $targetOwner = $target;
        } elseif (method_exists($target, 'author')) {
            $targetOwner = $target->author;
        } elseif (isset($target->user_id)) {
            $targetOwner = User::query()->find($target->user_id);
        } elseif ($target instanceof VenueCluster) {
            $targetOwner = $target->owner;
        }

        $violationScoreService = app(\App\Services\Moderation\ViolationScoreService::class);
        $targetType = $violationScoreService->normalizeTargetType(get_class($target));
        $isVenueRelated = in_array($targetType, ['venue_cluster', 'venue_post'], true);
        if (!$isVenueRelated && $targetType === 'user' && $targetOwner) {
            $isVenueRelated = $targetOwner->roles()->whereIn('roles.name', ['owner', 'venue_owner'])->exists();
        }

        foreach ($reports as $report) {
            $report->forceFill([
                'status' => 'resolved',
                'action_taken' => $actionTaken,
                'action_note' => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ])->save();

            // Gửi thông báo in-app
            if (\Schema::hasTable('notifications')) {
                $recipients = collect([$report->reporter, $targetOwner])->filter()->unique('id');
                foreach ($recipients as $user) {
                    \App\Models\Notification::query()->create([
                        'user_id' => $user->id,
                        'type' => 'report_processed',
                        'title' => 'Báo cáo đã được xử lý',
                        'body' => $note,
                        'reference_type' => self::class,
                        'reference_id' => $report->id,
                        'data' => ['status' => 'resolved', 'action_taken' => $actionTaken],
                        'is_read' => false,
                    ]);
                }
            }

            // Gửi email cho chủ sân nếu là báo cáo liên quan đến sân/cụm sân/chủ sân
            if ($isVenueRelated && $targetOwner && $targetOwner->email) {
                try {
                    \Illuminate\Support\Facades\Mail::to($targetOwner->email)->send(
                        new \App\Mail\VenueComplaintMail($report->description ?: $report->reason)
                    );
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
    }
}

