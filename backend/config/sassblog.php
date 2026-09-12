<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Registro de módulos
    |--------------------------------------------------------------------------
    |
    | Mapa declarativo de los módulos del monolito modular. Única fuente de verdad
    | sobre qué módulos EXISTEN. El cargador (App\Providers\ModuleServiceProvider)
    | recorre este registro —nunca el sistema de archivos— para enganchar cada
    | módulo. Una carpeta creada por error no se convierte en módulo cargado en
    | silencio: si un módulo existe, está declarado aquí.
    |
    | 'layer':
    |   kernel  → shared kernel; no depende de ningún módulo de dominio.
    |   domain  → módulos de dominio; pueden depender del kernel.
    |
    | 'depends_on': módulos de DOMINIO de los que este módulo puede depender en
    | código. El kernel es dependible por todos, así que no se lista.
    |
    | 'label': nombre en español para la interfaz (el identificador es inglés
    | porque es código; ver CLAUDE.md, sección Idioma).
    |
    */

    'modules' => [
        'Shared' => ['layer' => 'kernel', 'label' => 'General',        'depends_on' => []],
        'Tenancy' => ['layer' => 'kernel', 'label' => 'Espacios',       'depends_on' => []],
        'Identity' => ['layer' => 'kernel', 'label' => 'Identidad',      'depends_on' => []],
        'Audit' => ['layer' => 'kernel', 'label' => 'Auditoría',      'depends_on' => []],
        'Sites' => ['layer' => 'domain', 'label' => 'Sitios',         'depends_on' => []],
        'Billing' => ['layer' => 'domain', 'label' => 'Planes y cobro', 'depends_on' => []],
        'Builder' => ['layer' => 'domain', 'label' => 'Constructor',    'depends_on' => ['Sites']],
        'Content' => ['layer' => 'domain', 'label' => 'Contenido',      'depends_on' => ['Sites', 'Builder']],
        'Media' => ['layer' => 'domain', 'label' => 'Medios',         'depends_on' => ['Sites']],
        'Seo' => ['layer' => 'domain', 'label' => 'SEO',            'depends_on' => ['Sites']],
        'Navigation' => ['layer' => 'domain', 'label' => 'Navegación',     'depends_on' => ['Sites', 'Builder', 'Content']],
        'Publishing' => ['layer' => 'domain', 'label' => 'Publicación',     'depends_on' => ['Sites', 'Builder', 'Content', 'Seo']],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rutas de API
    |--------------------------------------------------------------------------
    |
    | Prefijo versionado desde el día uno. El cambio a v2 será aditivo: se registra
    | un segundo prefijo, nunca se muta este.
    |
    */

    'api' => [
        'prefix' => 'api/v1',
        'name_prefix' => 'api.v1.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Media (ADR-016)
    |--------------------------------------------------------------------------
    |
    | Disco del Filesystem donde viven los binarios (local en dev, S3-compat en
    | prod). Límite de tamaño y mimes permitidos para la subida. Tamaños de las
    | variantes de imagen (no agrandan).
    |
    */

    'media' => [
        'disk' => env('MEDIA_DISK', 'public'),
        'max_kb' => (int) env('MEDIA_MAX_KB', 10240),
        'mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf'],
        'variants' => [
            'thumb' => 320,
            'medium' => 768,
            'large' => 1440,
        ],
    ],

];
