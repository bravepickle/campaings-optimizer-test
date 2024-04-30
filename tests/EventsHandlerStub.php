<?php

namespace AppTest;

use EventsHandler;

class EventsHandlerStub implements EventsHandler
{
    protected array $events = [];

    /**
     * @inheritDoc
     */
    #[\Override] public function handle(string $eventName, array $context = []): void
    {
        $this->events[] = ['event' => $eventName, 'context' => $context];
    }

    public function getEvents(): array
    {
        return $this->events;
    }
}
