<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VietmapProxyController extends Controller
{
    private const BASE_URL = 'https://maps.vietmap.vn';

    public function search(Request $request)
    {
        $params = $request->validate([
            'text' => ['required', 'string', 'max:500'],
            'focus' => ['nullable', 'string', 'max:80'],
            'display_type' => ['nullable', 'integer'],
        ]);

        return $this->vietmapJson('/api/search/v4', $params);
    }

    public function place(Request $request)
    {
        $params = $request->validate([
            'refid' => ['required', 'string', 'max:255'],
        ]);

        return $this->vietmapJson('/api/place/v4', $params);
    }

    public function reverse(Request $request)
    {
        $params = $request->validate([
            'lat' => ['required', 'numeric'],
            'lng' => ['required', 'numeric'],
            'display_type' => ['nullable', 'integer'],
        ]);

        return $this->vietmapJson('/api/reverse/v4', $params);
    }

    public function routeDirections(Request $request)
    {
        $params = $request->validate([
            'point' => ['required', 'array', 'size:2'],
            'point.*' => ['required', 'string', 'max:80'],
            'points_encoded' => ['nullable', 'in:true,false'],
            'vehicle' => ['nullable', 'string', 'max:40'],
        ]);

        return $this->vietmapJson('/api/route/v3', $params);
    }

    private function vietmapJson(string $path, array $params)
    {
        $params['apikey'] = $this->geocodeApiKey();

        $response = Http::acceptJson()
            ->timeout(10)
            ->get(self::BASE_URL.$path.'?'.$this->buildQuery($params));

        return $this->proxyResponse($response, 'application/json', 'private, max-age=60');
    }

    private function proxyResponse(ClientResponse $response, string $contentType, string $cacheControl)
    {
        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type') ?: $contentType)
            ->header('Cache-Control', $cacheControl);
    }

    private function geocodeApiKey(): string
    {
        return $this->settingKey('vietmap_geocode_api_key');
    }

    private function settingKey(string $key): string
    {
        $value = data_get(Setting::first(), "options.{$key}", '');

        abort_if(blank($value), 503, 'Chưa cấu hình VietMap API Key.');

        return (string) $value;
    }

    private function buildQuery(array $params): string
    {
        $parts = [];

        foreach ($params as $key => $value) {
            foreach ((array) $value as $item) {
                $parts[] = rawurlencode((string) $key).'='.rawurlencode((string) $item);
            }
        }

        return implode('&', $parts);
    }
}
