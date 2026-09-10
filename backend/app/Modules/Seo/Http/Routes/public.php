<?php

declare(strict_types=1);

use App\Modules\Seo\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
 * Superficie PÚBLICA de SEO por sitio (ADR-018). SIN auth:sanctum ni 'workspace'
 * (frontera de confianza, ADR-006): el workspace se deriva del sitio en el servidor.
 * El renderer las sirve en la raíz del dominio (/sitemap.xml, /robots.txt).
 */
Route::get('public/sites/{site}/sitemap.xml', [SitemapController::class, 'sitemap'])
    ->name('public.sitemap');

Route::get('public/sites/{site}/robots.txt', [SitemapController::class, 'robots'])
    ->name('public.robots');
