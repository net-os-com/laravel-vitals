<?php

use Illuminate\Support\Facades\Exceptions;
use NetOs\Vitals\Running\CheckRunner;
use NetOs\Vitals\Support\SentryEvents;
use NetOs\Vitals\Tests\Fixtures\FixedCheck;

it('builds a link to a Sentry event from the configured template', function () {
    expect(SentryEvents::url('abc123'))->toBeNull();

    config(['vitals.links.sentry_event' => 'https://my-org.sentry.io/issues/?query=id%3A{event_id}']);

    expect(SentryEvents::url('abc123'))->toBe('https://my-org.sentry.io/issues/?query=id%3Aabc123');
});

it('stores the Sentry event id of a crash when the Sentry SDK is installed', function () {
    Exceptions::fake();

    $run = (new CheckRunner)->run(new FixedCheck(fn () => throw new RuntimeException('Boom')));

    expect($run->result->meta)->toBe(class_exists('Sentry\SentrySdk') && SentryEvents::lastEventId() !== null
        ? ['sentry_event_id' => SentryEvents::lastEventId()]
        : []);
});
