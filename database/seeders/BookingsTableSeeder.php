<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BookingsTableSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BookingFinanceTestDataSeeder::class);
    }
}
