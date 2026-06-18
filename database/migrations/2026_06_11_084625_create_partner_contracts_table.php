<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('partner_contracts')) {
            if (! Schema::hasColumn('partner_contracts', 'deleted_at')) {
                Schema::table('partner_contracts', function (Blueprint $table) {
                    $table->softDeletes();
                });
            }

            return;
        }

        Schema::create('partner_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('partner_application_id')->constrained('partner_applications')->onDelete('cascade');
            $table->foreignId('contract_template_id')->constrained('contract_templates')->onDelete('restrict');
            $table->string('contract_number')->unique();
            $table->string('status')->default('draft'); // draft, waiting_signature, signed, completed, liquidated, terminated
            $table->string('generated_file_path')->nullable();
            $table->string('final_signed_file_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_contracts');
    }
};
