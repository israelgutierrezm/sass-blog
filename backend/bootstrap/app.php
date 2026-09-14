<?php

use App\Modules\Content\Domain\Exceptions\InvalidEntryTransitionException;
use App\Modules\Shared\Domain\Capabilities\Exceptions\CapabilityDeniedException;
use App\Modules\Shared\Http\Middleware\EnsureCapability;
use App\Modules\Shared\Http\Middleware\ResolveWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // Resuelve el workspace de la ruta, valida la membresía y fija el contexto.
            'workspace' => ResolveWorkspace::class,
            // Exige una capability del plan (corre DESPUÉS de 'workspace'). ADR-014.
            'capability' => EnsureCapability::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // El plan no incluye la funcionalidad -> 403 (no 500). ADR-014.
        $exceptions->render(function (CapabilityDeniedException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 403);
            }

            return null;
        });

        // Transición editorial inválida -> 422 (no 500). ADR-023.
        $exceptions->render(function (InvalidEntryTransitionException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return null;
        });
    })->create();
