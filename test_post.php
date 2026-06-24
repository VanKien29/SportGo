<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::whereHas('roles', function($q) { $q->where('name', 'owner'); })->first();
if (!$user) { echo "No owner found"; exit; }

// Create a dummy image
$tempPath = sys_get_temp_dir() . '/dummy_thumb.jpg';
file_put_contents($tempPath, 'dummy image content');
$thumbnail = new Illuminate\Http\UploadedFile($tempPath, 'dummy_thumb.jpg', 'image/jpeg', null, true);

$request = Illuminate\Http\Request::create('/api/owner/venue-posts', 'POST', [
    'title' => 'Tieu de test nhe hihihi',
    'short_description' => 'Mo ta ngan gon de test bai viet nhe moi nguoi ahuhu',
    'content' => str_repeat('Noi dung the html gi do ', 10),
    'post_type' => 'news',
    'is_draft' => 0,
    'venue_cluster_id' => App\Models\VenueCluster::where('owner_id', $user->id)->first()->id,
]);
$request->files->set('thumbnail', $thumbnail);
$request->setUserResolver(function() use ($user) { return $user; });

try {
    $storeRequest = App\Http\Requests\StoreVenuePostRequest::createFrom($request);
    $storeRequest->setContainer($app);
    $storeRequest->setRedirector($app->make(\Illuminate\Routing\Redirector::class));
    $storeRequest->validateResolved();

    $controller = app()->make(App\Http\Controllers\Api\Owner\VenuePostController::class);
    $response = $controller->store($storeRequest);
    echo $response->getContent();
} catch (\Illuminate\Validation\ValidationException $e) {
    echo "Validation Error: " . json_encode($e->errors(), JSON_UNESCAPED_UNICODE);
} catch (\Illuminate\Auth\Access\AuthorizationException $e) {
    echo "Auth Error: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
