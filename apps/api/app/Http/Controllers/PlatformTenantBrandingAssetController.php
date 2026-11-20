<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\WorkspaceBrandingAsset;
use App\Services\Branding\WorkspaceBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PlatformTenantBrandingAssetController extends Controller
{
    public function store(Request $request, string $workspacePublicId, WorkspaceBranding $branding): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'in:primary_logo,dark_logo,favicon'],
            'asset' => ['required', 'file', 'max:2048', 'mimetypes:image/png,image/jpeg,image/webp'],
        ]);
        $workspace = Workspace::query()->where('public_id', $workspacePublicId)->firstOrFail();
        $uploaded = $validated['asset'];
        if ($validated['kind'] === 'favicon' && $uploaded->getSize() > 524288) {
            throw ValidationException::withMessages(['asset' => 'Favicons must be 512 KiB or smaller.']);
        }
        $bytes = file_get_contents($uploaded->getRealPath());
        if (is_string($bytes) && (str_contains($bytes, 'ANIM') || str_contains($bytes, 'ANMF') || str_contains($bytes, 'acTL'))) {
            throw ValidationException::withMessages(['asset' => 'Animated branding assets are not supported.']);
        }
        $detected = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
        if ($validated['kind'] === 'favicon' && (! is_array($detected) || ($detected['mime'] ?? null) !== 'image/png')) {
            throw ValidationException::withMessages(['asset' => 'The favicon must be a PNG image.']);
        }
        $image = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
        if ($image === false) {
            throw ValidationException::withMessages(['asset' => 'The uploaded file is not a decodable static image.']);
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $maximum = $validated['kind'] === 'favicon' ? 512 : 4096;
        if ($width < 16 || $height < 16 || $width > $maximum || $height > $maximum) {
            imagedestroy($image);
            throw ValidationException::withMessages(['asset' => "Image dimensions must be between 16 and {$maximum} pixels."]);
        }
        ob_start();
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagepng($image, null, 9);
        $safeBytes = ob_get_clean();
        imagedestroy($image);
        if (! is_string($safeBytes) || $safeBytes === '') {
            throw ValidationException::withMessages(['asset' => 'The uploaded image could not be made safe.']);
        }
        $publicId = (string) Str::uuid();
        $path = "branding/{$workspace->public_id}/{$publicId}.png";
        $disk = (string) config('branding.asset_disk');
        if (! Storage::disk($disk)->put($path, $safeBytes)) {
            throw ValidationException::withMessages(['asset' => 'The branding asset could not be stored.']);
        }
        $asset = WorkspaceBrandingAsset::query()->create([
            'public_id' => $publicId,
            'workspace_id' => $workspace->id,
            'kind' => $validated['kind'],
            'storage_path' => $path,
            'media_type' => 'image/png',
            'size_bytes' => strlen($safeBytes),
            'width' => $width,
            'height' => $height,
            'checksum_sha256' => hash('sha256', $safeBytes),
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $branding->assetView($asset)], 201);
    }

    public function show(string $assetPublicId): StreamedResponse
    {
        $asset = WorkspaceBrandingAsset::query()->where('public_id', $assetPublicId)->firstOrFail();
        $disk = (string) config('branding.asset_disk');
        abort_unless(Storage::disk($disk)->exists($asset->storage_path), 404);

        return response()->stream(function () use ($asset, $disk): void {
            $stream = Storage::disk($disk)->readStream($asset->storage_path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $asset->media_type,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Disposition' => 'inline; filename="brand-'.$asset->public_id.'.png"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
