<?php

namespace App\Http\Controllers\Gerencia\Logistica;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AddressLookupController extends Controller
{
    public function search(Request $request)
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        try {
            $response = Http::acceptJson()
                ->timeout(12)
                ->withHeaders($this->defaultHeaders())
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'countrycodes' => 'mx',
                    'limit' => 6,
                    'q' => $data['q'],
                ]);

            if (!$response->successful()) {
                return response()->json([]);
            }

            $payload = $response->json();

            return response()->json(is_array($payload) ? $payload : []);
        } catch (\Throwable $e) {
            return response()->json([]);
        }
    }

    public function reverse(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $response = Http::acceptJson()
                ->timeout(12)
                ->withHeaders($this->defaultHeaders())
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $data['lat'],
                    'lon' => $data['lng'],
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'message' => 'No se pudo convertir el punto a una dirección.',
                ], 502);
            }

            $payload = $response->json();

            return response()->json(is_array($payload) ? $payload : []);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo convertir el punto a una dirección.',
            ], 502);
        }
    }

    protected function defaultHeaders(): array
    {
        return [
            'User-Agent' => 'e-support-system/1.0 (logistica-address-picker)',
            'Accept-Language' => 'es-MX,es;q=0.9,en;q=0.8',
        ];
    }
}
