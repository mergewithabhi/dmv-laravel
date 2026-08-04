<?php

namespace Tests\Feature;

use App\Livewire\Admin\GalleryManager;
use App\Models\GalleryItem;
use App\Models\MediaAsset;
use App\Services\AdminMediaUploadService;
use App\Services\StaticSiteImporter;
use App\Services\SiteChromeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\CreatesCmsUsers;
use Tests\TestCase;

class GalleryManagementTest extends TestCase
{
    use CreatesCmsUsers, RefreshDatabase;

    public function test_gallery_accepts_quicktime_video_uploads(): void
    {
        $editor = $this->cmsUser(['manage media'], 'Gallery Editor');
        $this->actingAs($editor);

        $asset = app(AdminMediaUploadService::class)->store(
            UploadedFile::fake()->create('game-clip.mov', 10, 'video/quicktime'),
            'video',
            'Game clip'
        );

        $this->assertSame('video', $asset->kind->value);
        $this->assertSame('video/quicktime', $asset->getFirstMedia('file')?->mime_type);
        $this->assertSame('mov', $asset->getFirstMedia('file')?->extension);
        $this->assertContains('mov', config('livewire.temporary_file_upload.preview_mimes'));
    }

    public function test_media_editor_can_add_edit_and_bulk_manage_gallery_images(): void
    {
        $editor = $this->cmsUser(['manage media'], 'Gallery Editor');
        $first = MediaAsset::query()->create([
            'kind' => 'image',
            'title' => 'Game night',
            'alt_text' => 'Players on the court',
        ]);
        $second = MediaAsset::query()->create([
            'kind' => 'image',
            'title' => 'Team photo',
            'alt_text' => 'The DMV Warriors team',
        ]);

        $component = Livewire::actingAs($editor)->test(GalleryManager::class);
        foreach ([$first, $second] as $index => $asset) {
            $component
                ->call('create')
                ->call('selectMedia', $asset->id)
                ->set('form.title', $asset->title)
                ->set('form.alt_text', $asset->alt_text)
                ->set('form.position', ($index + 1) * 10)
                ->call('save')
                ->assertHasNoErrors();
        }

        $items = GalleryItem::query()->orderBy('position')->get();
        $component
            ->set('selected', $items->modelKeys())
            ->call('bulkUnpublish')
            ->assertHasNoErrors();
        $this->assertSame(0, GalleryItem::query()->where('is_published', true)->count());

        $component
            ->set('selected', $items->modelKeys())
            ->call('bulkPublish')
            ->assertHasNoErrors();
        $this->assertSame(2, GalleryItem::query()->where('is_published', true)->count());

        $component
            ->call('edit', $items->first()->id)
            ->set('form.caption', 'Opening night')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('Opening night', $items->first()->refresh()->caption);

        $component
            ->set('selected', $items->modelKeys())
            ->call('bulkDelete')
            ->assertHasNoErrors();
        $this->assertDatabaseCount('gallery_items', 0);
        $this->assertDatabaseCount('media_assets', 2);
    }

    public function test_public_gallery_shows_only_published_images_and_header_excludes_policies(): void
    {
        app(StaticSiteImporter::class)->run();
        $asset = MediaAsset::query()->create([
            'kind' => 'image',
            'title' => 'Published gallery image',
            'alt_text' => 'Warriors player taking a shot',
        ]);
        $asset->addMediaFromBase64(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        )
            ->usingFileName('gallery.png')
            ->toMediaCollection('file');
        GalleryItem::query()->create([
            'media_asset_id' => $asset->id,
            'title' => 'Game day',
            'alt_text' => 'Warriors player taking a shot',
            'position' => 10,
            'is_published' => true,
        ]);
        GalleryItem::query()->create([
            'media_asset_id' => MediaAsset::query()->create([
                'kind' => 'image',
                'title' => 'Hidden image',
                'alt_text' => 'Hidden image',
            ])->id,
            'title' => 'Hidden',
            'alt_text' => 'Hidden image',
            'position' => 20,
            'is_published' => false,
        ]);
        app(SiteChromeService::class)->forget();

        $this->get('/gallery')
            ->assertOk()
            ->assertSee('Game day')
            ->assertDontSee('Hidden');

        $chrome = app(SiteChromeService::class)->data();
        $this->assertFalse($chrome['navigation']->contains('label', 'Policies'));
        $this->assertTrue($chrome['navigation']->contains('label', 'Gallery'));
        $this->assertTrue($chrome['footerNavigation']->contains('label', 'Policies'));
        $this->assertTrue($chrome['footerNavigation']->contains('label', 'Gallery'));
    }
}
