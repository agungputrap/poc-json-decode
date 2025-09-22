# Complete Step-by-Step Code Explanation

## Project Overview
This project compares performance between two JSON parsing approaches:
1. **Standard `json_decode()`** - loads entire file into memory
2. **JSON Machine streaming** - processes JSON without loading entire file

---

## 1. Project Structure and Dependencies

### composer.json - Project Configuration
```json
{
    "name": "jatisampurna/poc-json-decode",
    "require": {
        "php": "^7.3|^8.0",
        "halaxa/json-machine": "^1.1"  // Streaming JSON parser library
    },
    "autoload": {
        "psr-4": {
            "PocJsonDecode\\": "src/"    // Auto-load our classes
        }
    }
}
```

**What this does:**
- Sets up PHP project with Composer dependency management
- Installs JSON Machine library for streaming JSON parsing
- Configures autoloading so we can use `use PocJsonDecode\ClassName`

---

## 2. Core Utility Classes

### src/MemoryProfiler.php - Memory and Time Tracking

Let me show you the key parts:

```php
class MemoryProfiler
{
    private $startTime;
    private $startMemory;
    private $peakMemory;
    
    public function __construct()
    {
        $this->reset();  // Start tracking immediately
    }
    
    public function reset()
    {
        $this->startTime = microtime(true);           // Current time in microseconds
        $this->startMemory = memory_get_usage(true);  // Current memory usage
        $this->peakMemory = $this->startMemory;       // Track highest memory
    }
}
```

**Step by step:**
1. **Constructor**: Automatically starts timing and memory tracking
2. **`microtime(true)`**: Gets current time with microsecond precision
3. **`memory_get_usage(true)`**: Gets actual memory allocated by PHP
4. **Peak tracking**: Monitors highest memory usage during execution

```php
public function getExecutionTime()
{
    return microtime(true) - $this->startTime;  // Current time - start time
}

public function getMemoryUsed()
{
    return memory_get_usage(true) - $this->startMemory;  // Current - start
}

public function getPeakMemoryUsed()
{
    $currentPeak = memory_get_peak_usage(true);  // PHP's built-in peak tracker
    return max($this->peakMemory, $currentPeak) - $this->startMemory;
}
```

**How memory tracking works:**
1. Records memory at start
2. Calculates difference from start to get "additional memory used"
3. Tracks peak usage to see maximum memory consumption

```php
public static function formatBytes($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;  // Convert to next unit
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}
```

**Formatting example:**
- Input: 1,048,576 bytes
- Loop: 1,048,576 ÷ 1024 = 1024 KB ÷ 1024 = 1 MB
- Output: "1 MB"

---

## 3. Results Display and Analysis

### src/BenchmarkResults.php - Formatting and Comparison

```php
class BenchmarkResults
{
    private $results = [];  // Store results for each method
    
    public function addResult($method, $profilerResults, $additionalData = [])
    {
        // Combine profiler data with additional info
        $this->results[$method] = array_merge($profilerResults, $additionalData);
    }
}
```

**How comparison works:**
```php
private function displayComparison()
{
    $methods = array_keys($this->results);  // ['json_decode', 'json_machine']
    
    if (count($methods) >= 2) {
        $method1 = $methods[0];  // 'json_decode'
        $method2 = $methods[1];  // 'json_machine'
        
        $time1 = $this->results[$method1]['execution_time'];
        $time2 = $this->results[$method2]['execution_time'];
        
        // Calculate percentage improvement
        if ($time1 < $time2) {
            $improvement = ($time2 - $time1) / $time1 * 100;
            echo sprintf("%s is %.2f%% faster than %s\n", 
                ucfirst($method1), $improvement, $method2);
        }
    }
}
```

**Calculation example:**
- json_decode: 0.2 seconds
- json_machine: 2.0 seconds
- Improvement: (2.0 - 0.2) / 0.2 * 100 = 900%
- Output: "Json_decode is 900% faster than json_machine"

---

## 4. Main Benchmark Script - benchmark.php

### Class Structure and Setup

```php
class JsonBenchmark
{
    private $jsonFile;
    private $results;
    
    public function __construct($jsonFile)
    {
        $this->jsonFile = $jsonFile;
        $this->results = new BenchmarkResults();  // Create results manager
        
        if (!file_exists($jsonFile)) {
            throw new Exception("JSON file not found: $jsonFile");
        }
    }
}
```

### Memory Safety Check

