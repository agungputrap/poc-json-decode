<?php

require_once __DIR__ . '/vendor/autoload.php';

use JsonMachine\Items;
use PocJsonDecode\MemoryProfiler;

echo "=== STEP-BY-STEP CODE WALKTHROUGH ===\n\n";

echo "STEP 1: Initialize Memory Profiler\n";
echo "-----------------------------------\n";
$profiler = new MemoryProfiler();
echo "✓ Created MemoryProfiler object\n";
echo "✓ Start time recorded: " . date('H:i:s.u') . "\n";
echo "✓ Start memory: " . MemoryProfiler::formatBytes(memory_get_usage(true)) . "\n\n";

echo "STEP 2: Demonstrate json_decode() approach\n";
echo "------------------------------------------\n";
$smallFile = __DIR__ . '/data/test_array.json';
echo "Using small test file: " . basename($smallFile) . "\n";
echo "File size: " . MemoryProfiler::formatBytes(filesize($smallFile)) . "\n\n";

echo "2a. Load entire file into memory:\n";
$memoryBefore = memory_get_usage(true);
echo "   Memory before: " . MemoryProfiler::formatBytes($memoryBefore) . "\n";

$jsonContent = file_get_contents($smallFile);
$memoryAfterLoad = memory_get_usage(true);
echo "   Memory after file_get_contents(): " . MemoryProfiler::formatBytes($memoryAfterLoad) . "\n";
echo "   Memory increase: " . MemoryProfiler::formatBytes($memoryAfterLoad - $memoryBefore) . "\n\n";

echo "2b. Parse JSON with json_decode():\n";
$profiler->reset(); // Start measuring from here
$data = json_decode($jsonContent, true);
$parseTime = $profiler->getExecutionTime();
$memoryAfterParse = memory_get_usage(true);

echo "   Parsed JSON in: " . MemoryProfiler::formatTime($parseTime) . "\n";
echo "   Memory after parsing: " . MemoryProfiler::formatBytes($memoryAfterParse) . "\n";
echo "   Total items: " . count($data) . "\n";
echo "   Data structure: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

echo "STEP 3: Demonstrate JSON Machine streaming\n";
echo "------------------------------------------\n";
$profiler->reset();
$memoryBeforeStream = memory_get_usage(true);
echo "Memory before streaming: " . MemoryProfiler::formatBytes($memoryBeforeStream) . "\n\n";

echo "3a. Create streaming iterator (no file loaded yet):\n";
$items = Items::fromFile($smallFile);
$memoryAfterIterator = memory_get_usage(true);
echo "   Memory after creating iterator: " . MemoryProfiler::formatBytes($memoryAfterIterator) . "\n";
echo "   Memory increase: " . MemoryProfiler::formatBytes($memoryAfterIterator - $memoryBeforeStream) . "\n\n";

echo "3b. Process items one by one:\n";
$itemCount = 0;
foreach ($items as $key => $item) {
    $itemCount++;
    $currentMemory = memory_get_usage(true);
    
    echo "   Item $itemCount (key: $key):\n";
    echo "      Content: " . json_encode($item) . "\n";
    echo "      Memory now: " . MemoryProfiler::formatBytes($currentMemory) . "\n";
    
    $profiler->recordMemoryUsage();
}

$streamTime = $profiler->getExecutionTime();
echo "\n   Streaming completed in: " . MemoryProfiler::formatTime($streamTime) . "\n";
echo "   Total items processed: $itemCount\n";
echo "   Peak memory during streaming: " . MemoryProfiler::formatBytes($profiler->getPeakMemoryUsed() + $memoryBeforeStream) . "\n\n";

echo "STEP 4: Compare the approaches\n";
echo "------------------------------\n";
echo "json_decode() approach:\n";
echo "  ✓ Speed: " . MemoryProfiler::formatTime($parseTime) . "\n";
echo "  ✓ Memory efficiency: Loads entire file at once\n";
echo "  ✓ Use case: When file fits comfortably in memory\n\n";

echo "JSON Machine approach:\n";
echo "  ✓ Speed: " . MemoryProfiler::formatTime($streamTime) . "\n";
echo "  ✓ Memory efficiency: Processes item by item\n";
echo "  ✓ Use case: Large files or memory-constrained environments\n\n";

echo "STEP 5: Memory usage comparison\n";
echo "-------------------------------\n";
$jsonDecodeMemory = $memoryAfterParse - $memoryBefore;
$streamingMemory = $profiler->getPeakMemoryUsed();

echo "json_decode() memory usage: " . MemoryProfiler::formatBytes($jsonDecodeMemory) . "\n";
echo "JSON Machine memory usage: " . MemoryProfiler::formatBytes($streamingMemory) . "\n";

if ($streamingMemory < $jsonDecodeMemory) {
    $savings = (($jsonDecodeMemory - $streamingMemory) / $jsonDecodeMemory) * 100;
    echo "Memory savings with JSON Machine: " . round($savings, 1) . "%\n";
} else {
    echo "json_decode() was more memory efficient for this small file\n";
}

echo "\n=== WALKTHROUGH COMPLETE ===\n";
echo "This demonstrates the core concepts of the benchmark project!\n";
