<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('policy_rule_templates')) {
            return;
        }

        Schema::create('policy_rule_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('policy_type', 50);
            $table->string('rule_code', 100);
            $table->string('rule_name', 255);
            $table->text('description')->nullable();
            $table->string('action_code', 100);
            $table->json('condition_schema')->nullable();
            $table->json('result_schema')->nullable();
            $table->boolean('is_venue_overridable')->default(false);
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['policy_type', 'rule_code'], 'policy_rule_templates_type_code_unique');
            $table->index(['policy_type', 'is_active'], 'policy_rule_templates_type_active_index');
            $table->index('action_code', 'policy_rule_templates_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_rule_templates');
    }
};
