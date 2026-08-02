<?php

namespace Tests\Feature;

use App\Livewire\Admin\MediaLibrary;
use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesCmsUsers;
use Tests\TestCase;

class MediaSecurityTest extends TestCase
{
    use CreatesCmsUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('tmp-for-tests');
        Storage::disk('tmp-for-tests')->makeDirectory('livewire-tmp');
    }

    public function test_svg_upload_is_sanitized_before_storage(): void
    {
        $user = $this->cmsUser(['manage media'], 'Media Editor');
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20">
<script>alert('xss')</script>
<rect width="20" height="20" onload="alert('xss')" />
</svg>
SVG;
        $file = UploadedFile::fake()->createWithContent('unsafe.svg', $svg);

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->set('upload', $file)
            ->set('title', 'Sanitized icon')
            ->set('kind', 'icon')
            ->set('isDecorative', true)
            ->call('uploadAsset')
            ->assertHasNoErrors();

        $asset = MediaAsset::query()->firstOrFail();
        $stored = file_get_contents($asset->getFirstMedia('file')->getPath());
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onload=', $stored);
    }

    public function test_svg_content_disguised_with_a_png_name_is_sanitized_and_stored_as_svg(): void
    {
        $user = $this->cmsUser(['manage media'], 'Media Editor');
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20">
<script>alert(document.domain)</script>
<rect width="20" height="20" onload="alert(1)" />
</svg>
SVG;
        $file = UploadedFile::fake()
            ->createWithContent('avatar.png', $svg)
            ->mimeType('image/svg+xml');

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->set('upload', $file)
            ->set('title', 'Disguised SVG')
            ->set('kind', 'icon')
            ->set('isDecorative', true)
            ->call('uploadAsset')
            ->assertHasNoErrors();

        $asset = MediaAsset::query()->firstOrFail();
        $media = $asset->getFirstMedia('file');
        $stored = file_get_contents($media->getPath());

        $this->assertSame('avatar.svg', $media->file_name);
        $this->assertSame('image/svg+xml', $media->mime_type);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onload=', $stored);
    }

    public function test_executable_content_with_an_image_extension_is_rejected(): void
    {
        $user = $this->cmsUser(['manage media'], 'Media Editor');
        $file = UploadedFile::fake()->createWithContent(
            'profile.png',
            '<?php echo shell_exec($_GET["command"] ?? "");'
        )->mimeType('text/x-php');

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->set('upload', $file)
            ->set('title', 'Executable payload')
            ->set('kind', 'image')
            ->set('altText', 'Profile')
            ->call('uploadAsset')
            ->assertStatus(422);

        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_document_content_disguised_as_an_svg_icon_is_rejected(): void
    {
        $user = $this->cmsUser(['manage media'], 'Media Editor');
        $file = UploadedFile::fake()->createWithContent(
            'partner.svg',
            "%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"
        )->mimeType('application/pdf');

        Livewire::actingAs($user)
            ->test(MediaLibrary::class)
            ->set('upload', $file)
            ->set('title', 'Mismatched icon')
            ->set('kind', 'icon')
            ->set('isDecorative', true)
            ->call('uploadAsset')
            ->assertStatus(422);

        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('media', 0);
    }
}
