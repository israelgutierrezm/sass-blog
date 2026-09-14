<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Infrastructure\Models;

use App\Modules\Shared\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Modules\Shared\Domain\Tenancy\Concerns\ScopedToSite;
use Database\Factories\AnalyticsDailyStatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rollup diario de analítica (ADR-021): una fila por (sitio, día, ruta). Fuente del
 * dashboard; poblada por el job de rollup vía UPSERT sobre la unique.
 *
 * @property int $id
 * @property int $site_id
 * @property Carbon $stat_date
 * @property string $path
 * @property int $views
 * @property int $visitors
 */
final class AnalyticsDailyStat extends Model
{
    /** @use HasFactory<AnalyticsDailyStatFactory> */
    use BelongsToWorkspace;

    use HasFactory;
    use ScopedToSite;

    protected $fillable = [
        'site_id',
        'stat_date',
        'path',
        'views',
        'visitors',
    ];

    protected function casts(): array
    {
        return [
            'stat_date' => 'date',
            'views' => 'integer',
            'visitors' => 'integer',
        ];
    }

    protected static function newFactory(): AnalyticsDailyStatFactory
    {
        return AnalyticsDailyStatFactory::new();
    }
}
