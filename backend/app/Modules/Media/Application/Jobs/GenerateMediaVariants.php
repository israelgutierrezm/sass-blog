<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Jobs;

use App\Modules\Media\Infrastructure\Models\MediaAsset;
use App\Modules\Media\Infrastructure\Models\MediaVariant;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Genera las variantes (thumb/medium/large) de un asset de imagen y lo marca ready
 * (ADR-016). IDEMPOTENTE: `updateOrCreate` por (asset, variant), así re-despachar no
 * duplica. NO agranda: se salta la variante cuyo ancho objetivo ya iguala/supera al
 * original. Corre en la cola `database`; fija el WorkspaceContext desde el evento.
 */
final class GenerateMediaVariants implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** mimes rasterizables que sabemos transformar (SVG no es raster). */
    private const RASTER = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        public readonly int $assetId,
        public readonly int $workspaceId,
    ) {}

    public function handle(WorkspaceContext $context): void
    {
        $context->runFor($this->workspaceId, function (): void {
            $asset = MediaAsset::find($this->assetId);
            if ($asset === null) {
                return;
            }

            if (in_array($asset->mime_type, self::RASTER, true)) {
                $this->generate($asset);
            }

            $asset->status = MediaAsset::STATUS_READY;
            $asset->save();
        });
    }

    private function generate(MediaAsset $asset): void
    {
        $disk = Storage::disk($asset->disk);
        $contents = $disk->get($asset->path);
        if ($contents === null) {
            return;
        }

        $manager = new ImageManager(Driver::class);

        /** @var array<string, int> $variants */
        $variants = (array) config('sassblog.media.variants', []);
        foreach ($variants as $name => $target) {
            // No agrandar: si el original ya es igual o menor, no duplicamos.
            if ($asset->width !== null && (int) $target >= $asset->width) {
                continue;
            }

            $image = $manager->decodeBinary($contents)->scaleDown(width: (int) $target);
            $encoded = $image->encodeUsingMediaType($asset->mime_type);
            $path = $this->variantPath($asset, (string) $name);
            $disk->put($path, (string) $encoded);

            MediaVariant::updateOrCreate(
                ['media_asset_id' => $asset->id, 'variant' => $name],
                [
                    'site_id' => $asset->site_id,
                    'disk' => $asset->disk,
                    'path' => $path,
                    'width' => $image->width(),
                    'height' => $image->height(),
                ],
            );
        }
    }

    private function variantPath(MediaAsset $asset, string $variant): string
    {
        $base = pathinfo($asset->path, PATHINFO_FILENAME);
        $ext = pathinfo($asset->path, PATHINFO_EXTENSION) ?: 'jpg';

        return "media/variants/{$base}-{$variant}.{$ext}";
    }
}
