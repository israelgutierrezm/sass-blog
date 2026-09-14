<?php

declare(strict_types=1);

use App\Modules\Analytics\Domain\VisitorHasher;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->hasher = new VisitorHasher;
});

it('es determinista dentro de un mismo (sitio, día)', function () {
    $day = Carbon::parse('2026-09-13');

    $a = $this->hasher->hash(1, '203.0.113.5', 'UA', $day);
    $b = $this->hasher->hash(1, '203.0.113.5', 'UA', $day);

    expect($a)->toBe($b)->and($a)->toHaveLength(64);
});

it('cambia al día siguiente (sin seguimiento entre días)', function () {
    $ip = '203.0.113.5';
    $ua = 'UA';

    expect($this->hasher->hash(1, $ip, $ua, Carbon::parse('2026-09-13')))
        ->not->toBe($this->hasher->hash(1, $ip, $ua, Carbon::parse('2026-09-14')));
});

it('difiere entre sitios (sin correlación cross-site)', function () {
    $day = Carbon::parse('2026-09-13');

    expect($this->hasher->hash(1, '203.0.113.5', 'UA', $day))
        ->not->toBe($this->hasher->hash(2, '203.0.113.5', 'UA', $day));
});

it('difiere por IP y no filtra la IP en el hash', function () {
    $day = Carbon::parse('2026-09-13');

    $a = $this->hasher->hash(1, '203.0.113.5', 'UA', $day);
    $b = $this->hasher->hash(1, '198.51.100.9', 'UA', $day);

    expect($a)->not->toBe($b)
        ->and($a)->not->toContain('203.0.113.5');
});
