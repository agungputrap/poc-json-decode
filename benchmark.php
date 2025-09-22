<?php

require_once __DIR__ . '/vendor/autoload.php';

use JsonMachine\Items;
use PocJsonDecode\MemoryProfiler;
use PocJsonDecode\BenchmarkResults;

/**
 * JSON Decode Performance Comparison Tool
 * 
 * This script compares the performance between:
 * 1. Standard json_decode() - loads entire file into memory
 * 2. JSON Machine streaming - processes JSON without loading entire file
 */

class JsonBenchmark
{
    private $jsonFile;
    private $results;
    
    public function __construct($jsonFile)
    {
        $this->jsonFile = $jsonFile;
        $this->results = new BenchmarkResults();
        
        if (!file_exists($jsonFile)) {
            throw new Exception("JSON file not found: $jsonFile");
        }
    }
    
    /**
     * Run standard json_decode() benchmark
     */
    public function benchmarkStandardJsonDecode()
    {
        echo "Running standard json_decode() benchmark...\n";
        
        try {
            // Check if file is too large for available memory
            $fileSize = filesize($this->jsonFile);
            $memoryLimit = $this->getMemoryLimitInBytes();
            
            if ($fileSize > $memoryLimit * 0.25) { // Use 25% as safety margin for overhead
                echo "WARNING: File size (" . MemoryProfiler::formatBytes($fileSize) . 
                     ") is too large for current memory limit (" . 
                     MemoryProfiler::formatBytes($memoryLimit) . ").\n";
                echo "Skipping standard json_decode() test to prevent memory exhaustion.\n\n";
                return false;
            }
            
            $profiler = new MemoryProfiler();
            
            // Read entire file into memory with memory monitoring
            $currentMemory = memory_get_usage(true);
            $availableMemory = $memoryLimit - $currentMemory;
            
            if ($fileSize > $availableMemory * 0.5) {
                echo "WARNING: Insufficient available memory for safe json_decode() execution.\n";
                echo "Current memory: " . MemoryProfiler::formatBytes($currentMemory) . "\n";
                echo "Available memory: " . MemoryProfiler::formatBytes($availableMemory) . "\n";
                echo "Required memory (estimated): " . MemoryProfiler::formatBytes($fileSize * 3) . "\n";
                echo "Skipping standard json_decode() test.\n\n";
                return false;
            }
            
            $jsonContent = file_get_contents($this->jsonFile);
            $dataSize = strlen($jsonContent);
            
            // Reset profiler after file read to focus on JSON parsing
            $profiler->reset();
            
            // Decode JSON
            $data = json_decode($jsonContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON decode error: ' . json_last_error_msg());
            }
            
            $itemCount = $this->countItems($data);
            
            $results = $profiler->getResults();
            $results['items_processed'] = $itemCount;
            $results['data_size'] = MemoryProfiler::formatBytes($dataSize);
            $results['method'] = 'Standard json_decode()';
            
            $this->results->addResult('json_decode', $results);
            
            // Clear memory
            unset($data);
            unset($jsonContent);
            
            echo "Standard json_decode() completed.\n\n";
            return true;
            
        } catch (Error $e) {
            // Handle PHP Fatal Errors (like memory exhaustion)
            if (strpos($e->getMessage(), 'memory') !== false || 
                strpos($e->getMessage(), 'Memory') !== false) {
                echo "MEMORY EXHAUSTED: json_decode() ran out of memory.\n";
                echo "Error: " . $e->getMessage() . "\n";
                echo "Try increasing memory limit or use JSON Machine streaming.\n\n";
            } else {
                echo "FATAL ERROR in json_decode(): " . $e->getMessage() . "\n\n";
            }
            return false;
        } catch (Exception $e) {
            // Handle other exceptions
            if (strpos($e->getMessage(), 'memory') !== false || 
                strpos($e->getMessage(), 'Memory') !== false) {
                echo "MEMORY EXHAUSTED: json_decode() ran out of memory.\n";
                echo "Error: " . $e->getMessage() . "\n";
                echo "Try increasing memory limit or use JSON Machine streaming.\n\n";
            } else {
                echo "ERROR in json_decode(): " . $e->getMessage() . "\n\n";
            }
            return false;
        }
    }
    
