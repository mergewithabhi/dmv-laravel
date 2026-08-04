<?php

namespace App\Services;

use App\Models\MediaAsset;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminMediaUploadService
{
    public function store(UploadedFile $upload, string $kind, string $label): MediaAsset
    {
        $mime = (string) $upload->getMimeType();
        $originalExtension = strtolower($upload->getClientOriginalExtension());
        $isSvg = $mime === 'image/svg+xml'
            || ($originalExtension === 'svg' && in_array($mime, ['text/plain', 'application/xml', 'text/xml'], true));

        if (! in_array($mime, config('cms.allowed_upload_mimes'), true) && ! $isSvg) {
            throw ValidationException::withMessages(['upload' => 'Unsupported file type.']);
        }

        $validKind = match ($kind) {
            'image' => str_starts_with($mime, 'image/') && ! $isSvg,
            'icon' => str_starts_with($mime, 'image/') || $isSvg,
            'video' => in_array($mime, ['video/mp4', 'video/quicktime', 'video/webm'], true),
            default => false,
        };
        if (! $validKind) {
            throw ValidationException::withMessages(['upload' => 'Choose a valid image or video file.']);
        }
        if ($kind === 'image' && @getimagesize($upload->getRealPath()) === false) {
            throw ValidationException::withMessages(['upload' => 'The image is malformed.']);
        }

        $extension = match (true) {
            $isSvg => 'svg',
            $mime === 'image/jpeg' => 'jpg',
            $mime === 'image/png' => 'png',
            $mime === 'image/webp' => 'webp',
            $mime === 'image/gif' => 'gif',
            $mime === 'video/mp4' => 'mp4',
            $mime === 'video/quicktime' => 'mov',
            $mime === 'video/webm' => 'webm',
            default => null,
        };
        if (! $extension) {
            throw ValidationException::withMessages(['upload' => 'Unsupported file type.']);
        }

        $baseName = Str::slug(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
            ?: (string) Str::uuid();
        $title = Str::of(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME))
            ->replace(['-', '_'], ' ')
            ->title()
            ->trim()
            ->value() ?: $label;

        $asset = MediaAsset::query()->create([
            'uuid' => (string) Str::uuid(),
            'kind' => $kind,
            'title' => $title,
            'alt_text' => $kind === 'icon' ? null : $label,
            'is_decorative' => $kind === 'icon',
            'created_by' => auth()->id(),
        ]);

        try {
            if ($isSvg) {
                $sanitized = (new Sanitizer)->sanitize(file_get_contents($upload->getRealPath()));
                if ($sanitized === false) {
                    throw ValidationException::withMessages(['upload' => 'The SVG could not be sanitized.']);
                }
                $asset->addMediaFromString($sanitized)
                    ->usingFileName($baseName.'.'.$extension)
                    ->toMediaCollection('file');
            } else {
                $asset->addMedia($upload->getRealPath())
                    ->usingFileName($baseName.'.'.$extension)
                    ->toMediaCollection('file');
            }
        } catch (\Throwable $exception) {
            if ($asset->exists) {
                $asset->delete();
            }

            throw $exception;
        }

        activity('cms')->causedBy(auth()->user())->performedOn($asset)->log('uploaded media from content editor');

        return $asset;
    }
}
