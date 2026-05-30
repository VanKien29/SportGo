<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PriceSlot;
use App\Models\VenueCourt;
use App\Models\VenueCluster;
use App\Models\CourtType;
use Carbon\Carbon;

echo "========== DB DIAGNOSTIC ==========\n\n";

// 1. All price_slots
echo "--- PRICE SLOTS ---\n";
$allSlots = PriceSlot::all();
if ($allSlots->isEmpty()) {
    echo "!! TABLE IS EMPTY - No price_slots found !!\n";
} else {
    foreach ($allSlots as $s) {
        echo json_encode([
            'id' => $s->id,
            'cluster' => $s->venue_cluster_id,
            'court_type' => $s->court_type_id,
            'time' => $s->start_time . ' - ' . $s->end_time,
            'price' => $s->price,
            'days' => $s->apply_to_days,
            'days_raw' => $s->getRawOriginal('apply_to_days'),
            'active' => $s->is_active,
        ]) . "\n";
    }
}
echo "Total slots: " . $allSlots->count() . "\n\n";

// 2. Active courts
echo "--- ACTIVE VENUE COURTS ---\n";
$courts = VenueCourt::where('status', 'active')->get();
foreach ($courts as $c) {
    echo json_encode([
        'id' => $c->id,
        'cluster_id' => $c->venue_cluster_id,
        'court_type_id' => $c->court_type_id,
        'name' => $c->name,
    ]) . "\n";
}
echo "\n";

// 3. Simulate the price lookup for first active court
if ($courts->isNotEmpty()) {
    $court = $courts->first();
    $today = Carbon::now()->format('Y-m-d');
    $dayOfWeek = Carbon::parse($today)->dayOfWeekIso;
    $startTime = '08:00:00';
    $endTime = '09:00:00';

    echo "--- SIMULATING PRICE LOOKUP ---\n";
    echo "Court: {$court->name} (ID: {$court->id})\n";
    echo "ClusterID: {$court->venue_cluster_id}\n";
    echo "CourtTypeID: {$court->court_type_id}\n";
    echo "Date: {$today} | dayOfWeekIso: {$dayOfWeek}\n";
    echo "Time: {$startTime} - {$endTime}\n\n";

    // Step by step filtering
    $q1 = PriceSlot::where('venue_cluster_id', $court->venue_cluster_id)->count();
    echo "Step1 - matching venue_cluster_id: {$q1} rows\n";

    $q2 = PriceSlot::where('venue_cluster_id', $court->venue_cluster_id)
        ->where('court_type_id', $court->court_type_id)->count();
    echo "Step2 - + matching court_type_id: {$q2} rows\n";

    $q3 = PriceSlot::where('venue_cluster_id', $court->venue_cluster_id)
        ->where('court_type_id', $court->court_type_id)
        ->where('is_active', true)->count();
    echo "Step3 - + is_active=true: {$q3} rows\n";

    $q4 = PriceSlot::where('venue_cluster_id', $court->venue_cluster_id)
        ->where('court_type_id', $court->court_type_id)
        ->where('is_active', true)
        ->where(function ($query) use ($dayOfWeek) {
            $query->whereJsonContains('apply_to_days', $dayOfWeek)
                ->orWhereJsonContains('apply_to_days', (string) $dayOfWeek);
        })->count();
    echo "Step4 - + apply_to_days contains {$dayOfWeek}: {$q4} rows\n";

    $q5 = PriceSlot::where('venue_cluster_id', $court->venue_cluster_id)
        ->where('court_type_id', $court->court_type_id)
        ->where('is_active', true)
        ->where(function ($query) use ($dayOfWeek) {
            $query->whereJsonContains('apply_to_days', $dayOfWeek)
                ->orWhereJsonContains('apply_to_days', (string) $dayOfWeek);
        })
        ->where('start_time', '<=', $startTime)
        ->where('end_time', '>=', $endTime)
        ->count();
    echo "Step5 - + time range match: {$q5} rows\n";

    $finalSlot = PriceSlot::where('venue_cluster_id', $court->venue_cluster_id)
        ->where('court_type_id', $court->court_type_id)
        ->where('is_active', true)
        ->where(function ($query) use ($dayOfWeek) {
            $query->whereJsonContains('apply_to_days', $dayOfWeek)
                ->orWhereJsonContains('apply_to_days', (string) $dayOfWeek);
        })
        ->where('start_time', '<=', $startTime)
        ->where('end_time', '>=', $endTime)
        ->first();

    if ($finalSlot) {
        echo "\nMATCHED SLOT: price = {$finalSlot->price}\n";
    } else {
        echo "\nNO MATCH FOUND - will fallback to 10000\n";
    }
}
