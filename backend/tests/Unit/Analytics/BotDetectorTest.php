<?php

declare(strict_types=1);

use App\Modules\Analytics\Domain\BotDetector;

beforeEach(function () {
    $this->detector = new BotDetector;
});

it('detecta bots/crawlers conocidos', function (string $ua) {
    expect($this->detector->isBot($ua))->toBeTrue();
})->with([
    'Googlebot/2.1 (+http://www.google.com/bot.html)',
    'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'facebookexternalhit/1.1',
    'curl/8.4.0',
    'python-requests/2.31.0',
]);

it('trata un User-Agent vacío como bot', function () {
    expect($this->detector->isBot(''))->toBeTrue()
        ->and($this->detector->isBot('   '))->toBeTrue();
});

it('no marca navegadores reales como bots', function (string $ua) {
    expect($this->detector->isBot($ua))->toBeFalse();
})->with([
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
]);
