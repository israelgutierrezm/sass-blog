<?php

declare(strict_types=1);

use App\Modules\Seo\Infrastructure\Models\Redirect;
use Database\Seeders\DatabaseSeeder;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('un redirect activo se resuelve antes del 404', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    withinWorkspace($ws, fn () => Redirect::factory()->create([
        'site_id' => $site->id,
        'from_path' => '/viejo',
        'to_path' => '/nuevo',
        'status' => 301,
        'is_active' => true,
    ]));

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/viejo")
        ->assertOk()
        ->assertJsonPath('data.redirect.to', '/nuevo')
        ->assertJsonPath('data.redirect.status', 301);
});

it('un redirect inactivo no se aplica (404)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    withinWorkspace($ws, fn () => Redirect::factory()->create([
        'site_id' => $site->id,
        'from_path' => '/viejo',
        'to_path' => '/nuevo',
        'is_active' => false,
    ]));

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/viejo")->assertNotFound();
});

it('sigue la cadena y colapsa a un solo salto al destino final', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    withinWorkspace($ws, function () use ($site) {
        Redirect::factory()->create(['site_id' => $site->id, 'from_path' => '/a', 'to_path' => '/b']);
        Redirect::factory()->create(['site_id' => $site->id, 'from_path' => '/b', 'to_path' => '/c']);
    });

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/a")
        ->assertOk()
        ->assertJsonPath('data.redirect.to', '/c');
});

it('una cadena cíclica no redirige (404, sin bucle infinito)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    withinWorkspace($ws, function () use ($site) {
        Redirect::factory()->create(['site_id' => $site->id, 'from_path' => '/x', 'to_path' => '/y']);
        Redirect::factory()->create(['site_id' => $site->id, 'from_path' => '/y', 'to_path' => '/x']);
    });

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/x")->assertNotFound();
});

it('una página publicada gana sobre un redirect con el mismo path', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    // La página estática se resuelve primero (orden del render): el redirect queda sombreado.
    publishedPage($ws, $site, '/gana', 'Contenido real');
    withinWorkspace($ws, fn () => Redirect::factory()->create([
        'site_id' => $site->id,
        'from_path' => '/gana',
        'to_path' => '/otra',
        'is_active' => true,
    ]));

    $this->getJson("/api/v1/public/sites/{$site->ulid}/render?path=/gana")
        ->assertOk()
        ->assertJsonPath('data.page.path', '/gana')
        ->assertJsonMissingPath('data.redirect');
});
