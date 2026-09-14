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
        'Domains' => ['layer' => 'domain', 'label' => 'Dominios',       'depends_on' => ['Sites']],
        'Analytics' => ['layer' => 'domain', 'label' => 'Analítica',       'depends_on' => ['Sites']],
        'Newsletter' => ['layer' => 'domain', 'label' => 'Newsletter',      'depends_on' => ['Sites']],
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

    /*
    |--------------------------------------------------------------------------
    | Publishing / export estático (ADR-019)
    |--------------------------------------------------------------------------
    |
    | Disco donde se guardan los artefactos ZIP; binario de Node y rutas del CLI de
    | render estático (bundle + CSS extraído) que produce el build de site-schema.
    |
    */

    'publishing' => [
        'disk' => env('PUBLISHING_DISK', 'local'),
        'node' => env('NODE_BIN', 'node'),
        'cli' => env('PUBLISHING_CLI', base_path('../renderer/static/dist/render-static.mjs')),
        'css' => env('PUBLISHING_CSS', base_path('../renderer/static/dist/render-static.css')),
        'timeout' => (int) env('PUBLISHING_TIMEOUT', 180),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dominios propios (ADR-020)
    |--------------------------------------------------------------------------
    |
    | Objetivos de apuntado hacia nuestro ingress: un dominio se verifica cuando su
    | DNS resuelve al CNAME (subdominios) o a la IP (apex) del ingress. La verificación
    | acepta cualquiera de estos valores.
    |
    */

    'domains' => [
        'ingress_cname' => env('DOMAINS_INGRESS_CNAME', 'ingress.sassblog.com'),
        'ingress_ip' => env('DOMAINS_INGRESS_IP', ''),
        // Afordance de NO producción (E2E/staging): trata cualquier dominio como apuntado
        // al ingress → la verificación queda en verde sin DNS real. Prod: false.
        'auto_verify' => (bool) env('DOMAINS_AUTO_VERIFY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Analítica (ADR-021)
    |--------------------------------------------------------------------------
    |
    | Captura server-side de pageviews privacy-first. `retention_days`: poda del raw
    | (`analytics_events`); los rollups diarios son permanentes. `bot_pattern`: regex
    | (sin delimitadores) sobre el User-Agent para descartar bots. `basic_range_days`:
    | ventana visible sin `analytics.advanced` (Pro amplía el rango + referrers + export).
    |
    */

    'analytics' => [
        'enabled' => (bool) env('ANALYTICS_ENABLED', true),
        'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 90),
        'basic_range_days' => (int) env('ANALYTICS_BASIC_RANGE_DAYS', 30),
        'bot_pattern' => env('ANALYTICS_BOT_PATTERN', 'bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|quora link preview|pinterest|vkshare|w3c_validator|curl|wget|python-requests|axios|headlesschrome|lighthouse|monitor|uptime'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Newsletter (ADR-022)
    |--------------------------------------------------------------------------
    |
    | `send_batch`: cuántos suscriptores procesa por lote el job de envío. El
    | transporte de correo se configura en config/mail.php (abstracción de Laravel;
    | array/log en dev, proveedor real en prod).
    |
    */

    'newsletter' => [
        'send_batch' => (int) env('NEWSLETTER_SEND_BATCH', 100),
    ],

];
