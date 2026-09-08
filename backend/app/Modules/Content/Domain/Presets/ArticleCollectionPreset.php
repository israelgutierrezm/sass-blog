<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Presets;

use App\Modules\Content\Domain\CollectionKind;
use App\Modules\Content\Domain\Fields\FieldType;
use Illuminate\Support\Str;

/**
 * Definición cerrada del preset de artículos (blog), sembrado al crear un site
 * (ADR-009/011). No hay constructor visual de campos en el MVP: este preset es la
 * única fuente de la colección `articles`. El template-como-Page se enlaza en un
 * sub-slice posterior (ruteo/bindings), no aquí.
 *
 * `tags` es Json (arreglo libre de strings) en el MVP: la taxonomía estructurada
 * son las categorías; multiselect exigiría un catálogo cerrado de opciones.
 */
final class ArticleCollectionPreset
{
    /**
     * @return array{attributes: array<string, mixed>, fields: list<array<string, mixed>>}
     */
    public static function definition(): array
    {
        return [
            'attributes' => [
                'handle' => 'articles',
                'name' => 'Artículos',
                'name_singular' => 'Artículo',
                'description' => 'Publicaciones del blog.',
                'kind' => CollectionKind::Article->value,
                'route_prefix' => 'blog',
            ],
            'fields' => [
                ['key' => 'excerpt', 'label' => 'Resumen', 'type' => FieldType::Textarea, 'required' => false],
                ['key' => 'body', 'label' => 'Cuerpo', 'type' => FieldType::RichText, 'required' => true],
                ['key' => 'featured_image', 'label' => 'Imagen destacada', 'type' => FieldType::Media, 'required' => false],
                ['key' => 'tags', 'label' => 'Etiquetas', 'type' => FieldType::Json, 'required' => false],
                ['key' => 'reading_time', 'label' => 'Tiempo de lectura (min)', 'type' => FieldType::Integer, 'required' => false],
                ['key' => 'featured', 'label' => 'Destacado', 'type' => FieldType::Boolean, 'required' => false],
            ],
        ];
    }

    /**
     * Schema de la Page plantilla (kind=collection_template) con placeholders de
     * binding ({ "$bind": … }) resueltos en render contra cada entry (ADR-011/012).
     * Usa componentes reales del registro (hero, text); no pasa por la validación
     * estricta del page schema porque los bindings ocupan el lugar de strings.
     *
     * @return array<string, mixed>
     */
    public static function templateSchema(): array
    {
        return [
            'schema_version' => 1,
            'sections' => [
                [
                    'id' => Str::upper((string) Str::ulid()),
                    'type' => 'hero',
                    'variant' => 'hero-centered',
                    'visible' => true,
                    'props' => [
                        'heading' => ['$bind' => 'entry.title'],
                        'subheading' => ['$bind' => 'entry.data.excerpt'],
                        'align' => 'center',
                    ],
                    'settings' => ['spacing' => ['top' => 'xl', 'bottom' => 'xl']],
                ],
                [
                    'id' => Str::upper((string) Str::ulid()),
                    'type' => 'text',
                    'variant' => 'text-prose',
                    'visible' => true,
                    'props' => [
                        'paragraphs' => [['$bind' => 'entry.data.body']],
                        'align' => 'left',
                    ],
                    'settings' => ['spacing' => ['top' => 'md', 'bottom' => 'md']],
                ],
            ],
        ];
    }
}
