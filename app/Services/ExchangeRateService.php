<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ExchangeRateService
{
    public function usdMxn(): float
    {
        return Cache::remember('exchange.usd_mxn', 60 * 60 * 12, function () {
            $fallback = 18.0;
            $apiKey = config('services.exchange_rate.key');

            if (!$apiKey) {
                return $fallback;
            }

            try {
                $response = Http::timeout(10)
                    ->get("https://v6.exchangerate-api.com/v6/{$apiKey}/latest/USD");

                if (!$response->successful()) {
                    return $fallback;
                }

                $rates = $response->json('conversion_rates', []);
                $mxn = isset($rates['MXN']) ? (float) $rates['MXN'] : 0.0;

                return $mxn > 0 ? $mxn : $fallback;
            } catch (\Throwable $e) {
                return $fallback;
            }
        });
    }

    public function mxnUsd(): float
    {
        $usdMxn = $this->usdMxn();
        return $usdMxn > 0 ? (1 / $usdMxn) : 0.0;
    }

    public function payload(): array
    {
        $usdMxn = $this->usdMxn();
        $mxnUsd = $this->mxnUsd();

        return [
            'ok' => true,
            'usd_mxn' => $usdMxn,
            'mxn_usd' => $mxnUsd,
            'raw' => [
                'USD' => $usdMxn,
                'MXN' => $mxnUsd,
            ],
        ];
    }
}
