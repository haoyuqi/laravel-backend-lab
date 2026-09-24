<?php

namespace Database\Seeders;

use App\Models\BlackList;
use App\Models\BlackListLog;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2EDataSeeder extends Seeder
{
    /**
     * Run the database seeds for end-to-end tests.
     */
    public function run(): void
    {
        // 1. Admin User for Filament Panel authentication
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        // 2. Test Visitor for Visitor resource tests
        Visitor::firstOrCreate(
            ['ip' => '127.0.0.1'],
            [
                'city' => 'Localhost',
            ]
        );

        // 3. Test BlackList and BlackListLog for Blacklist resource tests
        $blackList = BlackList::firstOrCreate(['ip' => '127.0.0.99']);

        if ($blackList->logs()->where('url', 'https://example.com/blocked-e2e')->doesntExist()) {
            $log = new BlackListLog;
            $log->url = 'https://example.com/blocked-e2e';
            $blackList->logs()->save($log);
        }
    }
}
