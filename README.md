# JSON Decode Performance Comparison

This project compares the performance between standard `json_decode()` and streaming JSON parsing using the [JSON Machine](https://github.com/halaxa/json-machine) library.

## Overview

The benchmark tests two approaches:

1. **Standard json_decode()**: Loads the entire JSON file into memory and then decodes it
2. **JSON Machine Streaming**: Processes JSON data as a stream without loading the entire file into memory

## Requirements

- PHP 7.3 or higher
- Composer

## Installation

```bash
composer install
```

## Usage

```bash
php benchmark.php <json_file>
```

Example:
```bash
php benchmark.php data/sample_data.json
```

## What it measures

- **Execution Time**: How long each method takes to process the JSON
- **Memory Usage**: Amount of memory consumed during processing
- **Peak Memory**: Maximum memory usage during the operation
- **Items Processed**: Number of JSON items/elements processed

## Expected Results

- **Standard json_decode()**: 
  - Faster execution for smaller files
  - Higher memory usage (loads entire file)
  - Memory usage scales with file size

- **JSON Machine Streaming**:
  - Consistent low memory usage regardless of file size
  - Slightly slower execution due to streaming overhead
  - Better for large files or memory-constrained environments

## Sample Output

```
JSON Decode Performance Comparison
File: /path/to/sample_data.json
File size: 48.8 MB

Running standard json_decode() benchmark...
Standard json_decode() completed.

Running JSON Machine streaming benchmark...
JSON Machine streaming completed.

================================================================================
JSON DECODE PERFORMANCE COMPARISON
================================================================================

Method: JSON_DECODE
----------------------------------------
Execution Time: 2.45 s
Memory Used: 156.2 MB
Peak Memory: 156.2 MB
Items Processed: 50,000

Method: JSON_MACHINE
----------------------------------------
Execution Time: 3.12 s
Memory Used: 2.1 MB
Peak Memory: 2.5 MB
Items Processed: 50,000

================================================================================
PERFORMANCE COMPARISON
================================================================================

EXECUTION TIME:
json_decode is 27.34% faster than json_machine

MEMORY USAGE:
json_machine uses 98.40% less memory than json_decode

Time difference: 670 ms
Memory difference: 153.7 MB
```

## Files

- `benchmark.php` - Main benchmark script
- `src/MemoryProfiler.php` - Memory and time profiling utility
- `src/BenchmarkResults.php` - Results formatting and display
- `composer.json` - Project dependencies
- `data/sample_data.json` - Sample JSON data for testing

## Use Cases

**Use json_decode() when:**
- Working with small to medium JSON files
- Speed is more important than memory usage
- You need to access the entire data structure randomly

**Use JSON Machine when:**
- Working with large JSON files (> 100MB)
- Memory usage is a concern
- Processing data sequentially
- Working in memory-constrained environments
