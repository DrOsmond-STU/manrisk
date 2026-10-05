<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CoreSeeder::class, UserSeeder::class]);
        if (filter_var(env('MR_SEED_DEMO', true), FILTER_VALIDATE_BOOL)) {
            $this->call([DemoSeeder::class]);
        }
    }
}