```php
private function getMemoryLimitInBytes()
{
    $memoryLimit = ini_get('memory_limit');  // Get PHP setting like "128M"
    
    if ($memoryLimit == -1) {
        return PHP_INT_MAX;  // No limit set
    }
    
    $unit = strtolower(substr($memoryLimit, -1));  // Get last character: 'm'
    $value = (int) substr($memoryLimit, 0, -1);    // Get number part: 128
    
    switch ($unit) {
        case 'g': return $value * 1024 * 1024 * 1024;  // Gigabytes
        case 'm': return $value * 1024 * 1024;          // Megabytes  
        case 'k': return $value * 1024;                 // Kilobytes
        default: return $value;                         // Bytes
    }
}
```

**Example conversion:**
- Input: "256M"
- `$unit = 'm'`, `$value = 256`
- Result: 256 * 1024 * 1024 = 268,435,456 bytes

### Standard json_decode() Benchmark

```php
public function benchmarkStandardJsonDecode()
{
    echo "Running standard json_decode() benchmark...\n";
    
    try {
        // Safety check before loading file
        $fileSize = filesize($this->jsonFile);
        $memoryLimit = $this->getMemoryLimitInBytes();
        
        if ($fileSize > $memoryLimit * 0.25) {  // 25% safety margin
            echo "WARNING: File too large for memory limit\n";
            return false;  // Skip this test
        }
        
        $profiler = new MemoryProfiler();  // Start tracking
        
        // STEP 1: Load entire file into memory
        $jsonContent = file_get_contents($this->jsonFile);
        $dataSize = strlen($jsonContent);
        
        // STEP 2: Reset profiler to focus on JSON parsing only
        $profiler->reset();
        
        // STEP 3: Parse JSON (this is what we're measuring)
        $data = json_decode($jsonContent, true);  // true = return array not object
        
        // STEP 4: Check for JSON errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error: ' . json_last_error_msg());
        }
        
        // STEP 5: Count items for comparison
        $itemCount = $this->countItems($data);
        
        // STEP 6: Get performance results
        $results = $profiler->getResults();
        $results['items_processed'] = $itemCount;
        $results['data_size'] = MemoryProfiler::formatBytes($dataSize);
        
        // STEP 7: Store results
        $this->results->addResult('json_decode', $results);
        
        return true;
    } catch (Exception $e) {
        echo "Failed: " . $e->getMessage() . "\n";
        return false;
    }
}
```

**What happens in memory:**
1. **Before**: Memory usage = baseline
2. **After file_get_contents()**: Memory += file size (46MB file = +46MB memory)
3. **After json_decode()**: Memory += parsed data structures (~2x file size)
4. **Total**: Often 3-4x the file size in memory

### JSON Machine Streaming Benchmark

```php
public function benchmarkJsonMachine()
{
    echo "Running JSON Machine streaming benchmark...\n";
    
    $profiler = new MemoryProfiler();  // Start tracking
    $itemCount = 0;
    
    try {
        // STEP 1: Create streaming parser (doesn't load file yet)
        $items = Items::fromFile($this->jsonFile);
        
        // STEP 2: Iterate through JSON stream
        foreach ($items as $key => $item) {
            $itemCount++;
            
            // STEP 3: Process data without loading everything
            if (is_array($item)) {
                $itemCount += count($item);  // Count array elements
            } elseif (is_object($item)) {
                $objectArray = (array) $item;
                $itemCount += count($objectArray);  // Count object properties
            }
            
            // STEP 4: Record memory usage periodically
            if ($itemCount % 1000 === 0) {
                $profiler->recordMemoryUsage();
            }
            
            // STEP 5: Safety limit for very large objects
            if ($itemCount > 100000) {
                echo "Large object detected, limiting for benchmark...\n";
                break;
            }
        }
        
        // STEP 6: Get results
        $results = $profiler->getResults();
        $results['items_processed'] = $itemCount;
        
        // STEP 7: Store results
        $this->results->addResult('json_machine', $results);
        
    } catch (Exception $e) {
        throw new Exception('JSON Machine error: ' . $e->getMessage());
    }
}
```

**Key difference in memory usage:**
- **json_decode()**: Loads entire file → parses everything → stores in memory
- **JSON Machine**: Reads file in chunks → parses incrementally → processes piece by piece

---

## 5. How JSON Machine Streaming Works

### Internal Process (Simplified):

