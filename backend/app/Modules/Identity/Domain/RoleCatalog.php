<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Catálogo CERRADO de roles y permisos de workspace (RBAC).
 *
 * Fuente única de verdad usada por el seeder y por el provisionador de RBAC de
 * cada workspace. El tenant combina permisos en roles a través de este catálogo;
 * no inventa permisos sueltos.
 */
final class RoleCatalog
{
    /**
     * Rol => permisos. El rol activo (no la suma) es el que autoriza (teams=workspace).
     *
     * @var array<string, list<string>>
     */
    public const ROLES = [
        'owner' => ['workspace.view', 'workspace.update', 'member.manage', 'site.view', 'site.create', 'site.update', 'site.delete', 'site.publish', 'page.view', 'page.create', 'page.update', 'page.publish', 'category.manage', 'author.manage', 'collection.view', 'collection.create', 'collection.update', 'entry.view', 'entry.create', 'entry.update', 'entry.publish', 'media.view', 'media.manage', 'redirect.manage', 'menu.manage', 'domain.manage', 'analytics.view'],
        'admin' => ['workspace.view', 'member.manage', 'site.view', 'site.create', 'site.update', 'site.delete', 'site.publish', 'page.view', 'page.create', 'page.update', 'page.publish', 'category.manage', 'author.manage', 'collection.view', 'collection.create', 'collection.update', 'entry.view', 'entry.create', 'entry.update', 'entry.publish', 'media.view', 'media.manage', 'redirect.manage', 'menu.manage', 'domain.manage', 'analytics.view'],
        'editor' => ['workspace.view', 'site.view', 'site.create', 'site.update', 'page.view', 'page.create', 'page.update', 'category.manage', 'author.manage', 'collection.view', 'entry.view', 'entry.create', 'entry.update', 'media.view', 'media.manage', 'menu.manage', 'analytics.view'],
        'viewer' => ['workspace.view', 'site.view', 'page.view', 'collection.view', 'entry.view', 'media.view'],
    ];

    /**
     * Todos los permisos del catálogo, sin repetir.
     *
     * @return list<string>
     */
    public static function permissions(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::ROLES))));
    }

    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return array_keys(self::ROLES);
    }
}
