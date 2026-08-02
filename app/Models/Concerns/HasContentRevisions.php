<?php

namespace App\Models\Concerns;

use App\Models\ContentRevision;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

trait HasContentRevisions
{
    public static function bootHasContentRevisions(): void
    {
        static::saved(function (self $model): void {
            if (! $model->wasRecentlyCreated && ! $model->wasChanged()) {
                return;
            }

            $model->recordRevision($model->wasRecentlyCreated ? 'created' : 'updated');
        });
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisionable')->latest('version');
    }

    public function recordRevision(string $event, ?string $note = null): ContentRevision
    {
        return $this->revisions()->create([
            'version' => ((int) $this->revisions()->max('version')) + 1,
            'event' => $event,
            'snapshot' => $this->attributesToArray(),
            'note' => $note,
            'user_id' => Auth::id(),
        ]);
    }

    public function restoreRevision(ContentRevision $revision): void
    {
        abort_unless($revision->revisionable_type === $this->getMorphClass(), 422);
        abort_unless((int) $revision->revisionable_id === (int) $this->getKey(), 422);

        $snapshot = collect($revision->snapshot)
            ->except([$this->getKeyName(), 'created_at', 'updated_at', 'lock_version'])
            ->all();

        if (array_key_exists('lock_version', $this->getAttributes())) {
            $snapshot['lock_version'] = ((int) $this->getAttribute('lock_version')) + 1;
        }

        $this->forceFill($snapshot)->saveQuietly();
        $this->recordRevision('restored', "Restored revision {$revision->version}");
    }
}
