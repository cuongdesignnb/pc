<?php

namespace App\Console\Commands;

use App\Models\AiProductContentCampaign;
use App\Services\Ai\ProductContentCampaignService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessAiProductContentCampaigns extends Command
{
    protected $signature = 'ai:process-product-content-campaigns {--limit=10}';

    protected $description = 'Đưa các campaign nội dung sản phẩm AI đến hạn vào hàng đợi';

    public function handle(ProductContentCampaignService $campaigns): int
    {
        $limit = max(1, min(50, (int) $this->option('limit')));
        $ids = [];
        DB::transaction(function () use ($limit, &$ids) {
            $ids = AiProductContentCampaign::query()
                ->where('scheduled_at', '<=', now())
                ->whereIn('status', ['pending', 'processing'])
                ->lock('for update')
                ->limit($limit)
                ->pluck('id')
                ->all();
        });

        foreach ($ids as $id) {
            $campaign = AiProductContentCampaign::find($id);
            if ($campaign) {
                $campaigns->dispatch($campaign);
            }
        }
        $this->info('Đã xếp '.count($ids).' campaign AI.');

        return self::SUCCESS;
    }
}
