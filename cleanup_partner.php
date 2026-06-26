<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Schema::disableForeignKeyConstraints();

DB::table('partner_applications')->truncate();
DB::table('partner_application_courts')->truncate();
DB::table('partner_application_documents')->truncate();
DB::table('partner_contracts')->truncate();
DB::table('generated_documents')->truncate();
DB::table('generated_document_signatures')->truncate();
DB::table('document_signing_requests')->truncate();
DB::table('partner_termination_requests')->truncate();
DB::table('partner_settlements')->truncate();
DB::table('partner_settlement_items')->truncate();
DB::table('owner_bank_accounts')->truncate();

Schema::enableForeignKeyConstraints();

echo "Cleaned up all partner application data.\n";
