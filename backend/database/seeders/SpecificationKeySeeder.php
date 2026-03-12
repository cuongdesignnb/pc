<?php

namespace Database\Seeders;

use App\Models\ComponentType;
use App\Models\SpecificationKey;
use Illuminate\Database\Seeder;

class SpecificationKeySeeder extends Seeder
{
    public function run(): void
    {
        $specs = [
            // CPU specs
            'cpu' => [
                ['key' => 'socket', 'label' => 'Socket', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'cores', 'label' => 'Số nhân', 'unit' => null, 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'threads', 'label' => 'Số luồng', 'unit' => null, 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'base_clock', 'label' => 'Xung cơ bản', 'unit' => 'GHz', 'data_type' => 'decimal', 'is_filterable' => true],
                ['key' => 'boost_clock', 'label' => 'Xung tối đa', 'unit' => 'GHz', 'data_type' => 'decimal', 'is_filterable' => true],
                ['key' => 'tdp', 'label' => 'TDP', 'unit' => 'W', 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'integrated_gpu', 'label' => 'GPU tích hợp', 'unit' => null, 'data_type' => 'boolean', 'is_filterable' => true],
                ['key' => 'memory_type', 'label' => 'Loại RAM hỗ trợ', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
            ],
            // Mainboard specs
            'mainboard' => [
                ['key' => 'socket', 'label' => 'Socket', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'chipset', 'label' => 'Chipset', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'form_factor', 'label' => 'Kích thước', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'memory_type', 'label' => 'Loại RAM', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'memory_slots', 'label' => 'Số khe RAM', 'unit' => null, 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'max_memory', 'label' => 'RAM tối đa', 'unit' => 'GB', 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'm2_slots', 'label' => 'Số khe M.2', 'unit' => null, 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'sata_ports', 'label' => 'Số cổng SATA', 'unit' => null, 'data_type' => 'integer', 'is_filterable' => false],
                ['key' => 'pcie_x16_slots', 'label' => 'Số khe PCIe x16', 'unit' => null, 'data_type' => 'integer', 'is_filterable' => false],
            ],
            // RAM specs
            'ram' => [
                ['key' => 'memory_type', 'label' => 'Loại RAM', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'capacity', 'label' => 'Dung lượng', 'unit' => 'GB', 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'speed', 'label' => 'Tốc độ', 'unit' => 'MHz', 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'kit_type', 'label' => 'Số thanh', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'latency', 'label' => 'CAS Latency', 'unit' => null, 'data_type' => 'string', 'is_filterable' => false],
                ['key' => 'rgb', 'label' => 'LED RGB', 'unit' => null, 'data_type' => 'boolean', 'is_filterable' => true],
            ],
            // VGA specs
            'vga' => [
                ['key' => 'gpu_chip', 'label' => 'Chip GPU', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'vram', 'label' => 'VRAM', 'unit' => 'GB', 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'vram_type', 'label' => 'Loại VRAM', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'core_clock', 'label' => 'Xung core', 'unit' => 'MHz', 'data_type' => 'integer', 'is_filterable' => false],
                ['key' => 'boost_clock', 'label' => 'Xung boost', 'unit' => 'MHz', 'data_type' => 'integer', 'is_filterable' => false],
                ['key' => 'tdp', 'label' => 'TDP', 'unit' => 'W', 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'length', 'label' => 'Chiều dài', 'unit' => 'mm', 'data_type' => 'integer', 'is_filterable' => false],
                ['key' => 'power_connectors', 'label' => 'Nguồn phụ', 'unit' => null, 'data_type' => 'string', 'is_filterable' => false],
            ],
            // SSD specs
            'ssd' => [
                ['key' => 'capacity', 'label' => 'Dung lượng', 'unit' => 'GB', 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'interface', 'label' => 'Chuẩn kết nối', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'form_factor', 'label' => 'Kích thước', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'read_speed', 'label' => 'Tốc độ đọc', 'unit' => 'MB/s', 'data_type' => 'integer', 'is_filterable' => false],
                ['key' => 'write_speed', 'label' => 'Tốc độ ghi', 'unit' => 'MB/s', 'data_type' => 'integer', 'is_filterable' => false],
            ],
            // HDD specs
            'hdd' => [
                ['key' => 'capacity', 'label' => 'Dung lượng', 'unit' => 'TB', 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'rpm', 'label' => 'Tốc độ quay', 'unit' => 'RPM', 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'cache', 'label' => 'Bộ nhớ đệm', 'unit' => 'MB', 'data_type' => 'integer', 'is_filterable' => false],
                ['key' => 'form_factor', 'label' => 'Kích thước', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
            ],
            // PSU specs
            'psu' => [
                ['key' => 'wattage', 'label' => 'Công suất', 'unit' => 'W', 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'efficiency', 'label' => 'Chứng nhận 80+', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'modular', 'label' => 'Modular', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'form_factor', 'label' => 'Kích thước', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
            ],
            // Case specs
            'case' => [
                ['key' => 'form_factor', 'label' => 'Kích thước mainboard', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'max_gpu_length', 'label' => 'Chiều dài VGA tối đa', 'unit' => 'mm', 'data_type' => 'integer', 'is_filterable' => false],
                ['key' => 'max_cooler_height', 'label' => 'Chiều cao tản nhiệt tối đa', 'unit' => 'mm', 'data_type' => 'integer', 'is_filterable' => false],
                ['key' => 'drive_bays_25', 'label' => 'Khay ổ 2.5"', 'unit' => null, 'data_type' => 'integer', 'is_filterable' => false],
                ['key' => 'drive_bays_35', 'label' => 'Khay ổ 3.5"', 'unit' => null, 'data_type' => 'integer', 'is_filterable' => false],
                ['key' => 'tempered_glass', 'label' => 'Kính cường lực', 'unit' => null, 'data_type' => 'boolean', 'is_filterable' => true],
            ],
            // Cooler specs
            'cooler' => [
                ['key' => 'type', 'label' => 'Loại tản nhiệt', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'socket_support', 'label' => 'Socket hỗ trợ', 'unit' => null, 'data_type' => 'string', 'is_filterable' => true],
                ['key' => 'tdp_rating', 'label' => 'TDP hỗ trợ', 'unit' => 'W', 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'height', 'label' => 'Chiều cao', 'unit' => 'mm', 'data_type' => 'integer', 'is_filterable' => false],
                ['key' => 'radiator_size', 'label' => 'Kích thước rad', 'unit' => 'mm', 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'rgb', 'label' => 'LED RGB', 'unit' => null, 'data_type' => 'boolean', 'is_filterable' => true],
            ],
            // Fan specs
            'fan' => [
                ['key' => 'size', 'label' => 'Kích thước', 'unit' => 'mm', 'data_type' => 'integer', 'is_filterable' => true],
                ['key' => 'rpm', 'label' => 'Tốc độ', 'unit' => 'RPM', 'data_type' => 'string', 'is_filterable' => false],
                ['key' => 'airflow', 'label' => 'Lưu lượng gió', 'unit' => 'CFM', 'data_type' => 'decimal', 'is_filterable' => false],
                ['key' => 'noise_level', 'label' => 'Độ ồn', 'unit' => 'dBA', 'data_type' => 'decimal', 'is_filterable' => false],
                ['key' => 'rgb', 'label' => 'LED RGB', 'unit' => null, 'data_type' => 'boolean', 'is_filterable' => true],
                ['key' => 'pack_quantity', 'label' => 'Số lượng trong bộ', 'unit' => null, 'data_type' => 'integer', 'is_filterable' => false],
            ],
        ];

        foreach ($specs as $typeSlug => $typeSpecs) {
            $componentType = ComponentType::where('slug', $typeSlug)->first();
            if ($componentType) {
                foreach ($typeSpecs as $index => $spec) {
                    SpecificationKey::create([
                        'component_type_id' => $componentType->id,
                        'key' => $spec['key'],
                        'label' => $spec['label'],
                        'unit' => $spec['unit'],
                        'data_type' => $spec['data_type'],
                        'is_filterable' => $spec['is_filterable'],
                        'display_order' => $index + 1,
                    ]);
                }
            }
        }
    }
}
