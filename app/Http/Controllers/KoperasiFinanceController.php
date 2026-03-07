<?php

namespace App\Http\Controllers;

use App\Services\KoperasiFinanceService;
use App\Models\KoperasiWalletAccount;
use App\Services\KoperasiWalletService;
use Illuminate\Http\Request;

class KoperasiFinanceController extends Controller
{
    public function __construct(
        private readonly KoperasiFinanceService $financeService,
        private readonly KoperasiWalletService $walletService
    ) {
    }

    public function report(Request $request)
    {
        $data = $this->buildFinanceData($request);

        return view('koperasi.finance.report', $data);
    }

    public function accounting(Request $request)
    {
        $data = $this->buildFinanceData($request);

        return view('koperasi.finance.accounting', $data);
    }

    private function buildFinanceData(Request $request): array
    {
        $defaultStartDate = now()->startOfMonth()->toDateString();
        $defaultEndDate = now()->toDateString();

        $validated = validator(
            [
                'start_date' => (string) $request->query('start_date', $defaultStartDate),
                'end_date' => (string) $request->query('end_date', $defaultEndDate),
            ],
            [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]
        )->validate();

        $userId = auth()->user()->tenantUserId();
        $data = $this->financeService->build(
            $userId,
            $validated['start_date'],
            $validated['end_date']
        );

        $data['walletTypeOptions'] = KoperasiWalletAccount::typeOptions();
        $data['walletDefaults'] = $this->walletService->defaultWalletMap($userId);

        return $data;
    }
}
