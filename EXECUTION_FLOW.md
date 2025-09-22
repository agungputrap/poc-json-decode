# Execution Flow Diagram

## Overall Process Flow

```
START
  ↓
[1] User runs: php benchmark.php data/sample_data.json
  ↓
[2] Script validates file exists
  ↓
[3] Create JsonBenchmark object
  ↓
[4] runComparison() method starts
  ↓
[5] Display file info and memory limits
  ↓
[6] Warm up PHP (run small JSON decode 3 times)
  ↓
[7] BRANCH: Try json_decode() benchmark
  ↓
[8] Check if file size < memory limit * 0.25
  ↓
[9a] IF TOO LARGE → Skip json_decode, show warning
[9b] IF OK → Run json_decode benchmark
  ↓
[10] Sleep 1 second (let system clean up)
  ↓
[11] Run JSON Machine benchmark
  ↓
[12] Display comparison results
  ↓
[13] Show recommendation based on results
  ↓
[14] Export results to JSON file
  ↓
END
```

## json_decode() Benchmark Flow

```
benchmarkStandardJsonDecode() START
  ↓
[1] Check file size vs memory limit
  ↓
[2] IF too large → return false (skip test)
  ↓
[3] Create MemoryProfiler object
  ↓
[4] Load ENTIRE file into memory with file_get_contents()
     Memory usage: BASELINE + FILE_SIZE
  ↓
[5] Reset profiler (start measuring from here)
  ↓
[6] Call json_decode($jsonContent, true)
     Memory usage: BASELINE + FILE_SIZE + PARSED_DATA
  ↓
[7] Check for JSON parsing errors
  ↓
[8] Count items in parsed data
  ↓
[9] Get profiler results (time + memory)
  ↓
[10] Store results in BenchmarkResults object
  ↓
[11] Clean up variables (unset $data, $jsonContent)
  ↓
[12] Return true (success)
```

## JSON Machine Benchmark Flow

```
benchmarkJsonMachine() START
  ↓
[1] Create MemoryProfiler object
  ↓
[2] Initialize item counter = 0
  ↓
[3] Create Items::fromFile($jsonFile) - NO MEMORY LOAD YET
  ↓
[4] START FOREACH LOOP (this triggers streaming)
  ↓
[5] For each JSON item found:
     ↓
     [5a] Increment counter
     ↓
     [5b] IF item is array → count += array size
     [5c] IF item is object → count += property count
     ↓
     [5d] IF counter % 1000 == 0 → record memory usage
     ↓
     [5e] IF counter > 100000 → break (safety limit)
     ↓
     [5f] Process next item (memory stays low)
  ↓
[6] Get profiler results
  ↓
[7] Store results with item count
  ↓
[8] Return (streaming complete)
```

## Memory Usage Patterns

### json_decode() Memory Pattern:
```
Memory
  ↑
  |     ╭─────────────╮ ← Peak usage (file + parsed data)
  |    ╱               ╲
  |   ╱                 ╲
  |  ╱                   ╲
  | ╱                     ╲
  |╱                       ╲
  └─────────────────────────→ Time
   ↑         ↑         ↑
   Start   Parse     Cleanup
```

### JSON Machine Memory Pattern:
```
Memory
  ↑
  |  ┌─┐ ┌─┐ ┌─┐ ┌─┐ ┌─┐  ← Consistent low usage
  |  │ │ │ │ │ │ │ │ │ │
  |  │ │ │ │ │ │ │ │ │ │
  |  │ │ │ │ │ │ │ │ │ │
  |  └─┘ └─┘ └─┘ └─┘ └─┘
  └──────────────────────→ Time
     ↑   ↑   ↑   ↑   ↑
    Item Item Item Item Item
     1    2    3    4    5
```

## Key Code Points with Line-by-Line Explanation

### 1. Memory Limit Check (benchmark.php lines 40-48)
```php
$fileSize = filesize($this->jsonFile);              // Get file size in bytes
$memoryLimit = $this->getMemoryLimitInBytes();      // Convert "128M" to bytes
if ($fileSize > $memoryLimit * 0.25) {              // Check if file > 25% of limit
    echo "WARNING: File size (" . 
         MemoryProfiler::formatBytes($fileSize) .    // Human readable size
         ") is too large for current memory limit (" . 
         MemoryProfiler::formatBytes($memoryLimit) . 
         ").\n";
    return false;                                    // Skip json_decode test
}
```

### 2. Profiler Reset (benchmark.php lines 55-56)
```php
$jsonContent = file_get_contents($this->jsonFile);  // Load file (uses memory)
$profiler->reset();                                 // Reset timer AFTER loading
```
**Why reset?** We want to measure only the JSON parsing time, not file loading time.

### 3. JSON Machine Streaming (benchmark.php lines 110-130)
```php
$items = Items::fromFile($this->jsonFile);          // Create iterator (no memory used)
foreach ($items as $key => $item) {                 // THIS line starts streaming
    // Each iteration processes one JSON item
    // Memory usage stays low because:
    // 1. Only current item is in memory
    // 2. Previous items are garbage collected
    // 3. File is read in small chunks
}
```

### 4. Memory Profiler Working (MemoryProfiler.php lines 33-37)
```php
public function recordMemoryUsage()
{
    $currentMemory = memory_get_usage(true);         // Get current memory
    if ($currentMemory > $this->peakMemory) {        // If higher than recorded peak
        $this->peakMemory = $currentMemory;          // Update peak memory
    }
}
```

### 5. Results Comparison (BenchmarkResults.php lines 85-95)
```php
$time1 = $this->results[$method1]['execution_time']; // Get execution times
$time2 = $this->results[$method2]['execution_time'];
$memory1 = $this->results[$method1]['peak_memory'];   // Get memory usage
$memory2 = $this->results[$method2]['peak_memory'];

if ($time1 < $time2) {                               // If method1 is faster
    $improvement = ($time2 - $time1) / $time1 * 100; // Calculate percentage
    echo sprintf("%s is %.2f%% faster than %s\n",   // Display result
        ucfirst($method1), $improvement, $method2);
}
```

## Understanding Your Results

When you ran: `php -d memory_limit=384M benchmark.php data/sample_data.json`

**What happened step by step:**

1. **File loaded**: 46.55 MB file detected
2. **Memory check**: 46.55MB < (384MB × 0.25 = 96MB) ✓ Safe to proceed
3. **json_decode() test**:
   - Loaded 46.55MB into memory
   - Parsed JSON (additional ~220MB memory used)
   - Total peak: 266MB
   - Time: 218.61 ms (very fast!)
4. **JSON Machine test**:
   - Streamed through file chunk by chunk
   - Peak memory: only 52.56MB
   - Time: 2.271s (slower but memory efficient)
5. **Comparison**:
   - json_decode(): 10x faster, 5x more memory
   - JSON Machine: 80% less memory, acceptable speed

This demonstrates the core trade-off: **Speed vs Memory Efficiency**

The project successfully proves that JSON Machine enables processing large JSON files that would otherwise exceed memory limits with traditional json_decode()!
