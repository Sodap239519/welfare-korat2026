<?php

namespace Database\Seeders;

use App\Models\Channel;
use Illuminate\Database\Seeder;

class ChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            ['code' => 'website',   'name' => 'เว็บไซต์',            'icon' => 'fi-rr-browser',       'sort_order' => 1],
            ['code' => 'paotang',   'name' => 'แอปเป๋าตัง',          'icon' => 'fi-rr-mobile-button', 'sort_order' => 2],
            ['code' => 'atm_ktb',   'name' => 'ATM กรุงไทย',         'icon' => 'fi-rr-money-check',   'sort_order' => 3],
            ['code' => 'bank',      'name' => 'ธนาคาร 5 แห่ง',       'icon' => 'fi-rr-bank',          'sort_order' => 4],
        ];

        foreach ($channels as $c) {
            Channel::updateOrCreate(['code' => $c['code']], $c);
        }
    }
}
