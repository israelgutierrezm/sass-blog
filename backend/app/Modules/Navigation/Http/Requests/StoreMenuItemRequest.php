<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Http\Requests;

use App\Modules\Builder\Infrastructure\Models\Page;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Navigation\Infrastructure\Models\Menu;
use App\Modules\Navigation\Infrastructure\Models\MenuItem;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StoreMenuItemRequest extends FormRequest
{
    /** Tipos de enlace que apuntan a una entidad por ULID (no url/home). */
    public const REFERENTIAL = [MenuItem::LINK_PAGE, MenuItem::LINK_ENTRY, MenuItem::LINK_COLLECTION];

    public function authorize(): bool
    {
        return true; // la Policy decide en el controlador
    }

    protected function prepareForValidation(): void
    {
        // Los ULID de referencia se normalizan a mayúsculas (como HasPublicUlid).
        foreach (['target', 'parent'] as $key) {
            if (is_string($this->input($key)) && $this->input($key) !== '') {
                $this->merge([$key => Str::upper($this->input($key))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'link_type' => ['required', Rule::in(MenuItem::LINK_TYPES)],
            'target' => [Rule::requiredIf(fn () => in_array($this->input('link_type'), self::REFERENTIAL, true)), 'nullable', 'string', 'size:26'],
            'url' => [Rule::requiredIf(fn () => $this->input('link_type') === MenuItem::LINK_URL), 'nullable', 'string', 'max:2048'],
            'parent' => ['nullable', 'string', 'size:26'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $siteId = Site::findByUlid((string) $this->route('site'))?->id;
            $menuId = Menu::findByUlid((string) $this->route('menu'))?->id;

            self::validateTarget($validator, (string) $this->input('link_type'), $this->input('target'), $siteId);
            self::validateParent($validator, $this->input('parent'), $menuId);
        });
    }

    /**
     * El destino debe existir y ser del tipo correcto en el sitio; los enlaces url/home
     * no llevan destino.
     */
    public static function validateTarget(Validator $validator, string $linkType, mixed $target, ?int $siteId): void
    {
        $referential = in_array($linkType, self::REFERENTIAL, true);

        if (! $referential) {
            if (is_string($target) && $target !== '') {
                $validator->errors()->add('target', 'Este tipo de enlace no lleva destino.');
            }

            return;
        }

        if (! is_string($target) || ! self::targetExists($linkType, $target, $siteId)) {
            $validator->errors()->add('target', 'El destino no existe en el sitio.');
        }
    }

    /** El padre (si se indica) debe ser un ítem del MISMO menú. */
    public static function validateParent(Validator $validator, mixed $parent, ?int $menuId): void
    {
        if (! is_string($parent) || $parent === '') {
            return;
        }

        $parentItem = MenuItem::findByUlid($parent);
        if ($parentItem === null || $parentItem->menu_id !== $menuId) {
            $validator->errors()->add('parent', 'El elemento padre no pertenece a este menú.');
        }
    }

    public static function targetExists(string $linkType, string $ulid, ?int $siteId): bool
    {
        $model = match ($linkType) {
            MenuItem::LINK_PAGE => Page::findByUlid($ulid),
            MenuItem::LINK_ENTRY => Entry::findByUlid($ulid),
            MenuItem::LINK_COLLECTION => Collection::findByUlid($ulid),
            default => null,
        };

        return $model !== null && $model->site_id === $siteId;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target.size' => 'El destino debe ser un identificador válido.',
        ];
    }
}
