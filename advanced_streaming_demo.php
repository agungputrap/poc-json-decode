<?php

require_once __DIR__ . '/vendor/autoload.php';

use JsonMachine\Items;
use JsonMachine\JsonDecoder\TokenDecoder;
use PocJsonDecode\MemoryProfiler;

echo "JSON Machine - Advanced Streaming Demo\n";
echo "=====================================\n\n";

$jsonFile = __DIR__ . '/data/sample_data.json';
$fileSize = filesize($jsonFile);

echo "File: " . basename($jsonFile) . "\n";
echo "Size: " . number_format($fileSize / 1024 / 1024, 2) . " MB\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n\n";

$profiler = new MemoryProfiler();

try {
    echo "Attempting to process with JSON Machine using different approaches...\n\n";
    
    // Approach 1: Try to read the root object keys
    echo "Approach 1: Reading root object keys...\n";
    try {
        $items = Items::fromFile($jsonFile);
        
        $keyCount = 0;
        $maxKeys = 10; // Limit for safety
        
        foreach ($items as $key => $value) {
            $keyCount++;
            echo "Key $keyCount: '$key' (type: " . gettype($value) . ")\n";
            
            if (is_array($value)) {
                echo "  Array with " . count($value) . " items\n";
            } elseif (is_string($value)) {
                echo "  String length: " . strlen($value) . "\n";
            }
            
            $profiler->recordMemoryUsage();
            echo "  Current memory: " . MemoryProfiler::formatBytes(memory_get_usage(true)) . "\n";
            
            if ($keyCount >= $maxKeys) {
                echo "  Limiting to first $maxKeys keys for safety...\n";
                break;
            }
        }
        
        echo "Approach 1 completed successfully!\n";
        $approach1Success = true;
        
    } catch (Exception $e) {
        echo "Approach 1 failed: " . $e->getMessage() . "\n";
        $approach1Success = false;
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // Approach 2: Try to read specific nested arrays
    echo "Approach 2: Reading specific nested arrays...\n";
    try {
        // Look for arrays within the object that we can stream
        $nestedItems = Items::fromFile($jsonFile, ['pointer' => '/responinqueryNIB/dataNIB/pemegang_saham']);
        
        $itemCount = 0;
        $maxItems = 20;
        
        foreach ($nestedItems as $index => $item) {
            $itemCount++;
            echo "Item $itemCount: ";
            
            if (isset($item['nama_pemegang_saham'])) {
                echo "Pemegang Saham: " . $item['nama_pemegang_saham'];
            } else {
                echo "Data structure: " . json_encode(array_keys($item));
            }
            echo "\n";
            
            $profiler->recordMemoryUsage();
            
            if ($itemCount >= $maxItems) {
                echo "Limiting to first $maxItems items for safety...\n";
                break;
            }
        }
        
        echo "Approach 2 completed successfully!\n";
        $approach2Success = true;
        
    } catch (Exception $e) {
        echo "Approach 2 failed: " . $e->getMessage() . "\n";
        $approach2Success = false;
    }
    
    $results = $profiler->getResults();
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "RESULTS:\n";
    echo "Execution time: " . $results['execution_time_formatted'] . "\n";
    echo "Memory used: " . $results['memory_used_formatted'] . "\n";
    echo "Peak memory: " . $results['peak_memory_formatted'] . "\n";
    
    if ($approach1Success || $approach2Success) {
        echo "\n✅ SUCCESS: JSON Machine can process your sample_data.json!\n";
        if ($approach1Success) echo "✓ Root level streaming works\n";
        if ($approach2Success) echo "✓ Nested array streaming works\n";
    } else {
        echo "\n❌ Both approaches failed with current memory limits\n";
        echo "💡 Try increasing memory limit or use file chunking\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
