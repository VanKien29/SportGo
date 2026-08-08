<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Booking;
use App\Models\VenueCluster;
use App\Models\VenueCourt;
use App\Models\User;
use Carbon\Carbon;

$clusters = VenueCluster::with('venueCourts')->get();

echo "Found " . $clusters->count() . " clusters.\n";

$client = User::first();

foreach ($clusters as $cluster) {
    $courts = $cluster->venueCourts;
    if ($courts->isEmpty()) {
        echo "Cluster {$cluster->name} has no courts. Skipping.\n";
        continue;
    }

    $court = $courts->first();

    // Create 2 sample bookings for each cluster if count is less than 3
    $existing = Booking::where('venue_cluster_id', $cluster->id)->count();
    if ($existing < 2) {
        for ($i = 1; $i <= 3; $i++) {
            $code = 'BK' . strtoupper(substr(md5(uniqid()), 0, 6));
            $bookingDate = Carbon::today()->addDays($i)->format('Y-m-d');
            
            Booking::create([
                'booking_code' => $code,
                'user_id' => $client->id,
                'venue_cluster_id' => $cluster->id,
                'venue_court_id' => $court->id,
                'court_type_id' => $court->court_type_id,
                'booking_date' => $bookingDate,
                'start_time' => sprintf('%02d:00:00', 7 + $i * 2),
                'end_time' => sprintf('%02d:00:00', 8 + $i * 2),
                'total_price' => 150000 * $i,
                'status' => 'confirmed',
                'payment_status' => $i === 1 ? 'paid' : 'unpaid',
                'payment_option' => 'full_payment',
                'source' => $i % 2 === 0 ? 'online' : 'counter',
                'note' => 'Đặt sân mẫu thử nghiệm giao diện',
            ]);
            echo "Created booking {$code} for cluster {$cluster->name}\n";
        }
    } else {
        echo "Cluster {$cluster->name} already has {$existing} bookings.\n";
    }
}

echo "Total bookings in database: " . Booking::count() . "\n";
