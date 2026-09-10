<?php

declare(strict_types=1);

namespace App\Modules\Navigation\Http\Requests;

use App\Modules\Navigation\Infrastructure\Models\Menu;
use App\Modules\Navigation\Infrastructure\Models\MenuItem;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la Policy decide en el controlador
    }

    protected function prepareForValidation(): void
    {
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
            'label' => ['sometimes', 'string', 'max:120'],
            'link_type' => ['sometimes', Rule::in(MenuItem::LINK_TYPES)],
            'target' => ['sometimes', 'nullable', 'string', 'size:26'],
            'url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'parent' => ['sometimes', 'nullable', 'string', 'size:26'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $siteId = Site::findByUlid((string) $this->route('site'))?->id;
            $menuId = Menu::findByUlid((string) $this->route('menu'))?->id;
            $item = MenuItem::findByUlid((string) $this->route('item'));

            // Tipo EFECTIVO: el del payload o, si no cambia, el ya guardado.
            $linkType = (string) $this->input('link_type', $item?->link_type);

            // Sólo validamos el destino si se está tocando el tipo o el destino.
            if ($this->has('link_type') || $this->has('target')) {
                $target = $this->has('target') ? $this->input('target') : $item?->target_ulid;
                StoreMenuItemRequest::validateTarget($validator, $linkType, $target, $siteId);
            }

            if ($this->has('parent')) {
                StoreMenuItemRequest::validateParent($validator, $this->input('parent'), $menuId);
                self::validateNoCycle($validator, $item, $this->input('parent'));
            }
        });
    }

    /** Re-anidar no puede meter un ítem dentro de sí mismo ni de un descendiente. */
    private static function validateNoCycle(Validator $validator, ?MenuItem $item, mixed $parentUlid): void
    {
        if ($item === null || ! is_string($parentUlid) || $parentUlid === '') {
            return;
        }

        $cursor = MenuItem::findByUlid($parentUlid);
        $hops = 0;
        while ($cursor !== null && $hops++ < 50) {
            if ($cursor->id === $item->id) {
                $validator->errors()->add('parent', 'No se puede anidar un elemento dentro de sí mismo.');

                return;
            }
            $cursor = $cursor->parent_id !== null ? MenuItem::query()->find($cursor->parent_id) : null;
        }
    }
}