    /**
     * Run JSON Machine streaming benchmark
     */
    public function benchmarkJsonMachine()
    {
        echo "Running JSON Machine streaming benchmark...\n";
        
        $profiler = new MemoryProfiler();
        $itemCount = 0;
        
        try {
            // Check available memory and file size
            $fileSize = filesize($this->jsonFile);
            $memoryLimit = $this->getMemoryLimitInBytes();
            $currentMemory = memory_get_usage(true);
            $availableMemory = $memoryLimit - $currentMemory;
            
            // JSON Machine needs some memory for parsing buffers
            $estimatedMemoryNeeded = max($fileSize * 0.1, 50 * 1024 * 1024); // At least 50MB or 10% of file size
            
            if ($estimatedMemoryNeeded > $availableMemory) {
                echo "WARNING: Insufficient memory for JSON Machine streaming.\n";
                echo "Current memory: " . MemoryProfiler::formatBytes($currentMemory) . "\n";
                echo "Available memory: " . MemoryProfiler::formatBytes($availableMemory) . "\n";
                echo "Estimated memory needed: " . MemoryProfiler::formatBytes($estimatedMemoryNeeded) . "\n";
                echo "Try increasing memory limit to at least " . 
                     MemoryProfiler::formatBytes($currentMemory + $estimatedMemoryNeeded) . "\n\n";
                return false;
            }
            
            // Stream JSON data
            $items = Items::fromFile($this->jsonFile);
            
            foreach ($items as $key => $item) {
                $itemCount++;
                
                // Count items differently based on data structure
                if (is_array($item)) {
                    // If it's an array, count the elements
                    $itemCount += count($item);
                } elseif (is_object($item)) {
                    // If it's an object, count the properties
                    $objectArray = (array) $item;
                    $itemCount += count($objectArray);
                }
                
                // Record memory usage periodically
                if ($itemCount % 1000 === 0) {
                    $profiler->recordMemoryUsage();
                    
                    // Check if we're approaching memory limit
                    $currentMem = memory_get_usage(true);
                    if ($currentMem > $memoryLimit * 0.9) {
                        echo "WARNING: Approaching memory limit during streaming.\n";
                        echo "Current memory: " . MemoryProfiler::formatBytes($currentMem) . "\n";
                        echo "Stopping processing to prevent memory exhaustion.\n";
                        break;
                    }
                }
                
                // For very large single objects, limit processing for benchmark purposes
                if ($itemCount > 100000) {
                    echo "Large object detected, limiting processing for benchmark...\n";
                    break;
                }
            }
            
        } catch (Error $e) {
            // Handle PHP Fatal Errors (like memory exhaustion)
            if (strpos($e->getMessage(), 'memory') !== false || 
                strpos($e->getMessage(), 'Memory') !== false) {
                echo "MEMORY EXHAUSTED: JSON Machine ran out of memory during streaming.\n";
                echo "Error: " . $e->getMessage() . "\n";
                echo "This file might be too large even for streaming. Consider:\n";
                echo "1. Increasing memory limit further\n";
                echo "2. Processing file in smaller chunks\n";
                echo "3. Using a different approach for this file size\n\n";
            } else {
                echo "FATAL ERROR in JSON Machine: " . $e->getMessage() . "\n\n";
            }
            return false;
        } catch (Exception $e) {
            // Handle other exceptions
            if (strpos($e->getMessage(), 'memory') !== false || 
                strpos($e->getMessage(), 'Memory') !== false) {
                echo "MEMORY EXHAUSTED: JSON Machine ran out of memory during streaming.\n";
                echo "Error: " . $e->getMessage() . "\n";
                echo "This file might be too large even for streaming. Consider:\n";
                echo "1. Increasing memory limit further\n";
                echo "2. Processing file in smaller chunks\n";
                echo "3. Using a different approach for this file size\n\n";
            } else {
                echo "ERROR in JSON Machine: " . $e->getMessage() . "\n\n";
            }
            return false;
        }
        
        $fileSize = filesize($this->jsonFile);
        
        $results = $profiler->getResults();
        $results['items_processed'] = $itemCount;
        $results['data_size'] = MemoryProfiler::formatBytes($fileSize);
        $results['method'] = 'JSON Machine Streaming';
        
        $this->results->addResult('json_machine', $results);
        
        echo "JSON Machine streaming completed.\n\n";
        return true;
    }
    
    /**
     * Count items in decoded JSON data
     */
    private function countItems($data)
    {
        if (is_array($data)) {
            $count = 0;
            foreach ($data as $item) {
                if (is_array($item) || is_object($item)) {
                    $count++;
                } else {
                    $count++;
                }
            }
            return $count;
        }
        
        return 1; // Single object/value
    }
    
