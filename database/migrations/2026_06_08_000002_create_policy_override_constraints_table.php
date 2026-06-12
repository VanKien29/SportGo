<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('policy_override_constraints')) {
            return;
        }

        Schema::create('policy_override_constraints', function (Blueprint $table): void {
            $table->id();
            $table->char('system_policy_id', 36);
            $table->char('policy_rule_id', 36)->nullable();
            $table->string('rule_code', 100);
            $table->string('constraint_key', 100);
            $table->string('constraint_name', 255);
            $table->enum('comparison_direction', [
                'exact_only',
                'venue_can_be_more_favorable_to_customer',
                'venue_can_be_stricter_for_safety',
                'venue_can_only_choose_within_range',
            ]);
            $table->decimal('min_value', 12, 2)->nullable();
            $table->decimal('max_value', 12, 2)->nullable();
            $table->json('allowed_values')->nullable();
            $table->text('message_vi');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['system_policy_id', 'constraint_key'], 'policy_override_constraints_policy_key_unique');
            $table->index(['rule_code', 'is_active'], 'policy_override_constraints_rule_active_index');
            $table->foreign('system_policy_id', 'policy_override_constraints_policy_foreign')
                ->references('id')->on('system_policies')->onDelete('restrict');
            $table->foreign('policy_rule_id', 'policy_override_constraints_rule_foreign')
                ->references('id')->on('policy_rules')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_override_constraints');
    }
};
