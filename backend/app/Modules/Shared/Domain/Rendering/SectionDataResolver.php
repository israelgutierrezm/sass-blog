<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Rendering;

/**
 * Contrato de kernel para resolver los datos de una sección DINÁMICA del page
 * schema (p.ej. CollectionGrid) en tiempo de render (ADR-013).
 *
 * Lo implementa un módulo de dominio (Content: CollectionGridResolver) y lo
 * consume Builder al armar el sidecar `resolved` del payload de render. Así
 * Builder depende de esta ABSTRACCIÓN del kernel, nunca de Content.
 */
interface SectionDataResolver
{
    public function supports(string $type): bool;

    /**
     * @param  array<string, mixed>  $section  La sección del page schema.
     * @param  array<string, mixed>  $context  { workspace_id, site_id, ... }.
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function resolve(array $section, array $context): array;
}
