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
        $pending = collect([
            $user->can('manage pages') ? Page::query()->where('workflow_status', 'in_review')->count() : 0,
            $user->can('manage roster') ? Person::query()->where('workflow_status', 'in_review')->count() : 0,
            $user->can('manage news') ? Post::query()->where('workflow_status', 'in_review')->count() : 0,
            $user->can('manage schedule') ? Game::query()->where('workflow_status', 'in_review')->count() : 0,
            $user->can('manage sponsors') ? Sponsor::query()->where('workflow_status', 'in_review')->count() : 0,
        ])->sum();

        $stats = ['Pending review' => $pending];
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

        return view('livewire.admin.dashboard', compact('stats', 'upcomingGames', 'recentSubmissions'))
            ->title('Dashboard')
            ->layoutData(['heading' => 'Dashboard']);
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('access admin'), 403);
    }
}
