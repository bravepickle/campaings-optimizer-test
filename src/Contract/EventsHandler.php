<?php

/**
 * Notifications handler interface
 */
interface EventsHandler
{
    /**
     * Handle event processing
     * @param string $eventName
     * @param array $context
     * @return void
     */
    public function handle(string $eventName, array $context = []): void;
}
