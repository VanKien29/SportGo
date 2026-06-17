<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$policy = App\Models\SystemPolicy::where('policy_type', 'moderation')->where('status', 'active')->first();
echo "Policy: " . $policy->id . " key=" . $policy->key . "\n\n";

$rules = $policy->rules()->get();
foreach ($rules as $r) {
    echo "Rule ID: " . $r->id . "\n";
    echo "  rule_type: " . $r->rule_type . "\n";
    echo "  is_active: " . ($r->is_active ? 'true' : 'false') . "\n";
    echo "  condition_json: " . json_encode($r->condition_json, JSON_UNESCAPED_UNICODE) . "\n";
    echo "  result_json: " . json_encode($r->result_json, JSON_UNESCAPED_UNICODE) . "\n\n";
}

// Check users table columns
echo "\nUsers table lock-related columns:\n";
$cols = ['status', 'lock_type', 'locked_until', 'status_reason'];
foreach ($cols as $col) {
    echo "  $col: " . (Illuminate\Support\Facades\Schema::hasColumn('users', $col) ? 'exists' : 'MISSING') . "\n";
}

// Check users that should be locked
echo "\nUsers with status=locked:\n";
$locked = App\Models\User::where('status', 'locked')->get(['id', 'username', 'status', 'lock_type', 'locked_until']);
echo json_encode($locked, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

// Check reports against users
echo "\nReport counts per user (reportable_type like user):\n";
$reportCounts = Illuminate\Support\Facades\DB::table('reports')
    ->whereIn('reportable_type', ['users', 'user', App\Models\User::class])
    ->select('reportable_id', Illuminate\Support\Facades\DB::raw('COUNT(*) as total'), Illuminate\Support\Facades\DB::raw('COUNT(DISTINCT reporter_id) as unique_reporters'))
    ->groupBy('reportable_id')
    ->orderByDesc('total')
    ->limit(10)
    ->get();
echo json_encode($reportCounts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
