<?php

namespace App\Services;

use App\Enums\PublicationStatus;
use App\Models\ContentRevision;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PageWorkflowService
{
    public function snapshot(Page $page): array
    {
        $page->loadMissing('sections');

        return [
            'page' => [
                'title' => $page->title,
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'canonical_url' => $page->canonical_url,
                'og_media_id' => $page->og_media_id,
                'is_indexable' => $page->is_indexable,
            ],
            'sections' => $page->sections->mapWithKeys(fn (PageSection $section) => [
                $section->id => [
                    'is_enabled' => $section->is_enabled,
                    'payload' => $section->payload,
                ],
            ])->all(),
        ];
    }

    public function stage(
        Page $page,
        array $snapshot,
        int $expectedLockVersion,
        User $user,
        string $event = 'draft_saved'
    ): Page {
        return DB::transaction(function () use ($page, $snapshot, $expectedLockVersion, $user, $event): Page {
            $locked = Page::query()->lockForUpdate()->findOrFail($page->id);
            if ((int) $locked->draft_lock_version !== $expectedLockVersion) {
                throw ValidationException::withMessages([
                    'pageForm' => 'This draft changed in another session. Reload before saving.',
                ]);
            }

            $locked->forceFill([
                'draft_snapshot' => $snapshot,
                'draft_lock_version' => $locked->draft_lock_version + 1,
                'draft_saved_at' => now(),
                'workflow_status' => PublicationStatus::Draft->value,
                'submitted_by' => null,
                'approved_by' => null,
                'publish_at' => null,
            ])->saveQuietly();

            $this->recordDraftRevision($locked, $snapshot, $event, $user);

            return $locked->refresh()->load('sections');
        });
    }

    public function submit(Page $page, User $user): Page
    {
        return DB::transaction(function () use ($page, $user): Page {
            $locked = Page::query()->lockForUpdate()->findOrFail($page->id);
            abort_unless($locked->draft_snapshot, 422, 'Save a draft before submitting it.');
            abort_unless(
                $locked->workflow_status->value === PublicationStatus::Draft->value,
                422,
                'Only a saved draft can be submitted.'
            );

            $locked->forceFill([
                'workflow_status' => PublicationStatus::InReview->value,
                'submitted_by' => $user->getKey(),
            ])->saveQuietly();

            $this->recordDraftRevision(
                $locked,
                $locked->draft_snapshot,
                'submitted_for_review',
                $user
            );

            return $locked->refresh()->load('sections');
        });
    }

    public function approve(Page $page, User $user, ?Carbon $publishAt = null): Page
    {
        return DB::transaction(function () use ($page, $user, $publishAt): Page {
            $locked = Page::query()->lockForUpdate()->with('sections')->findOrFail($page->id);
            abort_unless($locked->draft_snapshot, 422, 'There is no draft to publish.');
            abort_unless(
                $locked->workflow_status->value === PublicationStatus::InReview->value,
                422,
                'Only content in review can be approved.'
            );

            if ($publishAt?->isFuture()) {
                $locked->forceFill([
                    'workflow_status' => PublicationStatus::Scheduled->value,
                    'approved_by' => $user->getKey(),
                    'publish_at' => $publishAt,
                ])->saveQuietly();
                $this->recordDraftRevision($locked, $locked->draft_snapshot, 'scheduled', $user);

                return $locked->refresh()->load('sections');
            }

            return $this->publishSnapshot($locked, $user);
        });
    }

    public function publishScheduled(Page $page): bool
    {
        return DB::transaction(function () use ($page): bool {
            $locked = Page::query()->lockForUpdate()->with('sections')->findOrFail($page->id);
            if (
                $locked->workflow_status->value !== PublicationStatus::Scheduled->value
                || ! $locked->publish_at
                || $locked->publish_at->isFuture()
                || ! $locked->draft_snapshot
            ) {
                return false;
            }

            $this->publishSnapshot($locked, null);

            return true;
        });
    }

    public function applyDraftForPreview(Page $page): Page
    {
        $page->loadMissing('sections');
        $snapshot = $page->draft_snapshot;
        if (! $snapshot) {
            return $page;
        }

        $page->forceFill($snapshot['page'] ?? []);
        foreach ($page->sections as $section) {
            if ($values = $snapshot['sections'][$section->id] ?? null) {
                $section->forceFill($values);
            }
        }

        return $page;
    }

    private function publishSnapshot(Page $page, ?User $user): Page
    {
        $snapshot = $page->draft_snapshot;
        $page->forceFill(($snapshot['page'] ?? []) + [
            'status' => PublicationStatus::Published->value,
            'workflow_status' => PublicationStatus::Published->value,
            'draft_snapshot' => null,
            'draft_saved_at' => null,
            'approved_by' => $user?->getKey() ?? $page->approved_by,
            'publish_at' => null,
            'published_at' => now(),
            'lock_version' => $page->lock_version + 1,
        ])->saveQuietly();

        foreach ($snapshot['sections'] ?? [] as $sectionId => $values) {
            $section = $page->sections->firstWhere('id', (int) $sectionId);
            if (! $section) {
                continue;
            }

            $section->forceFill([
                'is_enabled' => (bool) ($values['is_enabled'] ?? true),
                'payload' => $values['payload'] ?? [],
                'lock_version' => $section->lock_version + 1,
            ])->saveQuietly();
            $section->recordRevision('published');
        }

        $page->recordRevision('published');

        return $page->refresh()->load('sections');
    }

    private function recordDraftRevision(
        Page $page,
        array $snapshot,
        string $event,
        ?User $user
    ): ContentRevision {
        return $page->revisions()->create([
            'version' => ((int) $page->revisions()->max('version')) + 1,
            'event' => $event,
            'snapshot' => $snapshot,
            'user_id' => $user?->getKey(),
        ]);
    }
}
