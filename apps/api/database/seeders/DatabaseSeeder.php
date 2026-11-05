<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * The demo account is the only seed we ship: it powers local development,
     * screenshots and the hosted demo status page (PRD §15).
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
