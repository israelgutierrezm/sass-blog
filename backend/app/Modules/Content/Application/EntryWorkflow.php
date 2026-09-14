<?php

declare(strict_types=1);

namespace App\Modules\Content\Application;

use App\Modules\Content\Domain\Exceptions\InvalidEntryTransitionException;
use App\Modules\Content\Events\EntryPublished;
use App\Modules\Content\Infrastructure\Models\Entry;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Máquina de estados del flujo editorial (ADR-023). Valida cada transición en el dominio y las
 * ejecuta. Requiere contexto de workspace activo. `EntryPublished` se emite al IR EN VIVO
 * (aprobar-ahora aquí; el job lo emite al vencer una programada), nunca al programar.
 */
final class EntryWorkflow
{
    /** draft → in_review (redactor). */
    public function submitForReview(Entry $entry, ?int $by = null): Entry
    {
        $this->assert($entry, 'enviar a revisión', Entry::STATUS_DRAFT);

        $entry->status = Entry::STATUS_IN_REVIEW;
        $entry->editorial_note = null;
        $entry->updated_by = $by;
        $entry->save();

        return $entry->refresh();
    }

    /** in_review → draft (redactor retira). */
    public function withdraw(Entry $entry, ?int $by = null): Entry
    {
        $this->assert($entry, 'retirar de revisión', Entry::STATUS_IN_REVIEW);

        $entry->status = Entry::STATUS_DRAFT;
        $entry->updated_by = $by;
        $entry->save();

        return $entry->refresh();
    }

    /** in_review → draft con nota (editor pide cambios). */
    public function requestChanges(Entry $entry, string $note, ?int $by = null): Entry
    {
        $this->assert($entry, 'pedir cambios', Entry::STATUS_IN_REVIEW);

        $entry->status = Entry::STATUS_DRAFT;
        $entry->editorial_note = $note;
        $entry->updated_by = $by;
        $entry->save();

        return $entry->refresh();
    }

    /**
     * in_review → published (fecha nula/pasada) o scheduled (fecha futura). Editor aprueba.
     */
    public function approve(Entry $entry, ?DateTimeInterface $publishAt = null, ?int $by = null): Entry
    {
        $this->assert($entry, 'aprobar', Entry::STATUS_IN_REVIEW);

        $when = $publishAt !== null ? Carbon::instance($publishAt) : Carbon::now();

        return DB::transaction(function () use ($entry, $when, $by): Entry {
            $scheduled = $when->isFuture();

            $entry->status = $scheduled ? Entry::STATUS_SCHEDULED : Entry::STATUS_PUBLISHED;
            $entry->published_at = $when;
            $entry->editorial_note = null;
            $entry->updated_by = $by;
            $entry->save();

            if (! $scheduled) {
                event(new EntryPublished($entry->workspace_id, $entry->site_id, $entry->collection_id, $entry->id, $by));
            }

            return $entry->refresh();
        });
    }

    private function assert(Entry $entry, string $action, string ...$allowed): void
    {
        if (! in_array($entry->status, $allowed, true)) {
            throw InvalidEntryTransitionException::from($entry->status, $action);
        }
    }
}
