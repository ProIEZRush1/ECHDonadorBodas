<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::where('slug', 'buy-overcloud')->firstOrFail();

        // Create admin user
        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@buyovercloud.com')],
            [
                'organization_id' => $organization->id,
                'name' => 'Buy Overcloud Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'role' => 'super_admin',
            ],
        );

        // Raffle settings
        $settings = [
            'raffle_draw_date' => '2027-01-30',
            'ticket_price' => '3000',
            'prize_amount' => '100000',
            'bank_name' => 'Bancomer (BBVA)',
            'bank_holder' => 'Messod',
            'bank_account' => '048 133 0551',
            'bank_clabe' => '012 180 00481330551 8',
            'bank_card' => '4152 3139 8046 2845',
            'bank_swift' => 'BCMRMXMMPYM',
        ];

        foreach ($settings as $key => $value) {
            Setting::firstOrCreate(['organization_id' => $organization->id, 'key' => $key], ['value' => $value]);
        }
    }
}
