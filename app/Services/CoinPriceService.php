<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class CoinPriceService
{
    /**
     * Ambil harga crypto (IDR) dari Coingecko.
     */
    public function getIdrPrice(string $coingeckoId): ?float
    {
        return Cache::remember("cg:price:{$coingeckoId}", 60, function () use ($coingeckoId) {
            $url = 'https://api.coingecko.com/api/v3/simple/price';
            $response = Http::get($url, [
                'ids' => $coingeckoId,
                'vs_currencies' => 'idr',
            ]);

            if (!$response->ok()) {
                return null;
            }

            $data = $response->json();
            return $data[$coingeckoId]['idr'] ?? null;
        });
    }
}
