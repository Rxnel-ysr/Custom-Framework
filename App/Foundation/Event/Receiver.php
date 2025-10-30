<?php

namespace App\Foundation\Event;

use Generator;

/**
 * Emitter class
 * 
 * @depends App\Foundation\Event\ReceiverInterface
 */
class Receiver implements ReceiverInterface
{
    private array $events = [];

    public function __construct($events = [])
    {
        $this->events = $events;
    }

    public function on(string $event, callable $respond, int $priority = 0): void
    {
        $this->events[$event][$priority][] = $respond;
    }

    /**
     * Alternative: Get sorted listeners without array_merge (more memory efficient)
     */
    public function getEvents(string $event, bool $highestFirst = false): Generator
    {
        if (empty($this->events[$event])) {
            return [];
        }

        $priorities = $this->events[$event];

        if ($highestFirst) {
            krsort($priorities);
        } else {
            ksort($priorities);
        }

        foreach ($priorities as $priorityListeners) {
            foreach ($priorityListeners as $listener) {
                yield $listener;
            }
        }
    }
}
