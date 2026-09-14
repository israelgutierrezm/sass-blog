<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Newsletter\Application\ConfirmSubscription;
use App\Modules\Newsletter\Application\SubscribeToNewsletter;
use App\Modules\Newsletter\Application\Unsubscribe;
use App\Modules\Newsletter\Http\Requests\SubscribeRequest;
use App\Modules\Sites\Infrastructure\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Superficie PÚBLICA de newsletter (ADR-006/ADR-022). SIN auth: el sitio se resuelve en el
 * servidor. `subscribe` lo llama el formulario del sitio (204 siempre, no filtra existencia);
 * `confirm`/`unsubscribe` los abre el suscriptor desde un correo (devuelven una página HTML).
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

        return response()->noContent();
    }

    public function confirm(Request $request, ConfirmSubscription $confirm): Response
    {
        abort_unless($confirm->handle((string) $request->query('token', '')), 404, 'Enlace no válido.');

        return $this->page('Suscripción confirmada', '¡Listo! Tu suscripción está confirmada.');
    }

    public function unsubscribe(Request $request, Unsubscribe $unsubscribe): Response
    {
        abort_unless($unsubscribe->handle((string) $request->query('token', '')), 404, 'Enlace no válido.');

        return $this->page('Baja completada', 'Te has dado de baja. No recibirás más correos de esta newsletter.');
    }

    private function page(string $title, string $message): Response
    {
        $html = '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1"><title>'.e($title).'</title></head>'
            .'<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1rem;color:#111">'
            .'<h1>'.e($title).'</h1><p>'.e($message).'</p></body></html>';

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
