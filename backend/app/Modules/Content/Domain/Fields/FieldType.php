<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Fields;

/**
 * Catálogo CERRADO de tipos de campo de colección (ADR-010). Fuente de verdad en
 * el backend; espejada/commiteada desde TS (field-types.v1.json) en el sub-slice
 * de validación. Las reglas de validación por tipo se añaden ahí; aquí sólo el
 * conjunto y clasificaciones estructurales.
 */
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case RichText = 'richtext';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';
    case Money = 'money';
    case Email = 'email';
    case Url = 'url';
    case Slug = 'slug';
    case Select = 'select';
    case MultiSelect = 'multiselect';
    case Media = 'media';
    case MediaArray = 'media_array';
    case Relation = 'relation';
    case Json = 'json';

    /** Tipos cuya integridad exige BD + tenant (sólo validables en backend). */
    public function isReferential(): bool
    {
        return in_array($this, [self::Relation, self::Media, self::MediaArray], true);
    }

    /** Tipos escalares bindeables en plantillas (ADR-012). */
    public function isBindable(): bool
    {
        return ! in_array($this, [self::Media, self::MediaArray, self::MultiSelect, self::Relation, self::Json], true);
    }
}
