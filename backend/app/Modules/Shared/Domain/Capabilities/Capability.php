<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Capabilities;

/**
 * Catálogo CERRADO de capabilities del plan.
 *
 * Responde "¿el plan contratado permite la funcionalidad?", NUNCA "¿el usuario
 * puede la acción?" (eso es RBAC). Este enum es la fuente de verdad; el seeder
 * CapabilitySeeder refleja estos valores en la tabla `capabilities`.
 *
 * Agregar una capability = agregar un caso aquí y correr el seeder. Prohibido
 * inventar strings sueltos por el código.
 */
enum Capability: string
{
    case SiteExportStatic = 'site.export.static';
    case SiteCustomDomain = 'site.custom_domain';
    case SiteMultilanguage = 'site.multilanguage';
    case CmsCollections = 'cms.collections';
    case CmsAdvancedWorkflow = 'cms.advanced_workflow';
    case BuilderCustomCode = 'builder.custom_code';
    case PublisherEditorial = 'publisher.editorial';
    case PublisherFrontpages = 'publisher.frontpages';
    case PublisherPaywall = 'publisher.paywall';
    case AnalyticsAdvanced = 'analytics.advanced';
    case AgencyWhiteLabel = 'agency.white_label';
    case ApiHeadless = 'api.headless';

    /**
     * Etiqueta en español para la interfaz.
     */
    public function label(): string
    {
        return match ($this) {
            self::SiteExportStatic => 'Exportación estática',
            self::SiteCustomDomain => 'Dominio propio',
            self::SiteMultilanguage => 'Multilenguaje',
            self::CmsCollections => 'Colecciones dinámicas',
            self::CmsAdvancedWorkflow => 'Flujo editorial avanzado',
            self::BuilderCustomCode => 'Código personalizado',
            self::PublisherEditorial => 'Suite editorial',
            self::PublisherFrontpages => 'Editor de portadas',
            self::PublisherPaywall => 'Muro de pago',
            self::AnalyticsAdvanced => 'Analítica avanzada',
            self::AgencyWhiteLabel => 'Marca blanca (agencia)',
            self::ApiHeadless => 'API headless',
        };
    }
}
