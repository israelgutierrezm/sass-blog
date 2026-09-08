<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Tenancy;

use App\Modules\Shared\Domain\Tenancy\Exceptions\MissingWorkspaceContextException;
use Closure;

/**
 * Portador del workspace activo durante una unidad de trabajo.
 *
 * Único lugar del sistema que sabe "en qué workspace estamos". Lo escribe el
 * middleware de resolución de contexto en HTTP, y {@see self::runFor()} fuera de
 * HTTP (jobs, comandos, scheduler, pruebas).
 *
 * Registrado como singleton: un request, un workspace.
 *
 * NO conoce el modelo Workspace a propósito: guarda un identificador. Así el
 * kernel no depende de la persistencia del módulo Tenancy, y el global scope
 * puede aplicarse sin cargar una fila de más en cada consulta.
 *
 * El mecanismo onChange existe porque cambiar de workspace no es sólo cambiar un
 * entero: Spatie carga su cache de permisos bajo una llave por "team", y esa
 * llave debe cambiar junto con el contexto, siempre, sin depender de que alguien
 * recuerde llamar a dos métodos en orden.
 */
final class WorkspaceContext
{
    private ?int $workspaceId = null;

    /**
     * @var list<Closure(int|null): void>
     */
    private array $listeners = [];

    /**
     * ¿Hay workspace resuelto? Sólo para infraestructura (middleware).
     * El código de dominio no pregunta: si necesita el workspace, lo pide.
     */
    public function has(): bool
    {
        return $this->workspaceId !== null;
    }

    /**
     * @throws MissingWorkspaceContextException si no hay contexto resuelto
     */
    public function id(): int
    {
        if ($this->workspaceId === null) {
            throw MissingWorkspaceContextException::make();
        }

        return $this->workspaceId;
    }

    /**
     * El workspace activo, o null. Reservado a infraestructura.
     */
    public function idOrNull(): ?int
    {
        return $this->workspaceId;
    }

    /**
     * Registra infraestructura que debe reaccionar al cambio de workspace.
     *
     * @param  Closure(int|null): void  $listener
     */
    public function onChange(Closure $listener): void
    {
        $this->listeners[] = $listener;

        // Se invoca de inmediato para que un oyente registrado después de que el
        // contexto ya estuviera resuelto no arranque desincronizado.
        $listener($this->workspaceId);
    }

    public function set(int $workspaceId): void
    {
        $this->workspaceId = $workspaceId;
        $this->notify();
    }

    public function forget(): void
    {
        $this->workspaceId = null;
        $this->notify();
    }

    /**
     * Ejecuta el callback dentro del contexto de un workspace y restaura el previo.
     *
     * Único camino permitido para tocar el dominio fuera de HTTP. Restaura el
     * contexto previo incluso si el callback lanza: un job que falla a media cola
     * no puede dejar el contexto de otro workspace abierto para el siguiente.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runFor(int $workspaceId, Closure $callback): mixed
    {
        $previous = $this->workspaceId;

        $this->workspaceId = $workspaceId;
        $this->notify();

        try {
            return $callback();
        } finally {
            $this->workspaceId = $previous;
            $this->notify();
        }
    }

    /**
     * Ejecuta el callback sin ningún workspace activo (super admin, o pruebas que
     * verifican que el dominio falla ruidosamente sin contexto).
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runWithout(Closure $callback): mixed
    {
        $previous = $this->workspaceId;

        $this->workspaceId = null;
        $this->notify();

        try {
            return $callback();
        } finally {
            $this->workspaceId = $previous;
            $this->notify();
        }
    }

    private function notify(): void
    {
        foreach ($this->listeners as $listener) {
            $listener($this->workspaceId);
        }
    }
}
