<?php

namespace App\Jobs\Ai;

use App\Models\AiGenerationSchedule;
use App\Models\AiProductContentCampaign;
use App\Models\Post;
use App\Services\Ai\AiGenerationService;
use App\Services\Ai\ProductContentCampaignService;
use App\Services\Seo\VietnameseSlugNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProcessAiGenerationSchedule implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $scheduleId) {}

    public function handle(AiGenerationService $generation, VietnameseSlugNormalizer $slugs, ProductContentCampaignService $campaigns): void
    {
        $schedule = AiGenerationSchedule::find($this->scheduleId);
        if (! $schedule || $schedule->status !== 'processing') {
            return;
        }

        try {
            if ($schedule->type === 'product_description' && $schedule->product) {
                $campaign = AiProductContentCampaign::create([
                    'name' => 'Chuyển lịch AI cũ #'.$schedule->id,
                    'filters' => ['legacy_schedule_id' => $schedule->id],
                    'mode' => 'draft',
                    'technical_heading' => 'auto',
                    'use_web_research' => false,
                    'append_contact_footer' => false,
                    'include_product_images' => true,
                    'max_items' => 1,
                    'status' => 'pending',
                    'scheduled_at' => now(),
                    'created_by' => $schedule->created_by,
                ]);
                $campaign->items()->create([
                    'product_id' => $schedule->product_id,
                    'source_snapshot' => $campaigns->snapshot($schedule->product()->with(['images', 'specifications.specificationKey'])->firstOrFail(), true),
                    'status' => 'pending',
                ]);
                $campaign->refreshProgress();
                $campaigns->dispatch($campaign);
                $schedule->update([
                    'status' => 'done',
                    'warnings' => ['Đã chuyển sang campaign nội dung sản phẩm ở chế độ nháp. Sản phẩm chưa bị thay đổi.'],
                    'processed_at' => now(),
                    'completed_at' => now(),
                    'error_message' => null,
                ]);

                return;
            }

            $result = $generation->generate([
                'topic' => $schedule->topic,
                'keywords' => $schedule->keywords,
                'type' => $schedule->type,
                'tone' => $schedule->tone,
                'length' => $schedule->length,
                'full_article' => $schedule->full_article,
                'with_images' => $schedule->with_images,
                'image_count' => $schedule->image_count,
                'category_id' => $schedule->category_id,
                'product_id' => $schedule->product_id,
                'existing_content' => $schedule->product?->description,
            ], $schedule->created_by);

            $articleId = null;
            if ($schedule->type === 'article') {
                $articleTitle = $result['title'] ?: $schedule->topic;
                $slug = $slugs->normalize($result['slug'] ?? $articleTitle);
                if ($slugs->isReserved($slug)) {
                    $slug = $slugs->normalize('bai-viet '.$articleTitle);
                }
                $baseSlug = $slug;
                $counter = 1;
                while (Post::where('slug', $slug)->exists()) {
                    $slug = $slugs->normalize($baseSlug.' '.($counter++));
                }
                $post = Post::create([
                    'user_id' => $schedule->created_by ?? 1,
                    'post_category_id' => $schedule->category_id,
                    'title' => $articleTitle,
                    'slug' => $slug,
                    'excerpt' => $result['excerpt'],
                    'body' => $result['content'],
                    'featured_image' => $result['thumbnail'],
                    'status' => $schedule->auto_publish ? 'published' : 'draft',
                    'published_at' => $schedule->auto_publish ? now() : null,
                    'meta_title' => $result['meta_title'],
                    'meta_description' => $result['meta_description'],
                    'view_count' => 0,
                    'slug_source' => $articleTitle,
                    'slug_policy_version' => VietnameseSlugNormalizer::POLICY_VERSION,
                    'slug_locked_at' => now(),
                ]);
                $articleId = $post->id;
            }

            $schedule->update([
                'status' => 'done', 'article_id' => $articleId, 'warnings' => $result['warnings'],
                'processed_at' => now(), 'completed_at' => now(), 'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $attempts = $schedule->attempts + 1;
            $schedule->update([
                'status' => $attempts >= 3 ? 'failed' : 'pending',
                'attempts' => $attempts,
                'error_message' => Str::limit($e->getMessage(), 500, ''),
                'scheduled_at' => $attempts >= 3 ? $schedule->scheduled_at : now()->addMinutes(5),
                'processed_at' => now(),
            ]);
            if ($attempts < 3) {
                throw $e;
            }
        }
    }
}
