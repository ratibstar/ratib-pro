<?php
declare(strict_types=1);

namespace App\Core;

final class EventDispatcher
{
    /** @var array<string, array<int, callable>> */
    private array $listeners = [];

    /** @var callable[] */
    private array $queue = [];
    /** @var array<string, int> */
    private array $activeDispatches = [];

    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function event(object $event): void
    {
        $eventClass = $event::class;
        $this->activeDispatches[$eventClass] = ($this->activeDispatches[$eventClass] ?? 0) + 1;
        if ($this->activeDispatches[$eventClass] > 10) {
            // Hard stop to avoid recursive dispatch loops.
            $this->activeDispatches[$eventClass]--;
            return;
        }

        foreach ($this->listeners[$eventClass] ?? [] as $listener) {
            $listener($event);
        }
        $this->activeDispatches[$eventClass]--;
    }

    public function dispatchAsync(object $event): void
    {
        $this->queue[] = fn () => $this->event($event);
    }

    public function flushQueue(): void
    {
        while ($job = array_shift($this->queue)) {
            $job();
        }
    }
}
