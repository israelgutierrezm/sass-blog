<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Rendering;

/**
 * Contrato de kernel para resolver una ruta pública DINÁMICA (p.ej. una página de
 * artículo /blog/{slug}) cuando el render estático no encuentra la página (ADR-011).
 *
 * Lo enlaza el módulo Content; si NO hay implementación enlazada, el render queda
 * SÓLO-estático (degradación elegante) y Builder no depende de Content.
 */
interface DynamicRouteResolver
{
    /**
     * Resuelve el payload de render para (site, path) o null si no aplica.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(int $siteId, string $path): ?array;
}
