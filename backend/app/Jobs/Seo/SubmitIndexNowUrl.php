<?php

namespace App\Jobs\Seo;

use App\Services\Seo\IndexNow;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SubmitIndexNowUrl implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300;

    public int $uniqueFor = 120;

    public function __construct(public readonly string $url) {}

    public function uniqueId(): string
    {
        return sha1($this->url);
    }

    public function handle(IndexNow $indexNow): void
    {
        $indexNow->submit([$this->url]);
    }
}
