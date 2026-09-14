<?php

declare(strict_types=1);

use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use App\Modules\Newsletter\Mail\ConfirmationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(fn () => Mail::fake());

function subscribeUrl(string $siteUlid): string
{
    return "/api/v1/public/sites/{$siteUlid}/newsletter/subscribe";
}

it('suscribe (204) crea un pending y envía la confirmación', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();

    $this->postJson(subscribeUrl($site->ulid), ['email' => 'Lector@Example.com'])->assertNoContent();

    withinWorkspace($ws, function () use ($site) {
        $s = Subscriber::forSite($site->id)->sole();
        expect($s->email)->toBe('lector@example.com')          // normalizado a minúsculas
            ->and($s->status)->toBe(Subscriber::STATUS_PENDING)
            ->and($s->confirmation_token)->not->toBeEmpty();
    });

    Mail::assertSent(ConfirmationMail::class);
});

it('confirma la suscripción por token (idempotente)', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $this->postJson(subscribeUrl($site->ulid), ['email' => 'x@example.com'])->assertNoContent();
    $token = withinWorkspace($ws, fn () => Subscriber::forSite($site->id)->sole()->confirmation_token);

    $this->get("/api/v1/public/newsletter/confirm?token={$token}")->assertOk();
    $this->get("/api/v1/public/newsletter/confirm?token={$token}")->assertOk(); // idempotente

    withinWorkspace($ws, fn () => expect(Subscriber::forSite($site->id)->sole()->isConfirmed())->toBeTrue());
});

it('un token de confirmación inválido da 404', function () {
    $this->get('/api/v1/public/newsletter/confirm?token=noexiste')->assertNotFound();
});

it('da de baja por token', function () {
    ['ws' => $ws, 'site' => $site] = ownerWithSite();
    $sub = withinWorkspace($ws, fn () => Subscriber::factory()->confirmed()->create(['site_id' => $site->id]));

    $this->get("/api/v1/public/newsletter/unsubscribe?token={$sub->unsubscribe_token}")->assertOk();

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

    Mail::assertNothingSent();
});

it('sitio inexistente → 404', function () {
    $this->postJson(subscribeUrl(Str::upper((string) Str::ulid())), ['email' => 'a@example.com'])->assertNotFound();
});

it('email inválido → 422', function () {
    ['site' => $site] = ownerWithSite();
    $this->postJson(subscribeUrl($site->ulid), ['email' => 'no-es-email'])->assertStatus(422);
});
