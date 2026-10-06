<?php

declare(strict_types=1);

use App\Modules\Domains\Application\DnsResolver;
use App\Modules\Domains\Infrastructure\AutoVerifyDnsResolver;
use App\Modules\Domains\Infrastructure\SystemDnsResolver;
use App\Modules\Domains\Providers\DomainsServiceProvider;

it('auto_verify NUNCA se enlaza en producción, aunque se active por error (ADR-025)', function () {
    config(['sassblog.domains.auto_verify' => true]);

    app()->detectEnvironment(fn () => 'production');
    (new DomainsServiceProvider(app()))->register();
    expect(app(DnsResolver::class))->toBeInstanceOf(SystemDnsResolver::class);

    app()->detectEnvironment(fn () => 'testing');
    (new DomainsServiceProvider(app()))->register();
    expect(app(DnsResolver::class))->toBeInstanceOf(AutoVerifyDnsResolver::class);
});

it('sin auto_verify se usa el DNS del sistema', function () {
    config(['sassblog.domains.auto_verify' => false]);
    (new DomainsServiceProvider(app()))->register();

    expect(app(DnsResolver::class))->toBeInstanceOf(SystemDnsResolver::class);
});
