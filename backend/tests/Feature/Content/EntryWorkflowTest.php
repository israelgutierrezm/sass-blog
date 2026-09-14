<?php

declare(strict_types=1);

use App\Modules\Content\Application\EntryWorkflow;
use App\Modules\Content\Domain\Exceptions\InvalidEntryTransitionException;
use App\Modules\Content\Events\EntryPublished;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Support\Facades\Event;

/**
 * @return array{0: Workspace, 1: Entry}
 */
function makeEntry(string $status = Entry::STATUS_DRAFT): array
{
    [$ws, $site] = builderSite();
    $entry = withinWorkspace($ws, function () use ($site, $status) {
        $collection = Collection::factory()->create(['site_id' => $site->id]);

        return Entry::factory()->create(['site_id' => $site->id, 'collection_id' => $collection->id, 'status' => $status]);
    });

    return [$ws, $entry];
}

it('enviar a revisión: draft → in_review', function () {
    [$ws, $entry] = makeEntry();

    withinWorkspace($ws, function () use ($entry) {
        expect(app(EntryWorkflow::class)->submitForReview($entry)->status)->toBe(Entry::STATUS_IN_REVIEW);
    });
});

it('rechaza enviar a revisión si no es un borrador', function () {
    [$ws, $entry] = makeEntry(Entry::STATUS_PUBLISHED);

    withinWorkspace($ws, function () use ($entry) {
        expect(fn () => app(EntryWorkflow::class)->submitForReview($entry))->toThrow(InvalidEntryTransitionException::class);
    });
});

it('aprobar ahora: in_review → published y emite EntryPublished', function () {
    Event::fake([EntryPublished::class]);
    [$ws, $entry] = makeEntry(Entry::STATUS_IN_REVIEW);

    withinWorkspace($ws, function () use ($entry) {
        $r = app(EntryWorkflow::class)->approve($entry, null);
        expect($r->status)->toBe(Entry::STATUS_PUBLISHED)->and($r->published_at)->not->toBeNull();
    });

    Event::assertDispatched(EntryPublished::class);
});

it('aprobar con fecha futura: in_review → scheduled y NO emite evento', function () {
    Event::fake([EntryPublished::class]);
    [$ws, $entry] = makeEntry(Entry::STATUS_IN_REVIEW);

    withinWorkspace($ws, function () use ($entry) {
        $r = app(EntryWorkflow::class)->approve($entry, now()->addDay());
        expect($r->status)->toBe(Entry::STATUS_SCHEDULED);
    });

    Event::assertNotDispatched(EntryPublished::class);
});

it('pedir cambios: in_review → draft con nota', function () {
    [$ws, $entry] = makeEntry(Entry::STATUS_IN_REVIEW);

    withinWorkspace($ws, function () use ($entry) {
        $r = app(EntryWorkflow::class)->requestChanges($entry, 'Faltan fuentes');
        expect($r->status)->toBe(Entry::STATUS_DRAFT)->and($r->editorial_note)->toBe('Faltan fuentes');
    });
});

it('retirar de revisión: in_review → draft', function () {
    [$ws, $entry] = makeEntry(Entry::STATUS_IN_REVIEW);

    withinWorkspace($ws, function () use ($entry) {
        expect(app(EntryWorkflow::class)->withdraw($entry)->status)->toBe(Entry::STATUS_DRAFT);
    });
});

it('rechaza aprobar algo que no está en revisión', function () {
    [$ws, $entry] = makeEntry(Entry::STATUS_DRAFT);

    withinWorkspace($ws, function () use ($entry) {
        expect(fn () => app(EntryWorkflow::class)->approve($entry))->toThrow(InvalidEntryTransitionException::class);
    });
});
