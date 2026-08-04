<?php

namespace App\Services;

use App\Models\InstagramConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class InstagramConnectionService
{
    public function connect(string $accessToken, int $userId): InstagramConnection
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            throw new RuntimeException('An Instagram access token is required.');
        }

        $profileResponse = Http::acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->get(rtrim((string) config('services.instagram.graph_url'), '/').'/me', [
                'fields' => 'id,user_id,username,account_type',
                'access_token' => $accessToken,
            ]);
        $instagramUserId = $profileResponse->successful()
            ? trim((string) ($profileResponse->json('user_id') ?: $profileResponse->json('id')))
            : '';
        if ($instagramUserId === '') {
            throw new RuntimeException('The Instagram access token could not be validated.');
        }

        return DB::transaction(function () use (
            $instagramUserId,
            $profileResponse,
            $accessToken,
            $userId
        ): InstagramConnection {
            InstagramConnection::query()->delete();

            return InstagramConnection::query()->create([
                'instagram_user_id' => $instagramUserId,
                'username' => trim((string) $profileResponse->json('username')) ?: null,
                'access_token' => $accessToken,
                'expires_at' => now()->addDays(60),
                'connected_by' => $userId,
            ]);
        });
    }

    public function connection(): ?InstagramConnection
    {
        return InstagramConnection::query()->latest('id')->first();
    }

    public function accessToken(?InstagramConnection $connection = null): ?string
    {
        $connection ??= $this->connection();
        if (! $connection) {
            return null;
        }

        if (! $connection->expires_at || $connection->expires_at->isAfter(now()->addDays(7))) {
            return $connection->access_token;
        }

        $response = Http::acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->get((string) config('services.instagram.refresh_url'), [
                'grant_type' => 'ig_refresh_token',
                'access_token' => $connection->access_token,
            ]);

        if ($response->successful() && filled($response->json('access_token'))) {
            $connection->forceFill([
                'access_token' => trim((string) $response->json('access_token')),
                'expires_at' => now()->addSeconds(max(
                    3600,
                    (int) $response->json('expires_in', 5184000)
                )),
            ])->save();

            return $connection->access_token;
        }

        return $connection->expires_at->isFuture() ? $connection->access_token : null;
    }
}
