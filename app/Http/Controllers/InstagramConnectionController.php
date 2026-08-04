<?php

namespace App\Http\Controllers;

use App\Models\SocialLink;
use App\Services\InstagramConnectionService;
use App\Services\InstagramFeedService;
use App\Services\SiteChromeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class InstagramConnectionController extends Controller
{
    public function store(
        Request $request,
        InstagramConnectionService $instagram,
        InstagramFeedService $feed,
        SiteChromeService $chrome
    ): RedirectResponse {
        $accessToken = trim((string) $request->input('access_token'));
        if ($accessToken === '' || strlen($accessToken) > 4096) {
            return redirect()
                ->route('admin.settings')
                ->with('error', 'Enter a valid Instagram access token.');
        }

        try {
            $connection = $instagram->connect($accessToken, (int) $request->user()->getKey());
            $feed->forget($connection);
            if ($connection->username) {
                SocialLink::query()
                    ->where('platform', 'instagram')
                    ->update([
                        'url' => 'https://www.instagram.com/'.$connection->username.'/',
                        'is_enabled' => true,
                    ]);
                $chrome->forget();
            }
        } catch (Throwable $exception) {
            Log::warning('Instagram access token validation failed.', [
                'exception' => $exception::class,
            ]);

            return redirect()
                ->route('admin.settings')
                ->with('error', 'The Instagram access token could not be validated.');
        }

        activity('cms')
            ->causedBy($request->user())
            ->withProperties([
                'instagram_user_id' => $connection->instagram_user_id,
                'username' => $connection->username,
            ])
            ->log('saved Instagram access token');

        return redirect()
            ->route('admin.settings')
            ->with('success', 'Instagram account connected and the Home feed is active.');
    }

    public function destroy(
        Request $request,
        InstagramConnectionService $instagram,
        InstagramFeedService $feed
    ): RedirectResponse {
        $connection = $instagram->connection();
        if ($connection) {
            $feed->forget($connection);
            $connection->delete();
            activity('cms')->causedBy($request->user())->log('disconnected Instagram account');
        }

        return redirect()
            ->route('admin.settings')
            ->with('success', 'Instagram account disconnected.');
    }
}
