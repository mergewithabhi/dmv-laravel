<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\InstagramConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramConnectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_validates_and_encrypts_a_cms_supplied_access_token(): void
    {
        config([
            'services.instagram.graph_url' => 'https://graph.instagram.test',
        ]);
        Http::fake([
            'graph.instagram.test/me*' => Http::response([
                'user_id' => 'instagram-user-1',
                'username' => 'dmvwarriors',
                'account_type' => 'BUSINESS',
            ]),
        ]);
        $user = User::factory()->create();

        $connection = app(InstagramConnectionService::class)->connect(
            'long-lived-token',
            $user->id
        );

        $this->assertSame('instagram-user-1', $connection->instagram_user_id);
        $this->assertSame('dmvwarriors', $connection->username);
        $this->assertSame('long-lived-token', $connection->access_token);
        $this->assertNotSame(
            'long-lived-token',
            DB::table('instagram_connections')->value('access_token')
        );
    }
}
