<?php

namespace App\Services\Zalo;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZaloMiniAppIdentityVerifier
{
    public function verifyAccessToken(string $accessToken): array
    {
        $accessToken = trim($accessToken);

        if ($accessToken === '') {
            throw new RuntimeException('Thiếu access token Zalo.');
        }

        $appSecret = (string) config('services.zalo.app_secret', '');

        if ($appSecret === '') {
            throw new RuntimeException('Chưa cấu hình ZALO_APP_SECRET.');
        }

        $response = Http::timeout((float) config('services.zalo.timeout', 8))
            ->withHeaders(['access_token' => $accessToken])
            ->get((string) config('services.zalo.graph_me_url', 'https://graph.zalo.me/v2.0/me'), [
                'fields' => 'id,name,picture',
                'appsecret_proof' => hash_hmac('sha256', $accessToken, $appSecret),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Không xác thực được tài khoản Zalo.');
        }

        $payload = $response->json();
        $zaloUserId = (string) (
            data_get($payload, 'id')
            ?: data_get($payload, 'user_id')
            ?: data_get($payload, 'user_id_by_app')
        );

        if ($zaloUserId === '') {
            throw new RuntimeException('Zalo không trả về định danh người dùng.');
        }

        return [
            'id' => $zaloUserId,
            'name' => data_get($payload, 'name'),
            'picture' => data_get($payload, 'picture.data.url', data_get($payload, 'picture')),
            'raw' => $payload,
        ];
    }
}
