<?php

declare(strict_types=1);

use App\Modules\Newsletter\Application\SubscribeToNewsletter;
use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use App\Modules\Newsletter\Mail\ConfirmationMail;
use App\Modules\Sites\Infrastructure\Models\Site;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

beforeEach(fn () => Mail::fake());

function subscribeUrl(string $siteUlid): string
{
    return "/api/v1/public/sites/{$siteUlid}/newsletter/subscribe";
}

/** Da de alta `$email` (pending) por la API pública y devuelve su token de confirmación. */
function pendingToken(TestCase $test, Workspace $ws, Site $site, string $email = 'x@example.com'): string
{
    $test->postJson(subscribeUrl($site->ulid), ['email' => $email])->assertNoContent();

    return withinWorkspace($ws, fn () => Subscriber::forSite($site->id)->where('email', $email)->sole()->confirmation_token);
}

it('suscribe (204) crea un pending y ENCOLA la confirmación', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();

    $this->postJson(subscribeUrl($site->ulid), ['email' => 'Lector@Example.com'])->assertNoContent();

    withinWorkspace($ws, function () use ($site) {
        $s = Subscriber::forSite($site->id)->sole();
        expect($s->email)->toBe('lector@example.com')          // normalizado a minúsculas
            ->and($s->status)->toBe(Subscriber::STATUS_PENDING)
            ->and($s->confirmation_token)->not->toBeEmpty();
    });

    Mail::assertQueued(ConfirmationMail::class);
});

it('sin JS (POST de formulario) responde una página HTML con enlace de vuelta seguro', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();

    $this->post(subscribeUrl($site->ulid), ['email' => 'form@example.com'], ['Referer' => 'https://blog.acme.test/portada'])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('Revisa tu correo')
        ->assertSee('href="https://blog.acme.test/portada"', false);

    // Un Referer con otro esquema (javascript:) nunca se ofrece como enlace.
    $this->post(subscribeUrl($site->ulid), ['email' => 'otro@example.com'], ['Referer' => 'javascript:alert(1)'])
        ->assertOk()
        ->assertDontSee('javascript:', false);

    withinWorkspace($ws, fn () => expect(Subscriber::forSite($site->id)->count())->toBe(2));
});

it('el GET del enlace de confirmación sólo muestra la página: NO confirma (escáneres de correo)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $token = pendingToken($this, $ws, $site);

    $this->get("/api/v1/public/newsletter/confirm?token={$token}")
        ->assertOk()
        ->assertSee('Confirmar suscripción')
        ->assertSee('method="post"', false);

    withinWorkspace($ws, fn () => expect(Subscriber::forSite($site->id)->sole()->status)->toBe(Subscriber::STATUS_PENDING));
});

it('confirma por POST (idempotente)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $token = pendingToken($this, $ws, $site);

    $this->post("/api/v1/public/newsletter/confirm?token={$token}")->assertOk()->assertSee('confirmada');
    $this->post("/api/v1/public/newsletter/confirm?token={$token}")->assertOk(); // idempotente

    withinWorkspace($ws, fn () => expect(Subscriber::forSite($site->id)->sole()->isConfirmed())->toBeTrue());
});

it('un token de confirmación inválido da 404 (GET y POST)', function () {
    $this->get('/api/v1/public/newsletter/confirm?token=noexiste')->assertNotFound();
    $this->post('/api/v1/public/newsletter/confirm?token=noexiste')->assertNotFound();
    $this->post('/api/v1/public/newsletter/confirm')->assertNotFound();
});

it('el GET de baja no da de baja; el POST sí (también el one-click de RFC 8058)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $sub = withinWorkspace($ws, fn () => Subscriber::factory()->confirmed()->create(['site_id' => $site->id]));
    $url = "/api/v1/public/newsletter/unsubscribe?token={$sub->unsubscribe_token}";

    $this->get($url)->assertOk()->assertSee('Darme de baja');
    withinWorkspace($ws, fn () => expect(Subscriber::forSite($site->id)->sole()->status)->toBe(Subscriber::STATUS_CONFIRMED));

    // Lo que manda el cliente de correo con `List-Unsubscribe-Post: List-Unsubscribe=One-Click`.
    $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk()->assertSee('Te has dado de baja');
    withinWorkspace($ws, fn () => expect(Subscriber::forSite($site->id)->sole()->status)->toBe(Subscriber::STATUS_UNSUBSCRIBED));

    $this->post($url)->assertOk(); // idempotente
});

it('tras darse de baja, el enlace de confirmación viejo ya no reactiva la suscripción', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $token = pendingToken($this, $ws, $site);
    $this->post("/api/v1/public/newsletter/confirm?token={$token}")->assertOk();
    $unsubscribeToken = withinWorkspace($ws, fn () => Subscriber::forSite($site->id)->sole()->unsubscribe_token);
    $this->post("/api/v1/public/newsletter/unsubscribe?token={$unsubscribeToken}")->assertOk();

    $this->get("/api/v1/public/newsletter/confirm?token={$token}")->assertNotFound();
    $this->post("/api/v1/public/newsletter/confirm?token={$token}")->assertNotFound();

    withinWorkspace($ws, fn () => expect(Subscriber::forSite($site->id)->sole()->status)->toBe(Subscriber::STATUS_UNSUBSCRIBED));
});

it('suscribir dos veces el mismo email no duplica', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();

    $this->postJson(subscribeUrl($site->ulid), ['email' => 'dup@example.com'])->assertNoContent();
    $this->postJson(subscribeUrl($site->ulid), ['email' => 'dup@example.com'])->assertNoContent();

    withinWorkspace($ws, fn () => expect(Subscriber::forSite($site->id)->where('email', 'dup@example.com')->count())->toBe(1));
});

it('un email ya confirmado no reenvía la confirmación', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    withinWorkspace($ws, fn () => Subscriber::factory()->confirmed()->create(['site_id' => $site->id, 'email' => 'ya@example.com']));

    $this->postJson(subscribeUrl($site->ulid), ['email' => 'ya@example.com'])->assertNoContent();

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

it('tope por destinatario: la misma dirección no recibe más de N confirmaciones por hora, aunque sean sitios distintos', function () {
    ['site' => $siteA] = ownerWithSite('a@example.com');
    ['site' => $siteB] = ownerWithSite('b@example.com');

    foreach ([$siteA, $siteB, $siteA, $siteB, $siteA] as $site) {
        // La respuesta es siempre la misma: no delata el tope ni si la dirección existe.
        $this->postJson(subscribeUrl($site->ulid), ['email' => 'victima@example.com'])->assertNoContent();
    }

    Mail::assertQueued(ConfirmationMail::class, SubscribeToNewsletter::PER_RECIPIENT_PER_HOUR);
});

it('sitio inexistente → 404', function () {
    $this->postJson(subscribeUrl(Str::upper((string) Str::ulid())), ['email' => 'a@example.com'])->assertNotFound();
});

it('email inválido → 422', function () {
    ['site' => $site] = ownerWithSite();
    $this->postJson(subscribeUrl($site->ulid), ['email' => 'no-es-email'])->assertStatus(422);
});
