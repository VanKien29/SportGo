<?php

namespace App\Services\Partner;

use App\Mail\Partner\PartnerDocumentOtpMail;
use App\Models\DocumentSigningRequest;
use App\Models\GeneratedDocument;
use App\Models\GeneratedDocumentSignature;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartnerDocumentSigningService
{
    public const OTP_MINUTES = 5;

    public function __construct(private readonly OtpService $otpService)
    {
    }

    public function requestOtp(
        GeneratedDocument $document,
        User $signer,
        string $signerSide,
        string $action,
        string $checkboxText,
        ?string $signatureImage,
        Request $request
    ): DocumentSigningRequest {
        $document = $document->fresh();
        $fileHash = $this->currentFileHash($document);
        $identifier = $signer->email;

        if (! $identifier) {
            throw ValidationException::withMessages([
                'email' => 'Tài khoản chưa có email để nhận OTP ký văn bản.',
            ]);
        }

        $this->cancelOpenRequests($document, $signer, $signerSide);

        $nonce = (string) Str::uuid();
        $otpType = 'partner_document_signature:' . $nonce;
        $otp = $this->otpService->generate();
        $verification = $this->otpService->create($signer, $identifier, $otpType, $otp, self::OTP_MINUTES);

        $signingRequest = DocumentSigningRequest::query()->create([
            'generated_document_id' => $document->id,
            'verification_code_id' => $verification->id,
            'user_id' => $signer->id,
            'signer_side' => $signerSide,
            'action' => $action,
            'document_type' => $document->document_type,
            'document_code' => $document->document_code,
            'document_version' => $document->document_version ?: 1,
            'file_hash' => $fileHash,
            'nonce' => $nonce,
            'otp_type' => $otpType,
            'otp_channel' => 'email',
            'otp_identifier' => $identifier,
            'otp_sent_at' => now(),
            'expires_at' => now()->addMinutes(self::OTP_MINUTES),
            'status' => 'otp_sent',
            'checkbox_text' => $checkboxText,
            'signature_image' => $signatureImage,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'metadata' => [
                'document_title' => $document->title,
                'viewed_at' => now()->toISOString(),
                'hash_short' => substr($fileHash, 0, 16),
            ],
        ]);

        try {
            Mail::to($identifier)->queue(new PartnerDocumentOtpMail($signingRequest->load('document'), $otp, self::OTP_MINUTES));
        } catch (\Throwable $exception) {
            Log::error('Failed to send partner document signature OTP.', [
                'signing_request_id' => $signingRequest->id,
                'document_id' => $document->id,
                'user_id' => $signer->id,
                'error' => $exception->getMessage(),
            ]);

            $signingRequest->forceFill(['status' => 'failed'])->save();

            throw ValidationException::withMessages([
                'otp' => 'Không gửi được OTP ký văn bản. Vui lòng thử lại sau.',
            ]);
        }

        return $signingRequest->fresh(['document']);
    }

    public function verifyOtp(DocumentSigningRequest $signingRequest, User $signer, string $otp): DocumentSigningRequest
    {
        $signingRequest->loadMissing(['document', 'verificationCode']);

        if ($signingRequest->user_id !== $signer->id) {
            abort(403, 'Bạn không có quyền xác thực yêu cầu ký này.');
        }

        if ($signingRequest->status !== 'otp_sent') {
            throw ValidationException::withMessages([
                'otp' => 'Yêu cầu ký này không còn hiệu lực. Vui lòng tạo OTP mới.',
            ]);
        }

        if ($signingRequest->expires_at->isPast()) {
            $signingRequest->forceFill(['status' => 'expired'])->save();
            throw ValidationException::withMessages([
                'otp' => 'Mã OTP đã hết hạn. Vui lòng tạo OTP mới.',
            ]);
        }

        $currentHash = $this->currentFileHash($signingRequest->document);
        if (! hash_equals($signingRequest->file_hash, $currentHash)) {
            $this->cancelBecauseFileChanged($signingRequest);
            throw ValidationException::withMessages([
                'otp' => 'Nội dung văn bản đã thay đổi sau khi gửi OTP. Vui lòng xem lại văn bản và tạo OTP mới.',
            ]);
        }

        $code = $signingRequest->verificationCode;
        if (! $code || $code->is_used) {
            throw ValidationException::withMessages([
                'otp' => 'Mã OTP đã được sử dụng hoặc không còn hiệu lực.',
            ]);
        }

        if ($code->expires_at->isPast()) {
            $signingRequest->forceFill(['status' => 'expired'])->save();
            throw ValidationException::withMessages([
                'otp' => 'Mã OTP đã hết hạn. Vui lòng tạo OTP mới.',
            ]);
        }

        if ($code->attempt_count >= $code->max_attempts) {
            $signingRequest->forceFill(['status' => 'failed'])->save();
            throw ValidationException::withMessages([
                'otp' => 'Mã OTP đã vượt quá số lần thử. Vui lòng tạo OTP mới.',
            ]);
        }

        if (! Hash::check($otp, $code->code)) {
            $code->increment('attempt_count');
            if (($code->attempt_count + 1) >= $code->max_attempts) {
                $signingRequest->forceFill(['status' => 'failed'])->save();
            }

            throw ValidationException::withMessages([
                'otp' => 'Mã OTP không đúng.',
            ]);
        }

        $code->forceFill(['is_used' => true])->save();
        $signingRequest->forceFill([
            'status' => 'verified',
            'otp_verified_at' => now(),
        ])->save();

        return $signingRequest->fresh(['document']);
    }

    public function markSigned(DocumentSigningRequest $signingRequest, GeneratedDocumentSignature $signature): DocumentSigningRequest
    {
        $document = $signature->document ?: $signingRequest->document;
        $signingRequest->forceFill([
            'status' => 'signed',
            'signed_signature_id' => $signature->id,
            'file_hash_after' => $this->currentFileHash($document->fresh()),
        ])->save();

        return $signingRequest->fresh(['document', 'signature']);
    }

    public function currentFileHash(GeneratedDocument $document): string
    {
        $path = $document->final_file_path ?: $document->generated_file_path;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages([
                'document' => 'Không tìm thấy file văn bản để ký.',
            ]);
        }

        $hash = hash_file('sha256', Storage::disk('local')->path($path));
        if ($document->file_hash !== $hash) {
            $document->forceFill(['file_hash' => $hash])->save();
        }

        return $hash;
    }

    private function cancelOpenRequests(GeneratedDocument $document, User $signer, string $signerSide): void
    {
        DocumentSigningRequest::query()
            ->with('verificationCode')
            ->where('generated_document_id', $document->id)
            ->where('user_id', $signer->id)
            ->where('signer_side', $signerSide)
            ->whereIn('status', ['otp_sent', 'verified'])
            ->get()
            ->each(function (DocumentSigningRequest $request): void {
                $request->verificationCode?->forceFill(['is_used' => true])->save();
                $request->forceFill(['status' => 'cancelled'])->save();
            });
    }

    private function cancelBecauseFileChanged(DocumentSigningRequest $signingRequest): void
    {
        $signingRequest->verificationCode?->forceFill(['is_used' => true])->save();
        $signingRequest->forceFill(['status' => 'cancelled'])->save();
    }
}
