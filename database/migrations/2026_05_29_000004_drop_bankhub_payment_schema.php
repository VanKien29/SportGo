<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payments', 'venue_payment_account_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['venue_payment_account_id']);
                $table->dropIndex('payments_venue_payment_account_id_index');
                $table->dropColumn('venue_payment_account_id');
            });
        }

        Schema::dropIfExists('venue_payment_accounts');

        if (Schema::hasColumn('venue_clusters', 'sepay_company_xid')) {
            Schema::table('venue_clusters', function (Blueprint $table) {
                $table->dropIndex(['sepay_company_xid']);
                $table->dropColumn('sepay_company_xid');
            });
        }
    }

    public function down(): void
    {
        //
    }
};
