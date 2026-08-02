<?php

namespace App\Services;

use App\Enums\PublicationStatus;
use App\Models\ContentRevision;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResourceWorkflowService
{
    public function snapshot(Model $model, array $fieldNames): array
    {
        return collect($fieldNames)
            ->mapWithKeys(function (string $field) use ($model): array {
                $value = $model->getAttribute($field);

                return [$field => $value instanceof BackedEnum ? $value->value : $value];
            })
            ->all();
    }

    public function create(
        Model $model,
        array $snapshot,
        string $statusField,
        User $user
    ): Model {
        return DB::transaction(function () use ($model, $snapshot, $statusField, $user): Model {
            $liveAttributes = $snapshot;
            $liveAttributes[$statusField] = PublicationStatus::Draft->value;
            unset($liveAttributes['publish_at']);
            if ($model instanceof Season) {
                $liveAttributes['is_current'] = false;
            }
            if ($model instanceof Team) {
                $liveAttributes['is_home_team'] = false;
            }

            $model->forceFill($liveAttributes + [
                'workflow_status' => PublicationStatus::Draft->value,
                'draft_snapshot' => $snapshot,
                'draft_lock_version' => 1,
                'draft_saved_at' => now(),
            ])->saveQuietly();

            $this->recordRevision($model, $snapshot, 'draft_saved', $user);

            return $model->refresh();
        });
    }

    public function stage(
        Model $model,
        array $snapshot,
        int $expectedLockVersion,
        User $user,
        string $event = 'draft_saved'
    ): Model {
        return DB::transaction(function () use (
            $model,
            $snapshot,
            $expectedLockVersion,
            $user,
            $event
        ): Model {
            $locked = $model->newQuery()->lockForUpdate()->findOrFail($model->getKey());
            if ((int) $locked->getAttribute('draft_lock_version') !== $expectedLockVersion) {
                throw ValidationException::withMessages([
                    'form' => 'This draft changed in another session. Reload before saving.',
                ]);
            }

            $locked->forceFill([
                'draft_snapshot' => $snapshot,
                'draft_lock_version' => $expectedLockVersion + 1,
                'draft_saved_at' => now(),
                'workflow_status' => PublicationStatus::Draft->value,
                'submitted_by' => null,
                'approved_by' => null,
                'publish_at' => null,
            ])->saveQuietly();

            $this->recordRevision($locked, $snapshot, $event, $user);

            return $locked->refresh();
        });
    }

    public function approve(
        Model $model,
        string $statusField,
        User $user,
        ?Carbon $publishAt = null
    ): Model {
        return $publishAt?->isFuture()
            ? $this->schedule($model, $user, $publishAt)
            : $this->publish($model, $statusField, $user);
    }

    public function publish(Model $model, string $statusField, User $user): Model
    {
        return DB::transaction(function () use ($model, $statusField, $user): Model {
            $locked = $model->newQuery()->lockForUpdate()->findOrFail($model->getKey());
            abort_unless($locked->getAttribute('draft_snapshot'), 422, 'Save a draft before publishing it.');

            return $this->publishSnapshot($locked, $statusField, $user);
        });
    }

    public function schedule(
        Model $model,
        User $user,
        Carbon $publishAt
    ): Model {
        return DB::transaction(function () use ($model, $user, $publishAt): Model {
            $locked = $model->newQuery()->lockForUpdate()->findOrFail($model->getKey());
            abort_unless($locked->getAttribute('draft_snapshot'), 422, 'Save a draft before scheduling it.');
            abort_unless($publishAt->isFuture(), 422, 'Choose a future publication time.');

            $locked->forceFill([
                'workflow_status' => PublicationStatus::Scheduled->value,
                'approved_by' => $user->getKey(),
                'publish_at' => $publishAt,
            ])->saveQuietly();
            $this->recordRevision(
                $locked,
                $locked->getAttribute('draft_snapshot'),
                'scheduled',
                $user
            );

            return $locked->refresh();
        });
    }

    public function publishScheduled(Model $model, string $statusField): bool
    {
        return DB::transaction(function () use ($model, $statusField): bool {
            $locked = $model->newQuery()->lockForUpdate()->findOrFail($model->getKey());
            if (
                $this->statusValue($locked->getAttribute('workflow_status'))
                    !== PublicationStatus::Scheduled->value
                || ! $locked->getAttribute('publish_at')
                || $locked->getAttribute('publish_at')->isFuture()
                || ! $locked->getAttribute('draft_snapshot')
            ) {
                return false;
            }

            $this->publishSnapshot($locked, $statusField, null);

            return true;
        });
    }

    public function archive(Model $model, string $statusField, User $user): Model
    {
        return DB::transaction(function () use ($model, $statusField, $user): Model {
            $locked = $model->newQuery()->lockForUpdate()->findOrFail($model->getKey());
            if ($locked instanceof Season && $locked->is_current) {
                throw ValidationException::withMessages([
                    'form.status' => 'Set another season as current before archiving this one.',
                ]);
            }
            if ($locked instanceof Team && $locked->is_home_team) {
                throw ValidationException::withMessages([
                    'form.status' => 'Set another team as the DMV Warriors team before archiving this one.',
                ]);
            }
            $locked->forceFill([
                $statusField => PublicationStatus::Archived->value,
                'workflow_status' => PublicationStatus::Archived->value,
                'draft_snapshot' => null,
                'draft_saved_at' => null,
                'submitted_by' => null,
                'approved_by' => $user->getKey(),
                'publish_at' => null,
                'lock_version' => ((int) $locked->getAttribute('lock_version')) + 1,
            ])->saveQuietly();
            $locked->recordRevision('archived');

            return $locked->refresh();
        });
    }

    private function publishSnapshot(
        Model $model,
        string $statusField,
        ?User $user
    ): Model {
        $snapshot = $model->getAttribute('draft_snapshot');
        unset($snapshot[$statusField], $snapshot['publish_at']);

        $this->enforceSingletonFlags($model, $snapshot);

        $model->forceFill($snapshot + [
            $statusField => PublicationStatus::Published->value,
            'workflow_status' => PublicationStatus::Published->value,
            'draft_snapshot' => null,
            'draft_saved_at' => null,
            'approved_by' => $user?->getKey() ?? $model->getAttribute('approved_by'),
            'publish_at' => null,
            'published_at' => now(),
            'lock_version' => ((int) $model->getAttribute('lock_version')) + 1,
        ])->saveQuietly();
        $model->recordRevision('published');

        return $model->refresh();
    }

    private function enforceSingletonFlags(Model $model, array $snapshot): void
    {
        if ($model instanceof Season) {
            $currentSeasons = Season::query()
                ->where('is_current', true)
                ->lockForUpdate()
                ->get();
            if ((bool) ($snapshot['is_current'] ?? false)) {
                $currentSeasons
                    ->where('id', '!=', $model->getKey())
                    ->each(function (Season $season): void {
                        $season->forceFill([
                            'is_current' => false,
                            'lock_version' => $season->lock_version + 1,
                        ])->saveQuietly();
                        $season->recordRevision('current_reassigned');
                    });
            } elseif ($model->is_current) {
                throw ValidationException::withMessages([
                    'form.is_current' => 'Publish another current season before removing this designation.',
                ]);
            }
        }

        if ($model instanceof Team) {
            $homeTeams = Team::query()
                ->where('is_home_team', true)
                ->lockForUpdate()
                ->get();
            if ((bool) ($snapshot['is_home_team'] ?? false)) {
                $homeTeams
                    ->where('id', '!=', $model->getKey())
                    ->each(function (Team $team): void {
                        $team->forceFill([
                            'is_home_team' => false,
                            'lock_version' => $team->lock_version + 1,
                        ])->saveQuietly();
                        $team->recordRevision('home_team_reassigned');
                    });
            } elseif ($model->is_home_team) {
                throw ValidationException::withMessages([
                    'form.is_home_team' => 'Publish another DMV Warriors team before removing this designation.',
                ]);
            }
        }
    }

    private function recordRevision(
        Model $model,
        array $snapshot,
        string $event,
        ?User $user
    ): ContentRevision {
        return $model->revisions()->create([
            'version' => ((int) $model->revisions()->max('version')) + 1,
            'event' => $event,
            'snapshot' => $snapshot,
            'user_id' => $user?->getKey(),
        ]);
    }

    private function statusValue(mixed $status): mixed
    {
        return $status instanceof BackedEnum ? $status->value : $status;
    }
}
