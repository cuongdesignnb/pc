<?php

namespace App\Console\Commands;

use App\Jobs\Integrations\Kiot\ProcessKiotOutboxEvent;
use App\Models\IntegrationOutboxEvent;
use App\Services\Integrations\Kiot\KiotConfigurationResolver;
use Illuminate\Console\Command;

class RetryKiotOutboxCommand extends Command
{
    protected $signature = 'kiot:retry-outbox {--id=}';

    protected $description = 'Dispatch các sự kiện KIOT đang chờ, retry hoặc stale-lock';

    public function handle(KiotConfigurationResolver $resolver): int
    {
        if (! $resolver->resolve()->orderSyncEnabled) {
            return self::SUCCESS;
        }

        $query = IntegrationOutboxEvent::where('integration', 'kiot')
            ->where(function ($query) {
                $query->where(fn ($q) => $q->whereIn('status', ['pending', 'retrying'])->where(fn ($due) => $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now())))
                    ->orWhere(fn ($q) => $q->where('status', 'processing')->where('locked_at', '<=', now()->subMinutes(5)));
            });
        if ($this->option('id')) {
            $query->whereKey($this->option('id'));
        }
        $count = 0;
        $query->select('id')->chunkById(100, function ($events) use (&$count) {
            foreach ($events as $event) {
                ProcessKiotOutboxEvent::dispatch($event->id);
                $count++;
            }
        });
        $this->info("Đã dispatch {$count} sự kiện KIOT.");

        return self::SUCCESS;
    }
}
