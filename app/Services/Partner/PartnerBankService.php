<?php

namespace App\Services\Partner;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PartnerBankService
{
    public function banks(): array
    {
        return Cache::remember('partner_vietqr_banks', now()->addDay(), function (): array {
            try {
                $response = Http::timeout(10)->get(config('services.vietqr.banks_url'));
                $payload = $response->json();
                $banks = $payload['data'] ?? [];

                if (! is_array($banks)) {
                    return $this->fallbackBanks();
                }

                return collect($banks)
                    ->filter(fn ($bank) => (int) ($bank['transferSupported'] ?? $bank['isTransfer'] ?? 0) === 1)
                    ->map(fn ($bank) => $this->normalizeBank($bank))
                    ->values()
                    ->all();
            } catch (\Throwable) {
                return $this->fallbackBanks();
            }
        });
    }

    public function findBank(?string $bankCode = null, ?string $bin = null): ?array
    {
        return collect($this->banks())->first(function (array $bank) use ($bankCode, $bin): bool {
            return ($bankCode && Str::upper($bank['code']) === Str::upper($bankCode))
                || ($bin && (string) $bank['bin'] === (string) $bin);
        });
    }

    public function verifyAccount(string $bankCode, string $accountNumber, string $accountHolderName, ?string $bin = null): array
    {
        $bank = $this->findBank($bankCode, $bin);

        if (! $bank) {
            return [
                'status' => 'invalid_bank',
                'verified' => false,
                'message' => 'Ngân hàng không nằm trong danh sách đang hỗ trợ.',
            ];
        }

        if (! preg_match('/^\d{6,19}$/', $accountNumber)) {
            return [
                'status' => 'invalid_account_number',
                'verified' => false,
                'bank' => $bank,
                'message' => 'Số tài khoản chỉ gồm 6-19 chữ số.',
            ];
        }

        $clientId = config('services.vietqr.client_id');
        $apiKey = config('services.vietqr.api_key');

        if (! $clientId || ! $apiKey) {
            return [
                'status' => 'manual_required',
                'verified' => false,
                'bank' => $bank,
                'message' => 'Tài khoản ngân hàng sẽ được admin xác minh thủ công trước khi duyệt hồ sơ.',
            ];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'x-client-id' => $clientId,
                    'x-api-key' => $apiKey,
                ])
                ->post(config('services.vietqr.lookup_url'), [
                    'bin' => (int) $bank['bin'],
                    'accountNumber' => $accountNumber,
                ]);

            $payload = $response->json();
            $providerName = trim((string) data_get($payload, 'data.accountName'));

            if (($payload['code'] ?? null) !== '00' || $providerName === '') {
                return [
                    'status' => 'not_found',
                    'verified' => false,
                    'bank' => $bank,
                    'message' => $payload['desc'] ?? 'Không tìm thấy tài khoản tại ngân hàng đã chọn.',
                ];
            }

            $matched = $this->normalizeName($providerName) === $this->normalizeName($accountHolderName);

            return [
                'status' => $matched ? 'verified' : 'name_mismatch',
                'verified' => $matched,
                'bank' => $bank,
                'provider_account_name' => $providerName,
                'message' => $matched
                    ? 'Tài khoản ngân hàng đã được xác minh.'
                    : 'Tên chủ tài khoản không khớp với dữ liệu ngân hàng.',
            ];
        } catch (\Throwable) {
            return [
                'status' => 'provider_unavailable',
                'verified' => false,
                'bank' => $bank,
                'message' => 'Không thể kết nối dịch vụ xác minh tài khoản, hồ sơ sẽ chờ admin kiểm tra.',
            ];
        }
    }

    private function normalizeBank(array $bank): array
    {
        return [
            'id' => $bank['id'] ?? null,
            'name' => $bank['name'] ?? $bank['shortName'] ?? $bank['code'] ?? '',
            'short_name' => $bank['shortName'] ?? $bank['short_name'] ?? $bank['code'] ?? '',
            'code' => $bank['code'] ?? '',
            'bin' => (string) ($bank['bin'] ?? ''),
            'logo' => $bank['logo'] ?? null,
            'transfer_supported' => (bool) ($bank['transferSupported'] ?? $bank['isTransfer'] ?? false),
            'lookup_supported' => (bool) ($bank['lookupSupported'] ?? false),
        ];
    }

    private function normalizeName(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9 ]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function fallbackBanks(): array
    {
        return [
            ['name' => 'Ngân hàng TMCP Ngoại thương Việt Nam', 'short_name' => 'Vietcombank', 'code' => 'VCB', 'bin' => '970436'],
            ['name' => 'Ngân hàng TMCP Kỹ thương Việt Nam', 'short_name' => 'Techcombank', 'code' => 'TCB', 'bin' => '970407'],
            ['name' => 'Ngân hàng TMCP Quân đội', 'short_name' => 'MBBank', 'code' => 'MB', 'bin' => '970422'],
            ['name' => 'Ngân hàng TMCP Á Châu', 'short_name' => 'ACB', 'code' => 'ACB', 'bin' => '970416'],
            ['name' => 'Ngân hàng TMCP Việt Nam Thịnh Vượng', 'short_name' => 'VPBank', 'code' => 'VPB', 'bin' => '970432'],
        ];
    }
}
