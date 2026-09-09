<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Rendering;

/**
 * Evento de KERNEL: la ruta pública de un contenido cambió (ADR-018). Lo emiten los
 * módulos de dominio (Builder al cambiar el `path` de una página publicada; Content al
 * cambiar el `slug` de una entry publicada) SIN conocer quién reacciona. El módulo Seo
 * lo escucha y auto-crea el redirect `slug_change`. Vive en el kernel para no acoplar
 * los emisores con Seo: sin Seo instalado, el evento simplemente no tiene oyente.
 *
 * Ambas rutas vienen ya normalizadas (con "/" inicial, sin "/" final salvo raíz), como
 * las produce el render, para que `old_path` case exacto con la clave de búsqueda.
 */
final class PublicPathChanged
{
    public function __construct(
        public readonly int $siteId,
        public readonly string $oldPath,
        public readonly string $newPath,
    ) {}
}
