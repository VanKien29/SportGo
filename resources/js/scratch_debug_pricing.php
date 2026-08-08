<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\CourtType;
use Illuminate\Support\Facades\DB;

$clusters = VenueCluster::all();
echo "Total Venue Clusters in DB: " . $clusters->count() . "\n";
foreach ($clusters as $c) {
    echo "Cluster ID: {$c->id} | Name: {$c->name} | Owner ID: {$c->owner_id}\n";
}

$courts = VenueCourt::all();
echo "\nTotal Venue Courts in DB: " . $courts->count() . "\n";
foreach ($courts as $vc) {
    echo "Court ID: {$vc->id} | Name: {$vc->name} | Cluster ID: {$vc->venue_cluster_id} | Court Type ID: {$vc->court_type_id}\n";
}

$courtTypes = CourtType::all();
echo "\nTotal Court Types in DB: " . $courtTypes->count() . "\n";
foreach ($courtTypes as $ct) {
    echo "Court Type ID: {$ct->id} | Name: {$ct->name} | Active: " . ($ct->is_active ? 'YES' : 'NO') . " | Deleted: " . ($ct->deleted_at ? 'YES' : 'NO') . "\n";
}

$groupedCourtTypes = DB::table('venue_courts')
    ->join('court_types', 'court_types.id', '=', 'venue_courts.court_type_id')
    ->whereNull('venue_courts.deleted_at')
    ->whereNull('court_types.deleted_at')
    ->where('court_types.is_active', true)
    ->select(['venue_courts.venue_cluster_id', 'court_types.id', 'court_types.name'])
    ->distinct()
    ->get()
    ->groupBy('venue_cluster_id');

echo "\nGrouped Court Types query result:\n";
print_r($groupedCourtTypes->toArray());
