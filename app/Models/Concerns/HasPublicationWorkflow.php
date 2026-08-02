<?php

namespace App\Models\Concerns;

use App\Enums\PublicationStatus;
use App\Models\User;
use App\Services\ResourceWorkflowService;
use Illuminate\Database\Eloquent\Builder;

trait HasPublicationWorkflow
{
    public static function bootHasPublicationWorkflow(): void
    {
        static::creating(function ($model): void {
            if ($model->getAttribute('workflow_status') !== null) {
                return;
            }

            $status = $model->getAttribute($model->publicationStatusColumn());
            $model->setAttribute(
                'workflow_status',
                $status instanceof PublicationStatus
                    ? $status->value
                    : ($status ?: PublicationStatus::Draft->value)
            );
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where($this->publicationStatusColumn(), PublicationStatus::Published->value)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function approve(User $user, mixed $publishAt = null): void
    {
        if (
            $this->getAttribute('draft_snapshot')
        ) {
            app(ResourceWorkflowService::class)->approve(
                $this,
                $this->publicationStatusColumn(),
                $user,
                $publishAt
            );

            return;
        }

        $scheduled = $publishAt && now()->lt($publishAt);

        $attributes = [
            $this->publicationStatusColumn() => $scheduled
                ? PublicationStatus::Scheduled->value
                : PublicationStatus::Published->value,
            'approved_by' => $user->getKey(),
            'publish_at' => $scheduled ? $publishAt : null,
            'published_at' => $scheduled ? null : now(),
        ];
        if (array_key_exists('workflow_status', $this->getAttributes())) {
            $attributes['workflow_status'] = $attributes[$this->publicationStatusColumn()];
        }

        $this->forceFill($attributes)->save();
    }

    public function publishScheduled(): bool
    {
        if ($this->getAttribute('draft_snapshot')) {
            return app(ResourceWorkflowService::class)->publishScheduled(
                $this,
                $this->publicationStatusColumn()
            );
        }

        $status = $this->getAttribute($this->publicationStatusColumn());
        $statusValue = $status instanceof PublicationStatus ? $status->value : $status;

        if (
            $statusValue !== PublicationStatus::Scheduled->value
            || ! $this->publish_at
            || $this->publish_at->isFuture()
        ) {
            return false;
        }

        $attributes = [
            $this->publicationStatusColumn() => PublicationStatus::Published->value,
            'published_at' => now(),
            'publish_at' => null,
        ];
        if (array_key_exists('workflow_status', $this->getAttributes())) {
            $attributes['workflow_status'] = PublicationStatus::Published->value;
        }
        $this->forceFill($attributes)->save();

        return true;
    }

    public function archive(): void
    {
        $attributes = [
            $this->publicationStatusColumn() => PublicationStatus::Archived->value,
        ];
        if (array_key_exists('workflow_status', $this->getAttributes())) {
            $attributes['workflow_status'] = PublicationStatus::Archived->value;
        }
        $this->forceFill($attributes)->save();
    }

    protected function publicationStatusColumn(): string
    {
        return 'status';
    }
}
