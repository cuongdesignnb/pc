<?php

namespace App\Console\Commands;

use App\Services\Integrations\Kiot\KiotConnectionTestService;
use Illuminate\Console\Command;

class KiotConnectionTestCommand extends Command
{
    protected $signature = 'kiot:connection-test';

    protected $description = 'Kiểm tra kết nối đọc sản phẩm KIOT, không ghi dữ liệu';

    public function handle(KiotConnectionTestService $connectionTest): int
    {
        $started = microtime(true);
        $result = $connectionTest->test();
        $duration = (int) ((microtime(true) - $started) * 1000);
        if (! $result['success']) {
            $this->error("{$result['error_code']}: {$result['message']} ({$duration} ms)");

            return self::FAILURE;
        }
        $this->info("Kết nối KIOT thành công ({$duration} ms).");

        return self::SUCCESS;
    }
}