```php
// What JSON Machine does internally:

// 1. Open file handle (no memory used for content)
$handle = fopen($jsonFile, 'r');

// 2. Read file in small chunks
while (!feof($handle)) {
    $chunk = fread($handle, 8192);  // Read 8KB at a time
    
    // 3. Parse chunk and identify JSON tokens
    $tokens = parseJsonTokens($chunk);
    
    // 4. When complete object/array found, yield it
    if ($completeItem = findCompleteItem($tokens)) {
        yield $key => $completeItem;  // Return to foreach loop
        
        // 5. Free memory for this item
        unset($completeItem);
    }
}
```

**Memory comparison:**
- **json_decode()**: Peak memory = entire file + parsed data
- **JSON Machine**: Peak memory = largest single item + buffer

---

## 6. Execution Flow - runComparison()

```php
public function runComparison()
{
    // STEP 1: Display file information
    echo "File: " . $this->jsonFile . "\n";
    echo "File size: " . MemoryProfiler::formatBytes(filesize($this->jsonFile)) . "\n";
    echo "Memory limit: " . MemoryProfiler::formatBytes($this->getMemoryLimitInBytes()) . "\n\n";
    
    // STEP 2: Warm up PHP (stabilize performance)
    echo "Warming up PHP...\n";
    for ($i = 0; $i < 3; $i++) {
        json_decode('{"test": "warmup"}', true);  // Small JSON to warm up
    }
    
    try {
        // STEP 3: Run standard json_decode test
        $standardSuccess = $this->benchmarkStandardJsonDecode();
        
        // STEP 4: Clean up memory and wait
        sleep(1);  // Let system clean up
        
        // STEP 5: Run JSON Machine test
        $this->benchmarkJsonMachine();
        
        // STEP 6: Display results
        $this->results->displayResults();
        
        // STEP 7: Show recommendation
        $this->showRecommendation($standardSuccess);
        
        // STEP 8: Export results to JSON file
        $resultsDir = __DIR__ . '/results';
        if (!is_dir($resultsDir)) {
            mkdir($resultsDir, 0755, true);  // Create directory if needed
        }
        $resultsFile = $resultsDir . '/benchmark_results_' . date('Y-m-d_H-i-s') . '.json';
        $this->results->exportToJson($resultsFile);
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
```

---

## 7. Command Line Execution

### Script Entry Point:
```php
// Main execution starts here
if ($argc < 2) {  // Check command line arguments
    echo "Usage: php benchmark.php <json_file>\n";
    exit(1);
}

$jsonFile = $argv[1];  // Get filename from command line

// Convert relative path to absolute
if (strpos($jsonFile, '/') !== 0) {  // If not absolute path
    $jsonFile = __DIR__ . '/' . $jsonFile;  // Make it relative to script
}

try {
    $benchmark = new JsonBenchmark($jsonFile);  // Create benchmark object
    $benchmark->runComparison();                // Run the comparison
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
```

**Execution example:**
```bash
php benchmark.php data/sample_data.json
```

**What happens:**
1. PHP loads the script
2. Creates JsonBenchmark object with your file
3. Runs both benchmark methods
4. Compares results and shows recommendation
5. Saves detailed results to JSON file

---

## 8. Real Performance Results Explained

From your actual test:
```
Method: JSON_DECODE
Execution Time: 218.61 ms        ← Very fast
Memory Used: 266 MB               ← High memory usage
Peak Memory: 266 MB
Items Processed: 1

Method: JSON_MACHINE  
Execution Time: 2.271 s           ← Slower (10x)
Memory Used: 6 MB                 ← Low memory usage
Peak Memory: 52.56 MB             ← Much lower peak (5x less)
```

**Why these differences:**
1. **Speed**: json_decode() is optimized C code, JSON Machine is PHP with overhead
2. **Memory**: json_decode() loads everything, JSON Machine processes incrementally
3. **Trade-off**: Speed vs Memory efficiency

**When to use each:**
- **json_decode()**: Small/medium files, speed critical, plenty of memory
- **JSON Machine**: Large files, memory constrained, can accept slower processing

---

## 9. Key Learning Points

### Memory Management:
- PHP's `memory_get_usage()` tracks allocated memory
- `memory_get_peak_usage()` shows maximum usage during execution
- File size ≠ memory usage (parsed data often uses 2-3x file size)

### Performance Measurement:
- `microtime(true)` gives microsecond precision timing
- Always "warm up" for consistent results
- Measure only the operation you care about (reset profiler)

### Streaming vs Loading:
- **Loading**: Fast but memory-intensive
- **Streaming**: Memory-efficient but slower
- Choice depends on your constraints and requirements

This project demonstrates these concepts practically with real performance data!
