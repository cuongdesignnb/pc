<?php

namespace App\Console\Commands;

use App\Jobs\Ai\ProcessAiGenerationSchedule;
use App\Models\AiGenerationSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessAiGenerationSchedules extends Command
{
    protected $signature = 'ai:process-schedules {--limit=10}';

    protected $description = 'Đưa các lịch viết AI đến hạn vào hàng đợi';

    public function handle(): int
    {
        $limit = max(1, min(50, (int) $this->option('limit')));
        $ids = [];
        DB::transaction(function () use ($limit, &$ids) {
            $ids = AiGenerationSchedule::query()
                ->where('scheduled_at', '<=', now())
                ->where(function ($q) {
                    $q->where('status', 'pending')
                        ->orWhere(function ($q) {
                            $q->where('status', 'processing')->where('locked_at', '<', now()->subMinutes(15));
                        });
                })
                ->where('attempts', '<', 3)
                ->lock('for update')
                ->limit($limit)
                ->pluck('id')
                ->all();
            if ($ids) {
                AiGenerationSchedule::whereIn('id', $ids)->update([
                    'status' => 'processing', 'locked_at' => now(), 'started_at' => now(),
                ]);
            }
        });

        foreach ($ids as $id) {
            ProcessAiGenerationSchedule::dispatch($id);
        }
        $this->info('Đã xếp '.count($ids).' lịch AI.');

        return self::SUCCESS;
    }
}
