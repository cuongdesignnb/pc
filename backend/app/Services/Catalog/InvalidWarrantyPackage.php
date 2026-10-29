<?php

namespace App\Services\Catalog;

use RuntimeException;

final class InvalidWarrantyPackage extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Gói bảo hành đã chọn không còn áp dụng cho sản phẩm này.');
    }
}
