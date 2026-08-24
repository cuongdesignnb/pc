<?php

namespace App\Jobs\Ai;

use App\Models\AiGenerationSchedule;
use App\Models\Post;
use App\Services\Ai\AiGenerationService;
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

    public function handle(AiGenerationService $generation): void
    {
        $schedule = AiGenerationSchedule::find($this->scheduleId);
        if (! $schedule || $schedule->status !== 'processing') {
            return;
        }

        try {
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
                $slug = Str::slug($result['title'] ?: $schedule->topic) ?: 'bai-viet-ai';
                $baseSlug = $slug;
                $counter = 1;
                while (Post::where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.($counter++);
                }
                $post = Post::create([
                    'user_id' => $schedule->created_by ?? 1,
                    'post_category_id' => $schedule->category_id,
                    'title' => $result['title'] ?: $schedule->topic,
                    'slug' => $slug,
                    'excerpt' => $result['excerpt'],
                    'body' => $result['content'],
                    'featured_image' => $result['thumbnail'],
                    'status' => $schedule->auto_publish ? 'published' : 'draft',
                    'published_at' => $schedule->auto_publish ? now() : null,
                    'meta_title' => $result['meta_title'],
                    'meta_description' => $result['meta_description'],
                    'view_count' => 0,
                ]);
                $articleId = $post->id;
            } elseif ($schedule->type === 'product_description' && $schedule->product) {
                $schedule->product->update([
                    'description' => $result['content'],
                    'short_description' => Str::limit(strip_tags($result['short_description'] ?: $result['excerpt']), 500, ''),
                    'meta_title' => $result['meta_title'],
                    'meta_description' => $result['meta_description'],
                ]);
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
