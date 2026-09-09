<?php

declare(strict_types=1);

namespace App\Modules\Media\Application;

use App\Modules\Media\Infrastructure\Models\MediaAsset;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Http\UploadedFile;

/**
 * Sube un binario a la librería del sitio (ADR-016): calcula el checksum (sha256),
 * DEDUPLICA dentro del sitio (si el binario ya existe, devuelve ese asset; si estaba
 * borrado, lo restaura), guarda vía Filesystem y crea el MediaAsset con sus metadatos.
 * Las variantes de imagen las genera el job en un sub-slice posterior.
 *
 * Requiere contexto de workspace activo (workspace_id lo rellena BelongsToWorkspace).
 */
final class StoreMediaAsset
{
    public function handle(Site $site, UploadedFile $file, ?int $createdBy = null): MediaAsset
    {
        $checksum = (string) hash_file('sha256', $file->getRealPath());

        // Dedup por-sitio (incluye borrados: el índice único los cuenta).
        $existing = MediaAsset::withTrashed()
            ->where('site_id', $site->id)
            ->where('checksum', $checksum)
            ->first();
        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        $disk = (string) config('sassblog.media.disk', 'public');
        $path = (string) $file->store('media', $disk);

        [$width, $height] = $this->dimensions($file);

        $asset = new MediaAsset;
        $asset->site_id = $site->id;
        $asset->disk = $disk;
        $asset->path = $path;
        $asset->original_filename = $file->getClientOriginalName();
        $asset->mime_type = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $asset->size_bytes = (int) $file->getSize();
        $asset->width = $width;
        $asset->height = $height;
        $asset->checksum = $checksum;
        $asset->status = MediaAsset::STATUS_READY;
        $asset->created_by = $createdBy;
        $asset->save();

        return $asset;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(UploadedFile $file): array
    {
        if (! str_starts_with((string) $file->getMimeType(), 'image/')) {
            return [null, null];
        }

        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            return [null, null];
        }

        return [(int) $info[0], (int) $info[1]];
    }
}
