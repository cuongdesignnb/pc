<?php

namespace App\Jobs\Integrations\Kiot;

use App\Services\Integrations\Kiot\KiotOutboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessKiotOutboxEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $eventId) {}

    public function handle(KiotOutboxService $service): void
    {
        $service->process($this->eventId);
    }
}
