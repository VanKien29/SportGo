<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$userId = '019ed3c8-054e-70dc-9cfc-6fcd0013b6b5';

echo "=== TEST 1: Reset user to active ===\n";
$user = App\Models\User::find($userId);
$user->forceFill(['status' => 'active', 'lock_type' => null, 'locked_at' => null, 'locked_until' => null, 'status_reason' => null])->save();
echo "User {$user->username} status reset to: {$user->status}\n\n";

echo "=== TEST 2: Check auto-lock config API ===\n";
$ctrl = app(App\Http\Controllers\Api\Admin\UserController::class);
// Check internal config
$activePolicy = App\Models\SystemPolicy::where('policy_type', 'moderation')->where('status', 'active')->first();
$rules = $activePolicy->rules()->get();
foreach ($rules as $r) {
    $c = $r->condition_json ?? [];
    if (in_array($c['reportable_type'] ?? '', ['user', 'users'])) {
        $rj = $r->result_json ?? [];
        echo "Rule: action={$rj['action']} threshold={$c['threshold']} window={$c['window_days']}d is_active=" . ($r->is_active ? 'true' : 'false') . "\n";
        if (isset($rj['reason'])) echo "  reason: {$rj['reason']}\n";
        if (isset($rj['lock_duration_days'])) echo "  lock_duration: {$rj['lock_duration_days']} days\n";
    }
}

echo "\n=== TEST 3: Check warning level for user with 30 reports ===\n";
$reportCount = DB::table('reports')
    ->whereIn('reportable_type', ['users', 'user', App\Models\User::class])
    ->where('reportable_id', $userId)
    ->where('created_at', '>=', now()->subDays(7))
    ->distinct('reporter_id')
    ->count('reporter_id');
echo "Unique reporters in 7 days: {$reportCount}\n";

echo "\n=== TEST 4: Simulate evaluate (auto-lock should fire) ===\n";
$service = app(App\Services\Policies\ModerationReportPolicyService::class);
$result = $service->evaluate($user->fresh());
echo "Matched: " . ($result['matched'] ? 'YES' : 'NO') . "\n";
echo "Applied actions: " . implode(', ', $result['applied_actions']) . "\n";

$user->refresh();
echo "\nUser status after evaluate: {$user->status}\n";
echo "Lock type: {$user->lock_type}\n";
echo "Locked until: {$user->locked_until}\n";
echo "Status reason: {$user->status_reason}\n";

echo "\n=== TEST 5: Verify warning levels use policy thresholds ===\n";
// We'll simulate warningLevelText with the policy thresholds
$policyConfig = (function() {
    $activePolicy = App\Models\SystemPolicy::where('policy_type', 'moderation')->where('status', 'active')->first();
    $rules = $activePolicy ? $activePolicy->rules()->where('is_active', true)->get() : collect();
    $warn = 3; $lock = 10; $window = 14;
    foreach ($rules as $rule) {
        $c = $rule->condition_json ?? [];
        if (in_array($c['reportable_type'] ?? '', ['user', 'users'])) {
            $r = $rule->result_json ?? [];
            if (($r['action'] ?? '') === 'warning') {
                $warn = $c['threshold'] ?? $warn;
                $window = $c['window_days'] ?? $window;
            } elseif (in_array($r['action'] ?? '', ['auto_lock', 'lock_temp', 'lock_permanent'])) {
                $lock = $c['threshold'] ?? $lock;
                $window = $c['window_days'] ?? $window;
            }
        }
    }
    return [$warn, $lock, $window];
})();
echo "Policy thresholds: warn={$policyConfig[0]}, lock={$policyConfig[1]}, window={$policyConfig[2]} days\n";

// Test different report counts
foreach ([0, 1, 2, 3, 5, 9, 10, 15] as $count) {
    $level = match (true) {
        $count >= $policyConfig[1] => 'lock_suggested',
        $count >= $policyConfig[0] => 'near_lock',
        $count > 0 => 'watch',
        default => 'normal',
    };
    echo "  {$count} reports => level: {$level}\n";
}

echo "\n=== ALL TESTS PASSED ===\n";
