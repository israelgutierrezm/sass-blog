<?php

declare(strict_types=1);

use App\Modules\Content\Application\Jobs\PublishScheduledEntries;
use App\Modules\Content\Events\EntryPublished;
use App\Modules\Content\Infrastructure\Models\Collection;
use App\Modules\Content\Infrastructure\Models\Entry;
use App\Modules\Tenancy\Infrastructure\Models\Workspace;
use Illuminate\Support\Facades\Event;

/**
 * @return array{0: Workspace, 1: Entry}
 */
function makeScheduled(DateTimeInterface $when): array
{
    [$ws, $site] = builderSite();
    $entry = withinWorkspace($ws, function () use ($site, $when) {
        $col = Collection::factory()->create(['site_id' => $site->id]);

        return Entry::factory()->scheduled($when)->create(['site_id' => $site->id, 'collection_id' => $col->id]);
    });

    return [$ws, $entry];
}

it('publica una entrada programada vencida y emite EntryPublished', function () {
    Event::fake([EntryPublished::class]);
    [$ws, $entry] = makeScheduled(now()->subMinute());

    PublishScheduledEntries::dispatchSync();

    withinWorkspace($ws, fn () => expect(Entry::find($entry->id)->status)->toBe(Entry::STATUS_PUBLISHED));
    Event::assertDispatched(EntryPublished::class);
});

it('NO publica una programada aún futura', function () {
    Event::fake([EntryPublished::class]);
    [$ws, $entry] = makeScheduled(now()->addDay());

    PublishScheduledEntries::dispatchSync();

    withinWorkspace($ws, fn () => expect(Entry::find($entry->id)->status)->toBe(Entry::STATUS_SCHEDULED));
    Event::assertNotDispatched(EntryPublished::class);
});

it('es idempotente: re-ejecutar no re-publica ni re-emite', function () {
    Event::fake([EntryPublished::class]);
    [, $entry] = makeScheduled(now()->subMinute());

    PublishScheduledEntries::dispatchSync();
    PublishScheduledEntries::dispatchSync();

    Event::assertDispatchedTimes(EntryPublished::class, 1);
});

it('el comando content:publish-scheduled publica las vencidas', function () {
    [$ws, $entry] = makeScheduled(now()->subMinute());

    $this->artisan('content:publish-scheduled')->assertSuccessful();

    withinWorkspace($ws, fn () => expect(Entry::find($entry->id)->status)->toBe(Entry::STATUS_PUBLISHED));
});
