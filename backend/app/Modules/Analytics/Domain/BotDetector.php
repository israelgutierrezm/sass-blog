<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Domain;

/**
 * Clasifica un User-Agent como bot por heurística (ADR-021). Imperfecta por diseño (los bots
 * mienten); su fin es descartar el grueso de crawlers/monitores de las métricas, no seguridad.
 * El patrón vive en `config('sassblog.analytics.bot_pattern')` (regex sin delimitadores).
 *
 * Un UA vacío se trata como bot: un navegador real siempre envía uno.
 */
final class BotDetector
{
    public function isBot(string $userAgent): bool
    {
        $ua = trim($userAgent);

        if ($ua === '') {
            return true;
        }

        $pattern = (string) config('sassblog.analytics.bot_pattern');

        if ($pattern === '') {
            return false;
        }

        return preg_match('/'.$pattern.'/i', $ua) === 1;
    }
}
