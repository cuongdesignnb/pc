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
        $response = $client->products(['limit' => 1]);
        if (! $response->successful()) {
            $this->error(($response->errorCode() ?? 'UNKNOWN').': '.$response->errorMessage());

            return self::FAILURE;
        }
        $this->info('Kết nối KIOT thành công.');

        return self::SUCCESS;
    }
}
