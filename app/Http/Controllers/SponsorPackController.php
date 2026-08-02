<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\SiteSetting;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class SponsorPackController extends Controller
{
    public function __invoke(): Response|BinaryFileResponse
    {
        $assetId = SiteSetting::value('sponsors.pack_media_id');
        $asset = $assetId ? MediaAsset::query()->find($assetId) : null;
        $media = $asset?->getFirstMedia('file');

        if (
            $media
            && $media->mime_type === 'application/pdf'
            && is_file($media->getPath())
        ) {
            return response()->download(
                $media->getPath(),
                'dmv-warriors-sponsor-pack.pdf',
                $this->downloadHeaders()
            );
        }

        $defaultPack = base_path('output/pdf/dmv-warriors-sponsor-pack.pdf');
        abort_unless(is_file($defaultPack), 404, 'The sponsorship pack is unavailable.');

        return response()->download(
            $defaultPack,
            'dmv-warriors-sponsor-pack.pdf',
            $this->downloadHeaders()
        );
    }

    private function downloadHeaders(): array
    {
        return [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];
    }
}
