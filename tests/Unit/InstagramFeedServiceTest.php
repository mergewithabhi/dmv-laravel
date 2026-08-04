<?php

namespace Tests\Unit;

use App\Models\InstagramConnection;
use App\Services\InstagramFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramFeedServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_and_caches_recent_instagram_posts(): void
    {
        config([
            'services.instagram.media_endpoint' => 'https://graph.instagram.test/me/media',
            'services.instagram.cache_minutes' => 15,
        ]);
        InstagramConnection::query()->create([
            'instagram_user_id' => 'account-1',
            'username' => 'dmvwarriors',
            'access_token' => 'test-token',
            'expires_at' => now()->addMonth(),
        ]);
        Cache::flush();
        Http::fake([
            'graph.instagram.test/*' => Http::response([
                'data' => [
                    [
                        'id' => 'post-1',
                        'caption' => 'Game night',
                        'media_type' => 'IMAGE',
                        'media_url' => 'https://scontent.cdninstagram.com/photo.jpg',
                        'permalink' => 'https://www.instagram.com/p/post-1/',
                        'timestamp' => '2026-08-04T00:00:00+0000',
                        'username' => 'dmvwarriors',
                    ],
                ],
            ]),
        ]);

        $service = app(InstagramFeedService::class);
        $first = $service->posts();
        $second = $service->posts();

        $this->assertCount(1, $first);
        $this->assertSame('Game night', $first->first()['caption']);
        $this->assertSame($first->all(), $second->all());
        Http::assertSentCount(1);
    }

    public function test_it_returns_no_posts_without_a_connected_account(): void
    {
        $this->assertTrue(app(InstagramFeedService::class)->posts()->isEmpty());
    }
}
