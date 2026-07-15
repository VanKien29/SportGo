<?php

use Illuminate\Database\Migrations\Migration;
return new class extends Migration
{
    public function up(): void
    {
        // personal_access_tokens.tokenable_id follows Laravel's default unsigned bigint morphs.
    }

    public function down(): void
    {
        //
    }
};
