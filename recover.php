<?php
$logPath = 'C:\\Users\\Asus\\.gemini\\antigravity-ide\\brain\\3a61b0ff-64bd-42ec-9f56-95539edf972f\\.system_generated\\logs\\transcript.jsonl';
$lines = file($logPath);
$apiRecovered = false;
$userRecovered = false;

foreach ($lines as $line) {
    if (!$apiRecovered && strpos($line, 'routes/api.php') !== false && strpos($line, 'File Path:') !== false) {
        $data = json_decode($line, true);
        if (isset($data['content'])) {
            file_put_contents('routes/api.php.recovered', $data['content']);
            echo "Recovered api.php\n";
        }
    }
    if (!$userRecovered && strpos($line, 'UserController.php') !== false && strpos($line, 'File Path:') !== false) {
        $data = json_decode($line, true);
        if (isset($data['content'])) {
            file_put_contents('app/Http/Controllers/Api/Admin/UserController.php.recovered', $data['content']);
            echo "Recovered UserController.php\n";
        }
    }
}
