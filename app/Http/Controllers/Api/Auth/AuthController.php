<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AuthOtpMail;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Auth\RoleRedirectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    private const ADMIN_ROLES = ['super_admin', 'admin', 'system_staff'];

    public function __construct(
        private readonly OtpService $otpService,
        private readonly RoleRedirectService $roleRedirectService,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->prepareRegisterAccount($data);

        $user = User::query()->create([
            'username' => $data['username'],
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 'pending_verify',
            'verification_channel' => 'email',
        ]);

        $this->roleRedirectService->assignDefaultUserRole($user);
        $this->sendRegisterOtp($user);

        return response()->json([
            'message' => 'Đăng ký thành công. Vui lòng kiểm tra email để lấy mã xác thực.',
            'email' => $user->email,
            'verification_url' => $this->registrationVerificationUrl($user->email),
        ], 201);
    }

    public function resendRegisterOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Tài khoản không tồn tại.']);
        }

        if ($user->status !== 'pending_verify') {
            throw ValidationException::withMessages(['email' => 'Tài khoản này đã được xác thực hoặc không hợp lệ.']);
        }

        $this->sendRegisterOtp($user);

        return response()->json([
            'message' => 'Đã gửi lại mã xác thực. Vui lòng kiểm tra email của bạn.',
            'email' => $user->email,
            'verification_url' => $this->registrationVerificationUrl($user->email),
        ]);
    }

    public function verifyRegisterOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ]);

        $email = strtolower(trim($data['email']));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user || $user->status !== 'pending_verify' || strcasecmp((string) $user->email, $email) !== 0) {
            throw ValidationException::withMessages([
                'email' => 'Tài khoản không còn ở trạng thái chờ xác thực. Vui lòng yêu cầu mã mới nếu cần.',
            ]);
        }

        DB::transaction(function () use ($email, $data, $user): void {
            $this->otpService->verify($email, 'register', $data['otp'], true, (int) $user->id);
            $user->forceFill([
                'status' => 'active',
                'email_verified_at' => now(),
            ])->save();
        });

        return response()->json([
            'message' => 'Xác thực tài khoản thành công. Vui lòng đăng nhập.',
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => 'Vui lòng nhập tài khoản.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $user = $this->findUserByIdentifier($data['login']);

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => 'Sai tài khoản hoặc mật khẩu.']);
        }

        if ($this->isAdminUser($user)) {
            throw ValidationException::withMessages([
                'login' => 'Sai tài khoản hoặc mật khẩu.',
            ]);
        }

        if ($user->status === 'pending_verify') {
            return response()->json([
                'message' => 'Tài khoản chưa xác thực email. Vui lòng xác thực để tiếp tục.',
                'verification_email' => $user->email,
                'verification_url' => $this->registrationVerificationUrl($user->email),
            ], 422);
        }

        if ($user->status === 'locked') {
            return $this->lockedUserResponse($user);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['login' => 'Tài khoản không ở trạng thái hoạt động.']);
        }

        $token = $user->createToken('sportgo-api')->plainTextToken;

        return response()->json(array_merge([
            'message' => 'Đăng nhập thành công',
        ], $this->roleRedirectService->payload($user, $token)));
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->status === 'locked') {
            $user->currentAccessToken()?->delete();

            return $this->lockedUserResponse($user);
        }

        $payload = array_merge([
            'message' => 'Lấy thông tin tài khoản thành công.',
        ], $this->roleRedirectService->payload($user));

        $includes = collect(explode(',', (string) $request->query('include', '')))
            ->map(fn (string $include): string => trim($include))
            ->filter();

        if (($payload['role_group'] ?? null) === 'user' && $includes->contains('refund_finance')) {
            $payload['refund_finance'] = $this->refundFinanceSummary($user);
        }

        return response()->json($payload);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^(0\d{9}|\+84\d{9})$/',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'bio' => ['nullable', 'string', 'max:2000'],
            'preferred_sports' => ['nullable', 'array', 'max:5'],
            'preferred_sports.*' => ['nullable', 'string', 'max:80'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'full_name.required' => 'Vui lòng nhập họ và tên.',
            'full_name.min' => 'Họ và tên cần có ít nhất 2 ký tự.',
            'email.email' => 'Địa chỉ email không đúng định dạng.',
            'email.unique' => 'Email đã được sử dụng.',
            'phone.regex' => 'Số điện thoại không đúng định dạng.',
            'phone.unique' => 'Số điện thoại đã được sử dụng bởi tài khoản khác.',
            'avatar.image' => 'Avatar phải là một tệp hình ảnh.',
            'avatar.max' => 'Avatar không được vượt quá 2MB.',
        ]);

        $user->full_name = trim($data['full_name']);
        if (array_key_exists('email', $data) && $data['email'] !== null) {
            $nextEmail = trim($data['email']);
            if (strcasecmp($nextEmail, (string) $user->email) !== 0) {
                throw ValidationException::withMessages([
                    'email' => 'Vui lòng xác thực email mới bằng mã OTP trước khi lưu thay đổi.',
                ]);
            }
        }
        $user->phone = $data['phone'] ? trim($data['phone']) : null;
        $user->bio = array_key_exists('bio', $data) ? trim((string) ($data['bio'] ?? '')) : $user->bio;
        if (array_key_exists('preferred_sports', $data)) {
            $user->preferred_sports = array_values(array_unique(array_filter(array_map(
                static fn ($sport) => trim((string) $sport),
                $data['preferred_sports'] ?: [],
            ))));
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_url && str_starts_with($user->avatar_url, '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar_url));
            }

            $user->avatar_url = Storage::disk('public')->url($request->file('avatar')->store('avatars', 'public'));
        }

        $user->save();

        return response()->json([
            'message' => 'Đã cập nhật thông tin cá nhân.',
            'user' => $user->only([
                'id', 'username', 'full_name', 'email', 'phone', 'status',
                'avatar_url', 'email_verified_at', 'bio', 'preferred_sports',
            ]),
        ]);
    }

    public function requestEmailChangeOtp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'email.required' => 'Vui lòng nhập email mới.',
            'email.email' => 'Địa chỉ email không đúng định dạng.',
            'email.unique' => 'Email đã được sử dụng.',
        ]);

        $email = strtolower(trim($data['email']));
        if (strcasecmp($email, (string) $user->email) === 0) {
            return response()->json(['message' => 'Email mới trùng với email hiện tại.']);
        }

        $otp = $this->otpService->generate();
        $this->otpService->create($user, $email, 'change_email', $otp);
        Mail::to($email)->send(new AuthOtpMail($user, $otp, 'change_email', OtpService::EXPIRE_MINUTES));

        return response()->json([
            'message' => 'Đã gửi mã OTP đến email mới. Mã có hiệu lực trong '.OtpService::EXPIRE_MINUTES.' phút.',
            'email' => $email,
        ]);
    }

    public function verifyEmailChangeOtp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'otp' => ['required', 'digits:6'],
        ], [
            'email.required' => 'Vui lòng nhập email cần xác thực.',
            'otp.required' => 'Vui lòng nhập mã OTP.',
            'otp.digits' => 'Mã OTP phải gồm đúng 6 chữ số.',
        ]);

        $email = strtolower(trim($data['email']));
        $code = $this->otpService->verify($email, 'change_email', $data['otp'], true, (int) $user->id);
        if ((int) $code->user_id !== (int) $user->id) {
            throw ValidationException::withMessages(['otp' => 'Mã OTP không thuộc yêu cầu của tài khoản này.']);
        }

        if (User::query()
            ->where('email', $email)
            ->where('id', '<>', $user->id)
            ->exists()) {
            throw ValidationException::withMessages(['email' => 'Email đã được sử dụng.']);
        }

        $user->forceFill([
            'email' => $email,
            'email_verified_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Email mới đã được xác thực.',
            'user' => $user->only([
                'id', 'username', 'full_name', 'email', 'phone', 'status',
                'avatar_url', 'email_verified_at', 'bio', 'preferred_sports',
            ]),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công.',
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu mới không trùng khớp.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Mật khẩu hiện tại không đúng.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        return response()->json([
            'message' => 'Đổi mật khẩu thành công. Vui lòng sử dụng mật khẩu mới cho các lần đăng nhập sau.',
        ]);
    }

    private function prepareRegisterAccount(array $data): void
    {
        $conflictingUsers = User::query()
            ->where('username', $data['username'])
            ->orWhere('phone', $data['phone'])
            ->orWhere('email', $data['email'])
            ->get();

        $activeConflicts = $conflictingUsers->where('status', '!=', 'pending_verify');

        if ($activeConflicts->isNotEmpty()) {
            $errors = [];

            if ($activeConflicts->contains('username', $data['username'])) {
                $errors['username'] = 'Tên tài khoản đã tồn tại.';
            }

            if ($activeConflicts->contains('phone', $data['phone'])) {
                $errors['phone'] = 'Số điện thoại đã được sử dụng.';
            }

            if ($activeConflicts->contains('email', $data['email'])) {
                $errors['email'] = 'Email đã được sử dụng.';
            }

            throw ValidationException::withMessages($errors);
        }

        $conflictingUsers
            ->where('status', 'pending_verify')
            ->each(fn (User $pendingUser) => $pendingUser->delete());
    }

    private function sendRegisterOtp(User $user): void
    {
        $otp = $this->otpService->generate();
        $this->otpService->create($user, $user->email, 'register', $otp);
        Mail::to($user->email)->send(new AuthOtpMail($user, $otp, 'register', OtpService::EXPIRE_MINUTES));
    }

    private function registrationVerificationUrl(string $email): string
    {
        return url('/verify-email?email='.rawurlencode(strtolower(trim($email))));
    }

    private function lockedUserResponse(User $user): JsonResponse
    {
        return response()->json([
            'message' => 'Tài khoản của bạn đang bị khóa.',
            'status_reason' => $user->status_reason,
            'lock_type' => $user->lock_type,
            'locked_until' => $user->locked_until,
        ], 423);
    }

    private function findUserByIdentifier(string $identifier): ?User
    {
        return User::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->orWhere('phone', $identifier)
            ->first();
    }

    private function isAdminUser(User $user): bool
    {
        $roles = $this->roleRedirectService->roles($user);

        return (bool) array_intersect($roles, self::ADMIN_ROLES);
    }

    private function refundFinanceSummary(User $user): array
    {
        $emptySummary = [
            'available_balance' => 0.0,
            'locked_balance' => 0.0,
            'total_balance' => 0.0,
            'status' => 'none',
            'transactions' => [],
            'withdrawals' => [],
        ];

        if (! Schema::hasTable('user_wallets')) {
            return $emptySummary;
        }

        $wallet = DB::table('user_wallets')
            ->where('user_id', $user->id)
            ->first();

        if (! $wallet) {
            return $emptySummary;
        }

        $transactions = Schema::hasTable('user_wallet_ledgers')
            ? DB::table('user_wallet_ledgers')
                ->where('user_wallet_id', $wallet->id)
                ->latest('created_at')
                ->limit(50)
                ->get()
                ->map(fn (object $ledger): array => [
                    'id' => $ledger->id,
                    'transaction_code' => $ledger->transaction_code ?? null,
                    'type' => $ledger->type ?? null,
                    'type_label' => $this->refundTransactionTypeLabel($ledger->type ?? null),
                    'direction' => $ledger->direction ?? null,
                    'amount' => (float) ($ledger->amount ?? 0),
                    'balance_after' => (float) ($ledger->balance_after ?? 0),
                    'status' => $ledger->status ?? null,
                    'status_label' => $this->refundTransactionStatusLabel($ledger->status ?? null),
                    'created_at' => $this->isoDateTime($ledger->created_at ?? null),
                ])
                ->values()
                ->all()
            : [];

        $withdrawalQuery = Schema::hasTable('user_withdrawal_requests')
            ? DB::table('user_withdrawal_requests')
                ->where('user_withdrawal_requests.user_id', $user->id)
                ->latest('user_withdrawal_requests.requested_at')
                ->limit(20)
            : null;

        if ($withdrawalQuery && Schema::hasTable('user_payout_accounts')) {
            $withdrawalQuery->leftJoin('user_payout_accounts', function ($join) use ($user): void {
                $join->on('user_payout_accounts.id', '=', 'user_withdrawal_requests.payout_account_id')
                    ->where('user_payout_accounts.user_id', '=', $user->id);
            });
        }

        $withdrawalColumns = [
                    'user_withdrawal_requests.id',
                    'user_withdrawal_requests.amount',
                    'user_withdrawal_requests.status',
                    'user_withdrawal_requests.rejected_reason',
                    'user_withdrawal_requests.requested_at',
                    'user_withdrawal_requests.paid_at',
        ];

        if ($withdrawalQuery && Schema::hasTable('user_payout_accounts')) {
            $withdrawalColumns[] = 'user_payout_accounts.bank_name';
            $withdrawalColumns[] = 'user_payout_accounts.bank_account_number';
        }

        $withdrawals = $withdrawalQuery
            ? $withdrawalQuery
                ->get($withdrawalColumns)
                ->map(fn (object $withdrawal): array => [
                    'id' => $withdrawal->id,
                    'amount' => (float) ($withdrawal->amount ?? 0),
                    'status' => $withdrawal->status ?? null,
                    'status_label' => $this->withdrawalStatusLabel($withdrawal->status ?? null),
                    'rejected_reason' => $withdrawal->rejected_reason ?? null,
                    'requested_at' => $this->isoDateTime($withdrawal->requested_at ?? null),
                    'paid_at' => $this->isoDateTime($withdrawal->paid_at ?? null),
                    'bank_name' => $withdrawal->bank_name ?? null,
                    'bank_account_masked' => $this->maskBankAccount($withdrawal->bank_account_number ?? null),
                ])
                ->values()
                ->all()
            : [];

        $availableBalance = (float) ($wallet->balance ?? 0);
        $lockedBalance = (float) ($wallet->locked_balance ?? 0);

        return [
            'available_balance' => $availableBalance,
            'locked_balance' => $lockedBalance,
            'total_balance' => $availableBalance + $lockedBalance,
            'status' => $wallet->status ?? 'none',
            'transactions' => $transactions,
            'withdrawals' => $withdrawals,
        ];
    }

    private function refundTransactionTypeLabel(?string $type): string
    {
        return match ($type) {
            'deposit' => 'Bổ sung số dư',
            'payment' => 'Thanh toán booking',
            'refund' => 'Hoàn tiền booking',
            'withdrawal' => 'Chi trả về ngân hàng',
            'adjustment' => 'Điều chỉnh số dư',
            default => 'Biến động số dư',
        };
    }

    private function refundTransactionStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Đang xử lý',
            'completed' => 'Hoàn tất',
            'failed' => 'Thất bại',
            'cancelled' => 'Đã hủy',
            default => 'Chưa xác định',
        };
    }

    private function withdrawalStatusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Chờ duyệt',
            'approved' => 'Đã duyệt',
            'rejected' => 'Bị từ chối',
            'paid' => 'Đã chi trả',
            'cancelled' => 'Đã hủy',
            default => 'Chưa xác định',
        };
    }

    private function maskBankAccount(?string $accountNumber): ?string
    {
        if (! $accountNumber) {
            return null;
        }

        $visibleDigits = substr($accountNumber, -4);

        return str_repeat('•', max(0, strlen($accountNumber) - 4)).$visibleDigits;
    }

    private function isoDateTime(mixed $value): ?string
    {
        return $value ? Carbon::parse($value, config('app.timezone'))->toIso8601String() : null;
    }
}
