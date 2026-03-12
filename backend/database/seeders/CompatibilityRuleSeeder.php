<?php

namespace Database\Seeders;

use App\Models\CompatibilityRule;
use App\Models\ComponentType;
use Illuminate\Database\Seeder;

class CompatibilityRuleSeeder extends Seeder
{
    public function run(): void
    {
        $cpu = ComponentType::where('slug', 'cpu')->first();
        $mainboard = ComponentType::where('slug', 'mainboard')->first();
        $ram = ComponentType::where('slug', 'ram')->first();
        $vga = ComponentType::where('slug', 'vga')->first();
        $cooler = ComponentType::where('slug', 'cooler')->first();
        $psu = ComponentType::where('slug', 'psu')->first();
        $case = ComponentType::where('slug', 'case')->first();

        $rules = [
            // CPU - Mainboard socket compatibility
            [
                'source_type_id' => $cpu->id,
                'target_type_id' => $mainboard->id,
                'rule_type' => 'must_match',
                'source_spec_key' => 'socket',
                'target_spec_key' => 'socket',
                'message' => 'Socket CPU phải khớp với socket mainboard',
            ],
            // CPU - Mainboard memory type compatibility
            [
                'source_type_id' => $cpu->id,
                'target_type_id' => $mainboard->id,
                'rule_type' => 'must_match',
                'source_spec_key' => 'memory_type',
                'target_spec_key' => 'memory_type',
                'message' => 'Loại RAM hỗ trợ của CPU phải khớp với mainboard',
            ],
            // Mainboard - RAM type compatibility
            [
                'source_type_id' => $mainboard->id,
                'target_type_id' => $ram->id,
                'rule_type' => 'must_match',
                'source_spec_key' => 'memory_type',
                'target_spec_key' => 'memory_type',
                'message' => 'Loại RAM phải tương thích với mainboard',
            ],
            // Mainboard - Case form factor compatibility
            [
                'source_type_id' => $mainboard->id,
                'target_type_id' => $case->id,
                'rule_type' => 'must_fit',
                'source_spec_key' => 'form_factor',
                'target_spec_key' => 'form_factor',
                'allowed_values' => [
                    'ITX' => ['ITX', 'Micro-ATX', 'ATX', 'E-ATX'],
                    'Micro-ATX' => ['Micro-ATX', 'ATX', 'E-ATX'],
                    'ATX' => ['ATX', 'E-ATX'],
                    'E-ATX' => ['E-ATX'],
                ],
                'message' => 'Kích thước mainboard phải vừa với case',
            ],
            // VGA - Case length compatibility
            [
                'source_type_id' => $vga->id,
                'target_type_id' => $case->id,
                'rule_type' => 'must_fit_dimension',
                'source_spec_key' => 'length',
                'target_spec_key' => 'max_gpu_length',
                'message' => 'Chiều dài VGA phải nhỏ hơn chiều dài tối đa của case',
            ],
            // Cooler - Case height compatibility
            [
                'source_type_id' => $cooler->id,
                'target_type_id' => $case->id,
                'rule_type' => 'must_fit_dimension',
                'source_spec_key' => 'height',
                'target_spec_key' => 'max_cooler_height',
                'message' => 'Chiều cao tản nhiệt phải nhỏ hơn chiều cao tối đa của case',
            ],
            // Cooler - CPU socket compatibility
            [
                'source_type_id' => $cooler->id,
                'target_type_id' => $cpu->id,
                'rule_type' => 'must_contain',
                'source_spec_key' => 'socket_support',
                'target_spec_key' => 'socket',
                'message' => 'Tản nhiệt phải hỗ trợ socket CPU',
            ],
            // VGA + CPU - PSU wattage recommendation
            [
                'source_type_id' => $vga->id,
                'target_type_id' => $psu->id,
                'rule_type' => 'power_check',
                'source_spec_key' => 'tdp',
                'target_spec_key' => 'wattage',
                'power_headroom' => 150, // Extra watts for other components
                'message' => 'Tổng TDP linh kiện phải nhỏ hơn công suất PSU',
            ],
        ];

        foreach ($rules as $rule) {
            CompatibilityRule::create($rule);
        }
    }
}
