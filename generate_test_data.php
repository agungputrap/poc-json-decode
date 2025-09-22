<?php

// Generate a large JSON file for testing
$data = [];
for ($i = 1; $i <= 10000; $i++) {
    $data[] = [
        'id' => $i,
        'name' => 'User ' . $i,
        'email' => 'user' . $i . '@example.com',
        'created_at' => date('Y-m-d H:i:s', time() - rand(0, 365*24*3600)),
        'data' => str_repeat('Sample data for user ' . $i . ' ', rand(10, 50))
    ];
}

echo "Generating large JSON file...\n";
file_put_contents('data/large_array.json', json_encode($data, JSON_PRETTY_PRINT));
echo "Generated data/large_array.json with " . count($data) . " items\n";
echo "File size: " . round(filesize('data/large_array.json') / 1024 / 1024, 2) . " MB\n";
