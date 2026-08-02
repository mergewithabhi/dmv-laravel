<?php

namespace App\Livewire\Admin;

use App\Models\FormSubmission;
use App\Models\Game;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\Person;
use App\Models\Post;
use App\Models\Sponsor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class Dashboard extends Component
{
    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function render()
    {
        $this->authorizeAccess();
        $user = auth()->user();
        $stats = [];
        if ($user->can('manage pages')) {
            $stats['Website pages'] = Page::query()->count();
        }
        if ($user->can('manage schedule')) {
            $stats['Upcoming games'] = Game::query()->upcoming()->count();
        }
        if ($user->can('manage submissions')) {
            $stats['New submissions'] = FormSubmission::query()->where('status', 'new')->count();
        }
        if ($user->can('manage media')) {
            $stats['Media assets'] = MediaAsset::query()->count();
        }
        if ($user->can('view audit log')) {
            $stats['Failed jobs'] = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        }

        $upcomingGames = $user->can('manage schedule')
            ? Game::query()->upcoming()->with(['homeTeam', 'awayTeam'])->limit(5)->get()
            : collect();
        $recentSubmissions = $user->can('manage submissions')
            ? FormSubmission::query()->latest()->limit(5)->get()
            : collect();

        $recentContent = collect();
        if ($user->can('manage pages')) {
            $recentContent = $recentContent->concat(
                Page::query()->latest('updated_at')->limit(5)->get()->map(fn (Page $page) => [
                    'title' => $page->title,
                    'type' => 'Website page',
                    'updated_at' => $page->updated_at,
                    'url' => route('admin.pages.edit', $page),
                ])
            );
        }
        if ($user->can('manage news')) {
            $recentContent = $recentContent->concat(
                Post::query()->latest('updated_at')->limit(5)->get()->map(fn (Post $post) => [
                    'title' => $post->title,
                    'type' => 'News',
                    'updated_at' => $post->updated_at,
                    'url' => route('admin.resources', 'posts'),
                ])
            );
        }
        $recentContent = $recentContent->sortByDesc('updated_at')->take(6)->values();

        return view('livewire.admin.dashboard', compact('stats', 'upcomingGames', 'recentSubmissions', 'recentContent'))
            ->title('Dashboard')
            ->layoutData(['heading' => 'Dashboard']);
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('access admin'), 403);
    }
}
