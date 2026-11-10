<?php

namespace NetOs\Vitals\Support;

/**
 * Reads the id of the event that report() just sent to Sentry, when the Sentry SDK is installed.
 */
final class SentryEvents
{
    private const string SDK = 'Sentry\SentrySdk';

    public static function lastEventId(): ?string
    {
        $getCurrentHub = [self::SDK, 'getCurrentHub'];

        if (! class_exists(self::SDK) || ! is_callable($getCurrentHub)) {
            return null;
        }

        $hub = $getCurrentHub();
        $eventId = is_object($hub) && method_exists($hub, 'getLastEventId') ? $hub->getLastEventId() : null;

        return $eventId === null ? null : (string) $eventId;
    }

    /**
     * @param  array<string, scalar|null>  $meta
     * @return array<string, scalar|null>
     */
    public static function meta(array $meta = []): array
    {
        $eventId = self::lastEventId();

        return $eventId === null ? $meta : [...$meta, 'sentry_event_id' => $eventId];
    }

    public static function url(string $eventId): ?string
    {
        $template = config('vitals.links.sentry_event');

        return is_string($template) && $template !== '' ? str_replace('{event_id}', rawurlencode($eventId), $template) : null;
    }
}
