<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Newsletter\Application\ConfirmSubscription;
use App\Modules\Newsletter\Application\SubscribeToNewsletter;
use App\Modules\Newsletter\Application\Unsubscribe;
use App\Modules\Newsletter\Http\Requests\SubscribeRequest;
use App\Modules\Newsletter\Infrastructure\Models\Subscriber;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Superficie PÚBLICA de newsletter (ADR-006/ADR-022). SIN auth: el sitio se resuelve en el
 * servidor.
 *
 * - `subscribe` lo llama el formulario del sitio: con JS (fetch) responde 204; sin JS (export
 *   estático o envío antes de hidratar) devuelve una página de "revisa tu correo".
 * - `confirm` / `unsubscribe`: el GET sólo MUESTRA una página con un botón; la acción va por
 *   POST. Así los escáneres de enlaces del correo (que hacen GET al recibirlo) no confirman ni
 *   dan de baja a nadie. El POST de baja también atiende el one-click de RFC 8058.
 */
final class PublicNewsletterController extends Controller
{
    public function subscribe(SubscribeRequest $request, string $site, SubscribeToNewsletter $subscribe): Response
    {
        $siteModel = Site::withoutGlobalScopes()
            ->where('ulid', Str::upper($site))
            ->where('status', '!=', Site::STATUS_ARCHIVED)
            ->first();

        abort_if($siteModel === null, 404, 'Sitio no encontrado.');

        $subscribe->handle($siteModel, (string) $request->input('email'));

        if (! $request->expectsJson()) {
            return $this->page(
                'Revisa tu correo',
                'Si la dirección es válida, te llegará un enlace para confirmar la suscripción a «'.$siteModel->name.'».',
                back: $this->backUrl($request),
            );
        }

        return response()->noContent();
    }

    public function showConfirm(Request $request, ConfirmSubscription $confirm): Response
    {
        $subscriber = $confirm->find((string) $request->query('token', ''));

        if ($subscriber === null || $subscriber->status === Subscriber::STATUS_UNSUBSCRIBED) {
            return $this->invalidLink();
        }

        $siteName = $this->siteName($subscriber);

        if ($subscriber->isConfirmed()) {
            return $this->page('Suscripción confirmada', "Tu suscripción a «{$siteName}» ya está confirmada.");
        }

        return $this->page(
            'Confirma tu suscripción',
            "Pulsa el botón para confirmar tu suscripción a «{$siteName}».",
            form: ['action' => $request->fullUrl(), 'label' => 'Confirmar suscripción'],
        );
    }

    public function confirm(Request $request, ConfirmSubscription $confirm): Response
    {
        if (! $confirm->handle((string) $request->query('token', ''))) {
            return $this->invalidLink();
        }

        return $this->page('Suscripción confirmada', '¡Listo! Tu suscripción está confirmada.');
    }

    public function showUnsubscribe(Request $request, Unsubscribe $unsubscribe): Response
    {
        $subscriber = $unsubscribe->find((string) $request->query('token', ''));

        if ($subscriber === null) {
            return $this->invalidLink();
        }

        $siteName = $this->siteName($subscriber);

        if ($subscriber->status === Subscriber::STATUS_UNSUBSCRIBED) {
            return $this->page('Baja completada', "Ya no recibes los correos de «{$siteName}».");
        }

        return $this->page(
            'Darse de baja',
            "¿Quieres dejar de recibir los correos de «{$siteName}»?",
            form: ['action' => $request->fullUrl(), 'label' => 'Darme de baja'],
        );
    }

    /** POST del formulario, o one-click del cliente de correo (RFC 8058, `List-Unsubscribe-Post`). */
    public function unsubscribe(Request $request, Unsubscribe $unsubscribe): Response
    {
        if (! $unsubscribe->handle((string) $request->query('token', ''))) {
            return $this->invalidLink();
        }

        return $this->page('Baja completada', 'Te has dado de baja. No recibirás más correos de esta newsletter.');
    }

    private function siteName(Subscriber $subscriber): string
    {
        return (string) (Site::withoutGlobalScopes()->whereKey($subscriber->site_id)->value('name') ?? 'esta newsletter');
    }

    private function invalidLink(): Response
    {
        return $this->page('Enlace no válido', 'Este enlace no es válido o ya no está vigente.', status: 404);
    }

    /** Sólo se ofrece volver a una URL http(s) (nunca `javascript:` u otros esquemas). */
    private function backUrl(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer', '');

        return preg_match('#^https?://#i', $referer) === 1 ? $referer : null;
    }

    /**
     * @param  array{action: string, label: string}|null  $form  botón que hace POST a `action`
     */
    private function page(string $title, string $message, ?array $form = null, ?string $back = null, int $status = 200): Response
    {
        $html = '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<meta name="robots" content="noindex"><title>'.e($title).'</title></head>'
            .'<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem;color:#111">'
            .'<h1>'.e($title).'</h1><p>'.e($message).'</p>';

        if ($form !== null) {
            $html .= '<form method="post" action="'.e($form['action']).'">'
                .'<button type="submit" style="padding:.6rem 1.2rem;border:0;border-radius:.375rem;'
                .'background:#2563eb;color:#fff;font:inherit;font-weight:600;cursor:pointer">'
                .e($form['label']).'</button></form>';
        }

        if ($back !== null) {
            $html .= '<p><a href="'.e($back).'">Volver al sitio</a></p>';
        }

        return response($html.'</body></html>', $status)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
