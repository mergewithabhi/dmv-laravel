<?php

namespace App\Models;

use App\Enums\GameStatus;
use App\Enums\PublicationStatus;
use App\Models\Concerns\HasContentRevisions;
use App\Models\Concerns\HasPublicationWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Game extends Model
{
    use HasContentRevisions, HasPublicationWorkflow, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'status' => GameStatus::class,
            'publication_status' => PublicationStatus::class,
            'workflow_status' => PublicationStatus::class,
            'draft_snapshot' => 'array',
            'draft_saved_at' => 'datetime',
            'is_featured' => 'boolean',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected function publicationStatusColumn(): string
    {
        return 'publication_status';
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query
            ->where('starts_at', '>=', now())
            ->where('status', GameStatus::Scheduled->value)
            ->orderBy('starts_at');
    }

    public function isHomeGame(): bool
    {
        return (bool) $this->homeTeam?->is_home_team;
    }

    public function opponent(): ?Team
    {
        return $this->isHomeGame() ? $this->awayTeam : $this->homeTeam;
    }

    public function resultForHomeTeam(): ?string
    {
        if ($this->status !== GameStatus::Final || $this->home_score === null || $this->away_score === null) {
            return null;
        }

        $teamScore = $this->isHomeGame() ? $this->home_score : $this->away_score;
        $opponentScore = $this->isHomeGame() ? $this->away_score : $this->home_score;

        return ($teamScore > $opponentScore ? 'W' : 'L')." {$teamScore}-{$opponentScore}";
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
