<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Rendering;

/**
 * Compone todos los SectionDataResolver etiquetados (ADR-013): delega en el PRIMERO que
 * soporta el tipo de sección. Permite que varios módulos aporten resolvers (Content:
 * collection-grid; Navigation: navigation) bajo el único contrato de kernel, sin que
 * Builder/render conozcan a ninguno. Sin resolvers, no soporta nada (degradación).
 */
final class CompositeSectionDataResolver implements SectionDataResolver
{
    /**
     * @param  list<SectionDataResolver>  $resolvers
     */
    public function __construct(private readonly array $resolvers) {}

    public function supports(string $type): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $context
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function resolve(array $section, array $context): array
    {
        $type = is_string($section['type'] ?? null) ? $section['type'] : '';

        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($type)) {
                return $resolver->resolve($section, $context);
            }
        }

        return ['items' => [], 'total' => 0];
    }
}
