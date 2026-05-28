<?php

namespace Database\Seeders;

use App\Models\Hashtag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HashtagsTableSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('hashtags')) {
            return;
        }

        foreach (['sportgo', 'thethao', 'bongda', 'caulong', 'pickleball', 'giaoluu', 'datsan'] as $name) {
            Hashtag::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
