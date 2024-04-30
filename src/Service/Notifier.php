<?php

/**
 * Dispatcher for events and handling - simple wrapper
 * Is useful for tests and debugging only. Do not use in production
 */
class Notifier
{
    public static EventsHandler|null $handler = null;

    /**
     * Notify on event
     * @param string $eventName
     * @param array $context
     * @return void
     */
    public static function notify(string $eventName, array $context = []): void
    {
        // handle events only if events handler specified. Otherwise skip
        self::$handler?->handle($eventName, $context);
    }
}
