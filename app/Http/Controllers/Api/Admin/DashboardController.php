<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AccountingDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __construct(private readonly AccountingDashboardService $accounting) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'finance_period' => ['nullable', Rule::in(['week', 'month', 'year'])],
        ]);

        return response()->json([
            'accounting' => $this->accounting->payload($data['finance_period'] ?? 'month'),
        ]);
    }
}
