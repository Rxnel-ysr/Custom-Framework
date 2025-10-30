<?php

namespace App\Foundation\Event;

use Generator;

interface EmitterInterface
{
    public function __construct(ReceiverInterface $receiver);

    public function emit(string $event, bool $highestFirst = true,?callable $onerror =null, mixed ...$payloads);

    public function setReceiver(ReceiverInterface $receiver): self;
}

interface ReceiverInterface
{
    /**
     * Undocumented function
     *
     * @param array $events
     */
    public function __construct(array $events);
    public function on(string $event, callable $respond, int $priority = 0): void;

    /**
     * Undocumented function
     *
     * @return array<int, callable>
     */
    public function getEvents(string $event, bool $highestFirst): array|Generator;
}
