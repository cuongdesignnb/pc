<?php

namespace App\Console\Commands;

use App\Services\Integrations\Kiot\KiotClient;
use Illuminate\Console\Command;

class KiotConnectionTestCommand extends Command
{
    protected $signature = 'kiot:connection-test';

    protected $description = 'Kiểm tra kết nối đọc sản phẩm KIOT, không ghi dữ liệu';

    public function handle(KiotClient $client): int
    {
        $started = microtime(true);
        $response = $client->products(['limit' => 1]);
        $duration = (int) ((microtime(true) - $started) * 1000);
        if (! $response->successful()) {
            $this->error(($response->errorCode() ?? 'UNKNOWN').": HTTP {$response->status} ({$duration} ms)");

            return self::FAILURE;
        }
        $this->info("Kết nối KIOT thành công: HTTP {$response->status} ({$duration} ms).");

        return self::SUCCESS;
    }
}
