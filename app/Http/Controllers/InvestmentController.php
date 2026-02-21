<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\InvestmentAllocation;
use App\Models\InvestmentAsset;
use App\Services\CoinPriceService;
use App\Models\Transaction;
use Illuminate\Http\Request;

class InvestmentController extends Controller
{
    public function __construct(private CoinPriceService $priceService)
    {
    }

    public function index()
    {
        $userId = auth()->user()->tenantUserId();
        $allocations = InvestmentAllocation::with(['asset','bankAccount'])
            ->where('user_id', $userId)
            ->latest('executed_at')
            ->get()
            ->map(function ($item) {
                $item->current_price = null;
                if ($item->asset->category === 'crypto' && $item->asset->coingecko_id) {
                    $item->current_price = app(CoinPriceService::class)->getIdrPrice($item->asset->coingecko_id);
                }
                $item->current_value = $item->current_price
                    ? $item->quantity * $item->current_price
                    : null;
                $item->pnl = $item->current_value && $item->amount_fiat
                    ? $item->current_value - $item->amount_fiat
                    : null;
                return $item;
            });

        return view('investments.index', compact('allocations'));
    }

    public function create()
    {
        $assets = InvestmentAsset::where('user_id', auth()->user()->tenantUserId())->orderBy('name')->get();
        $accounts = BankAccount::where('user_id', auth()->user()->tenantUserId())->get();
        return view('investments.create', compact('assets', 'accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'investment_asset_id' => 'required|exists:investment_assets,id',
            'bank_account_id'     => 'required|exists:bank_accounts,id',
            'amount_fiat'         => 'required|numeric|min:1',
            'executed_at'         => 'nullable|date',
            'note'                => 'nullable|string',
        ]);

        $asset = InvestmentAsset::where('id', $request->investment_asset_id)
            ->where('user_id', auth()->user()->tenantUserId())
            ->firstOrFail();

        $bank = BankAccount::where('id', $request->bank_account_id)
            ->where('user_id', auth()->user()->tenantUserId())
            ->firstOrFail();

        $price = null;
        $qty = null;

        if ($asset->category === 'crypto' && $asset->coingecko_id) {
            $price = $this->priceService->getIdrPrice($asset->coingecko_id);
            if ($price) {
                $qty = $request->amount_fiat / $price;
            }
        }

        $allocation = InvestmentAllocation::create([
            'user_id'            => auth()->user()->tenantUserId(),
            'bank_account_id'    => $bank->id,
            'investment_asset_id'=> $asset->id,
            'amount_fiat'        => $request->amount_fiat,
            'price_fiat'         => $price,
            'quantity'           => $qty,
            'currency'           => 'idr',
            'executed_at'        => $request->executed_at ?? now(),
            'note'               => $request->note,
        ]);

        // Catat transaksi pengeluaran (investasi) agar saldo rekening turun
        Transaction::create([
            'user_id'        => auth()->user()->tenantUserId(),
            'type'           => 'expense',
            'category_id'    => null,
            'project_id'     => null,
            'bank_account_id'=> $bank->id,
            'amount'         => $request->amount_fiat,
            'date'           => $allocation->executed_at->toDateString(),
            'note'           => 'Investasi ke ' . $asset->name,
        ]);

        // Kurangi saldo rekening (boleh minus)
        $bank->balance -= $request->amount_fiat;
        $bank->save();

        return redirect()->route('investments.index')
            ->with('success', 'Investasi berhasil dicatat');
    }
}
