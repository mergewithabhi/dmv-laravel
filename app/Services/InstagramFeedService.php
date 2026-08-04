<?php

namespace App\Services;

use App\Models\InstagramConnection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class InstagramFeedService
{
    public function __construct(
        private readonly InstagramConnectionService $instagram
    ) {}

    public function posts(int $limit = 6): Collection
    {
        $endpoint = trim((string) config('services.instagram.media_endpoint'));
        $connection = $this->instagram->connection();
        $token = $this->instagram->accessToken($connection);

        if (! $connection || ! $token || $endpoint === '') {
            return collect();
        }

        $limit = max(1, min(12, $limit));
        $cacheKey = $this->cacheKey($connection);
        $staleKey = $cacheKey.'.stale';
        $minutes = max(5, (int) config('services.instagram.cache_minutes', 15));

        $posts = Cache::remember($cacheKey, now()->addMinutes($minutes), function () use (
            $endpoint,
            $token,
            $limit,
            $staleKey
        ): array {
            try {
                $response = Http::acceptJson()
                    ->connectTimeout(3)
                    ->timeout(8)
                    ->get($endpoint, [
                        'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,username',
                        'limit' => $limit,
                        'access_token' => $token,
                    ]);

                if (! $response->successful()) {
                    Log::warning('Instagram feed refresh failed.', ['status' => $response->status()]);

                    return Cache::get($staleKey, []);
                }

                $posts = collect($response->json('data', []))
                    ->map(fn (array $post): ?array => $this->normalize($post))
                    ->filter()
                    ->take($limit)
                    ->values()
                    ->all();

                if ($posts !== []) {
                    Cache::put($staleKey, $posts, now()->addDays(7));
                }

                return $posts;
            } catch (Throwable $exception) {
                Log::warning('Instagram feed refresh failed.', [
                    'exception' => $exception::class,
                ]);

                return Cache::get($staleKey, []);
            }
        });

        return collect($posts);
    }

    public function forget(?InstagramConnection $connection = null): void
    {
        $connection ??= $this->instagram->connection();
        if (! $connection) {
            return;
        }

        $cacheKey = $this->cacheKey($connection);
        Cache::forget($cacheKey);
        Cache::forget($cacheKey.'.stale');
    }

    private function normalize(array $post): ?array
    {
        $mediaType = strtoupper(trim((string) ($post['media_type'] ?? '')));
        $imageUrl = $mediaType === 'VIDEO'
            ? ($post['thumbnail_url'] ?? null)
            : ($post['media_url'] ?? null);
        $permalink = $post['permalink'] ?? null;

        if (! $this->isHttpsUrl($imageUrl) || ! $this->isHttpsUrl($permalink)) {
            return null;
        }

        $username = preg_replace('/[^A-Za-z0-9._]/', '', (string) ($post['username'] ?? ''));

        return [
            'id' => (string) ($post['id'] ?? ''),
            'caption' => trim((string) ($post['caption'] ?? '')),
            'media_type' => $mediaType,
            'image_url' => $imageUrl,
            'permalink' => $permalink,
            'timestamp' => $post['timestamp'] ?? null,
            'username' => $username,
        ];
    }

    private function isHttpsUrl(mixed $value): bool
    {
        return is_string($value)
            && filter_var($value, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https';
    }

    private function cacheKey(InstagramConnection $connection): string
    {
        return 'instagram.feed.'.sha1(
            config('services.instagram.media_endpoint').'|'.$connection->instagram_user_id
        );
    }
}
