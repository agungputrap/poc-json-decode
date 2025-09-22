<?php

require_once __DIR__ . '/vendor/autoload.php';

use JsonMachine\Items;
use PocJsonDecode\MemoryProfiler;

echo "JSON Machine Streaming Demo with Large File\n";
echo "==========================================\n\n";

$jsonFile = __DIR__ . '/data/sample_data.json';
$fileSize = filesize($jsonFile);

echo "File: " . basename($jsonFile) . "\n";
echo "Size: " . number_format($fileSize / 1024 / 1024, 2) . " MB\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n\n";

$profiler = new MemoryProfiler();

try {
    echo "Processing with JSON Machine streaming...\n";
    
    // For large single JSON objects, we can iterate through keys
    $items = Items::fromFile($jsonFile);
    
    $keyCount = 0;
    foreach ($items as $key => $value) {
        $keyCount++;
        
        // Show progress every 100 keys
        if ($keyCount % 100 === 0) {
            $profiler->recordMemoryUsage();
            echo "Processed $keyCount keys, Current memory: " . 
                 MemoryProfiler::formatBytes(memory_get_usage(true)) . "\n";
        }
        
        // For demonstration, just count - in real usage you'd process the data
        if ($keyCount >= 1000) { // Limit for demo purposes
            echo "Limiting to first 1000 keys for demo...\n";
            break;
        }
    }
    
    $results = $profiler->getResults();
    
    echo "\nResults:\n";
    echo "Keys processed: " . number_format($keyCount) . "\n";
    echo "Execution time: " . $results['execution_time_formatted'] . "\n";
    echo "Memory used: " . $results['memory_used_formatted'] . "\n";
    echo "Peak memory: " . $results['peak_memory_formatted'] . "\n";
    
    echo "\nSuccess! Large file processed with minimal memory usage.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
