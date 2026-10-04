<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

// Small helpers for photos kept on the "public" disk (storage/app/public, served at /storage)
class PublicImage
{
    // Everything we upload lives under this folder: restaurants/{restaurant id}/random-name.jpg
    private const FOLDER = 'restaurants/';

    public static function folderFor(int $restaurantId): string
    {
        return self::FOLDER.$restaurantId;
    }

    // Browser address for a stored file, or null when there is no such file
    // (the seeded sample data only has made-up placeholder paths)
    public static function url(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        // asset() follows the address the visitor used, so it works on localhost and 127.0.0.1 alike
        return asset('storage/'.$path);
    }

    // Deletes one uploaded file. Paths outside our own folder are ignored on purpose,
    // so a placeholder or shared file can never be removed by mistake.
    public static function delete(?string $path): void
    {
        if ($path && str_starts_with($path, self::FOLDER) && ! str_contains($path, '..')) {
            Storage::disk('public')->delete($path);
        }
    }

    // Removes all photos of a restaurant (used when the restaurant itself is deleted)
    public static function deleteFolderFor(int $restaurantId): void
    {
        Storage::disk('public')->deleteDirectory(self::folderFor($restaurantId));
    }
}
