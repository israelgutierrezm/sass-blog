<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Application;

use App\Modules\Analytics\Domain\BotDetector;
use App\Modules\Analytics\Domain\VisitorHasher;
use App\Modules\Analytics\Infrastructure\Models\AnalyticsEvent;
use App\Modules\Shared\Domain\Tenancy\WorkspaceContext;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Support\Str;

/**
 * Registra un pageview (ADR-021). Corre dentro del WorkspaceContext del sitio para que
 * `workspace_id` se rellene por el trait de tenencia. La IP del visitante se usa SÓLO para
 * derivar el `visitor_hash` (HMAC diario) y NO se almacena. `path`/`referrer` se normalizan;
 * el referrer se reduce a su host (sin ruta ni query) por privacidad.
 */
final class RecordPageView
{
    public function __construct(
        private readonly WorkspaceContext $context,
        private readonly VisitorHasher $hasher,
        private readonly BotDetector $bots,
    ) {}

    public function handle(Site $site, string $path, ?string $referrer, string $visitorIp, string $userAgent): void
    {
        if (! config('sassblog.analytics.enabled')) {
            return;
        }

        $this->context->runFor($site->workspace_id, function () use ($site, $path, $referrer, $visitorIp, $userAgent): void {
            AnalyticsEvent::create([
                'site_id' => $site->id,
                'path' => $this->normalizePath($path),
                'occurred_at' => now(),
                'referrer_host' => $this->referrerHost($referrer),
                'visitor_hash' => $this->hasher->hash($site->id, $visitorIp, $userAgent),
                'is_bot' => $this->bots->isBot($userAgent),
            ]);
        });
    }

    /** Ruta pública canónica: con `/` inicial, sin `/` final (salvo raíz), acotada a 255. */
    private function normalizePath(string $path): string
    {
        $path = '/'.ltrim(trim($path), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');

        return Str::limit($path, 255, '');
    }

    /** Sólo el host del referrer (sin ruta/query), en minúsculas; null si no hay. */
    private function referrerHost(?string $referrer): ?string
    {
        if ($referrer === null || trim($referrer) === '') {
            return null;
        }

        $host = parse_url(trim($referrer), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? Str::limit(Str::lower($host), 255, '') : null;
    }
}