    /**
     * Get PHP memory limit in bytes
     */
    private function getMemoryLimitInBytes()
    {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit == -1) {
            return PHP_INT_MAX; // No limit
        }
        
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }
    
    /**
     * Run both benchmarks and display results
     */
    public function runComparison()
    {
        echo "JSON Decode Performance Comparison\n";
        echo "File: " . $this->jsonFile . "\n";
        echo "File size: " . MemoryProfiler::formatBytes(filesize($this->jsonFile)) . "\n";
        echo "Memory limit: " . MemoryProfiler::formatBytes($this->getMemoryLimitInBytes()) . "\n\n";
        
        // Warmup PHP
        echo "Warming up PHP...\n";
        for ($i = 0; $i < 3; $i++) {
            json_decode('{"test": "warmup"}', true);
        }
        echo "Warmup completed.\n\n";
        
        $standardSuccess = false;
        $streamingSuccess = false;
        
        try {
            // Run standard json_decode
            $standardSuccess = $this->benchmarkStandardJsonDecode();
            
            // Give system a moment to clean up
            sleep(1);
            
            // Run JSON Machine streaming
            $streamingSuccess = $this->benchmarkJsonMachine();
            
            // Check if we have any results to display
            $resultsData = $this->results->getResults();
            if (empty($resultsData)) {
                echo str_repeat("=", 80) . "\n";
                echo "NO SUCCESSFUL BENCHMARKS\n";
                echo str_repeat("=", 80) . "\n\n";
                echo "Both json_decode() and JSON Machine failed due to memory constraints.\n";
                echo "Recommendations:\n";
                echo "1. Increase PHP memory limit: php -d memory_limit=512M benchmark.php\n";
                echo "2. Use a smaller test file\n";
                echo "3. Process the file in chunks\n";
                echo "4. Consider using a streaming JSON parser designed for very large files\n\n";
                return;
            }
            
            // Display results
            $this->results->displayResults();
            
            // Show recommendation based on results
            $this->showRecommendation($standardSuccess, $streamingSuccess);
            
            // Export results only if we have some
            $resultsDir = __DIR__ . '/results';
            if (!is_dir($resultsDir)) {
                mkdir($resultsDir, 0755, true);
            }
            $resultsFile = $resultsDir . '/benchmark_results_' . date('Y-m-d_H-i-s') . '.json';
            $this->results->exportToJson($resultsFile);
            
        } catch (Exception $e) {
            echo "Unexpected error during benchmark: " . $e->getMessage() . "\n";
            exit(1);
        } catch (Error $e) {
            echo "Fatal error during benchmark: " . $e->getMessage() . "\n";
            echo "This is likely a memory-related issue. Try increasing memory limit.\n";
            exit(1);
        }
    }
    
    /**
     * Show recommendation based on benchmark results
     */
    private function showRecommendation($standardSuccess, $streamingSuccess)
    {
        echo str_repeat("=", 80) . "\n";
        echo "RECOMMENDATION\n";
        echo str_repeat("=", 80) . "\n\n";
        
        if (!$standardSuccess && !$streamingSuccess) {
            echo "RESULT: Both methods failed due to memory constraints\n";
            echo "RECOMMENDATION: \n";
            echo "• Increase memory limit significantly (try 512M or 1G)\n";
            echo "• Use smaller test files\n";
            echo "• Consider chunked processing approaches\n\n";
            return;
        }
        
        if (!$standardSuccess && $streamingSuccess) {
            echo "RESULT: JSON Machine succeeded where json_decode() failed\n";
            echo "RECOMMENDATION: Use JSON Machine streaming\n";
            echo "Reason: File is too large for standard json_decode() with current memory limits.\n";
            echo "JSON Machine allows processing large files with minimal memory usage.\n\n";
            return;
        }
        
        if ($standardSuccess && !$streamingSuccess) {
            echo "RESULT: Standard json_decode() succeeded but JSON Machine failed\n";
            echo "RECOMMENDATION: Use standard json_decode()\n";
            echo "Note: This is unusual - streaming should typically use less memory.\n";
            echo "Consider investigating JSON structure or increasing memory slightly.\n\n";
            return;
        }
        
        // Both succeeded - detailed comparison
        $results = $this->results->getResults();
        if (count($results) >= 2) {
            $jsonDecodeTime = $results['json_decode']['execution_time'];
            $jsonDecodeMem = $results['json_decode']['peak_memory'];
            $jsonMachineTime = $results['json_machine']['execution_time'];
            $jsonMachineMem = $results['json_machine']['peak_memory'];
            
            $memoryRatio = $jsonMachineMem / $jsonDecodeMem;
            $timeRatio = $jsonMachineTime / $jsonDecodeTime;
            
            if ($memoryRatio < 0.5 && $timeRatio < 2.0) {
                echo "RECOMMENDATION: Use JSON Machine streaming\n";
                echo "Reason: Significant memory savings with acceptable performance overhead.\n";
            } elseif ($timeRatio > 3.0 && $memoryRatio > 0.8) {
                echo "RECOMMENDATION: Use standard json_decode()\n";
                echo "Reason: Better performance with manageable memory usage.\n";
            } else {
                echo "RECOMMENDATION: Consider your priorities\n";
                echo "- Use json_decode() if speed is critical and memory is available\n";
                echo "- Use JSON Machine if memory efficiency is important\n";
            }
        }
        
        echo "\n";
    }
}

// Main execution
if ($argc < 2) {
    echo "Usage: php benchmark.php <json_file>\n";
    echo "Example: php benchmark.php data/sample_data.json\n";
    exit(1);
}

$jsonFile = $argv[1];

// Convert relative path to absolute
if (strpos($jsonFile, '/') !== 0) {
    $jsonFile = __DIR__ . '/' . $jsonFile;
}

try {
    $benchmark = new JsonBenchmark($jsonFile);
    $benchmark->runComparison();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
