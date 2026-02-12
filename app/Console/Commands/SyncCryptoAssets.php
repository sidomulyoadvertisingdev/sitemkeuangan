<?php

namespace App\Console\Commands;

use App\Models\InvestmentAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncCryptoAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:crypto-assets {--user= : User ID pemilik aset} {--limit=20 : Jumlah coin teratas}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi daftar aset crypto (top market cap) dari CoinGecko ke tabel investment_assets';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = (int) ($this->option('user') ?? 0);
        $limit  = (int) $this->option('limit');

        if ($userId <= 0) {
            $this->error('Option --user wajib diisi dengan ID user.');
            return self::FAILURE;
        }

        $url = 'https://api.coingecko.com/api/v3/coins/markets';
        $response = Http::timeout(15)->get($url, [
            'vs_currency' => 'idr',
            'order'       => 'market_cap_desc',
            'per_page'    => $limit,
            'page'        => 1,
            'sparkline'   => false,
        ]);

        if (!$response->ok()) {
            $this->error('Gagal mengambil data dari CoinGecko: ' . $response->status());
            return self::FAILURE;
        }

        $data = $response->json();
        $this->info('Mengimpor ' . count($data) . ' aset...');

        foreach ($data as $coin) {
            InvestmentAsset::updateOrCreate(
                [
                    'user_id'      => $userId,
                    'coingecko_id' => $coin['id'],
                ],
                [
                    'name'     => $coin['name'],
                    'symbol'   => strtoupper($coin['symbol']),
                    'category' => 'crypto',
                    'market'   => 'CoinGecko',
                    'meta'     => [
                        'image'      => $coin['image'] ?? null,
                        'rank'       => $coin['market_cap_rank'] ?? null,
                        'market_cap' => $coin['market_cap'] ?? null,
                    ],
                ]
            );
        }

        $this->info('Sinkronisasi selesai.');
        return self::SUCCESS;
    }
}
