<?php

declare(strict_types=1);

namespace App\Modules\Content\Application;

use App\Modules\Content\Domain\Fields\FieldType;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crea una colección con sus campos de forma atómica, scopeada al site (y al
 * workspace por contexto). El `handle` es el identificador máquina: se normaliza a
 * slug y se hace único dentro de (workspace, site). Usado por el preset de
 * artículos y, más adelante, por el API de administración.
 */
final class CreateCollection
{
    public function __construct(private readonly SlugGenerator $slugs) {}

    /**
     * @param  array<string, mixed>  $attributes  handle?, name, name_singular?, description?, kind?, route_prefix?, template_page_id?, created_by?
     * @param  list<array<string, mixed>>  $fields  key, label?, type, required?, config?, related_collection_id?, position?
     */
    public function handle(Site $site, array $attributes, array $fields = []): Collection
    {
        return DB::transaction(function () use ($site, $attributes, $fields): Collection {
            $collection = new Collection;
            $collection->site_id = $site->id;
            $collection->fill(Arr::only($attributes, [
                'name', 'name_singular', 'description', 'kind', 'route_prefix', 'template_page_id', 'created_by',
            ]));

            $source = (string) ($attributes['handle'] ?? $attributes['name'] ?? 'collection');
            $collection->handle = $this->slugs->unique(
                $source,
                fn (string $candidate): bool => Collection::query()
                    ->where('site_id', $site->id)
                    ->where('handle', $candidate)
                    ->exists(),
                'collection',
            );

            $collection->save();

            foreach (array_values($fields) as $index => $field) {
                $type = $field['type'] instanceof FieldType ? $field['type']->value : $field['type'];

                $collection->fields()->create([
                    'site_id' => $site->id,
                    'key' => $field['key'],
                    'label' => $field['label'] ?? Str::headline((string) $field['key']),
                    'type' => $type,
                    'required' => (bool) ($field['required'] ?? false),
                    'config' => $field['config'] ?? null,
                    'related_collection_id' => $field['related_collection_id'] ?? null,
                    'position' => $field['position'] ?? $index,
                ]);
            }

            return $collection->load('fields');
        });
    }
}
