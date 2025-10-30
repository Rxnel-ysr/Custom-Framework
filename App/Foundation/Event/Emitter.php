<?php

namespace App\Foundation\Event;

use Throwable;

/**
 * Emitter class
 * 
 * @depends App\Foundation\Event\EmitterInterface
 */
class Emitter implements EmitterInterface
{
    private ReceiverInterface $receiver;
    private array $active;

    public function __construct(ReceiverInterface $receiver)
    {
        $this->receiver = $receiver;
    }

    public function emit(string $event, bool $highestFirst = true, ?callable $onerror = null, mixed ...$payloads)
    {
        if (isset($this->active[$event])) return;
        $this->active[$event] = true;

        try {
            foreach ($this->receiver->getEvents($event, $highestFirst) as $fn) {
                try {
                    $fn(...$payloads);
                } catch (Throwable $e) {
                    if ($onerror !== null) $onerror($e);
                    continue;
                }
            }
        } finally {
            unset($this->active[$event]);
        }
    }

    public function setReceiver(ReceiverInterface $receiver): self
    {
        $this->receiver = $receiver;
        return $this;
    }
}
