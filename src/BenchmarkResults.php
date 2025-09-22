<?php

namespace PocJsonDecode;

/**
 * Benchmark results formatter and displayer
 */
class BenchmarkResults
{
    private $results = [];
    
    /**
     * Add benchmark result
     */
    public function addResult($method, $profilerResults, $additionalData = [])
    {
        $this->results[$method] = array_merge($profilerResults, $additionalData);
    }
    
    /**
     * Display results in a formatted table
     */
    public function displayResults()
    {
        if (empty($this->results)) {
            echo "No benchmark results to display.\n";
            return;
        }
        
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "JSON DECODE PERFORMANCE COMPARISON\n";
        echo str_repeat("=", 80) . "\n\n";
        
        // Display results for each method
        foreach ($this->results as $method => $data) {
            echo "Method: " . strtoupper($method) . "\n";
            echo str_repeat("-", 40) . "\n";
            echo sprintf("Execution Time: %s\n", $data['execution_time_formatted']);
            echo sprintf("Memory Used: %s\n", $data['memory_used_formatted']);
            echo sprintf("Peak Memory: %s\n", $data['peak_memory_formatted']);
            
            if (isset($data['items_processed'])) {
                echo sprintf("Items Processed: %s\n", number_format($data['items_processed']));
            }
            
            if (isset($data['data_size'])) {
                echo sprintf("Data Size: %s\n", $data['data_size']);
            }
            
            echo "\n";
        }
        
        // Display comparison if we have multiple results
        if (count($this->results) > 1) {
            $this->displayComparison();
        }
    }
    
    /**
     * Display comparison between methods
     */
    private function displayComparison()
    {
        echo str_repeat("=", 80) . "\n";
        echo "PERFORMANCE COMPARISON\n";
        echo str_repeat("=", 80) . "\n\n";
        
        $methods = array_keys($this->results);
        
        if (count($methods) >= 2) {
            $method1 = $methods[0];
            $method2 = $methods[1];
            
            $time1 = $this->results[$method1]['execution_time'];
            $time2 = $this->results[$method2]['execution_time'];
            $memory1 = $this->results[$method1]['peak_memory'];
            $memory2 = $this->results[$method2]['peak_memory'];
            
            echo "EXECUTION TIME:\n";
            if ($time1 < $time2) {
                $improvement = ($time2 - $time1) / $time1 * 100;
                echo sprintf("%s is %.2f%% faster than %s\n", 
                    ucfirst($method1), $improvement, $method2);
            } else {
                $improvement = ($time1 - $time2) / $time2 * 100;
                echo sprintf("%s is %.2f%% faster than %s\n", 
                    ucfirst($method2), $improvement, $method1);
            }
            
            echo "\nMEMORY USAGE:\n";
            if ($memory1 < $memory2) {
                $improvement = ($memory2 - $memory1) / $memory1 * 100;
                echo sprintf("%s uses %.2f%% less memory than %s\n", 
                    ucfirst($method1), $improvement, $method2);
            } else {
                $improvement = ($memory1 - $memory2) / $memory2 * 100;
                echo sprintf("%s uses %.2f%% less memory than %s\n", 
                    ucfirst($method2), $improvement, $method1);
            }
            
            echo "\n";
            echo sprintf("Time difference: %s\n", 
                MemoryProfiler::formatTime(abs($time1 - $time2)));
            echo sprintf("Memory difference: %s\n", 
                MemoryProfiler::formatBytes(abs($memory1 - $memory2)));
        }
        
        echo "\n";
    }
    
    /**
     * Get raw results data
     */
    public function getResults()
    {
        return $this->results;
    }
    
    /**
     * Export results to JSON file
     */
    public function exportToJson($filename)
    {
        $json = json_encode($this->results, JSON_PRETTY_PRINT);
        file_put_contents($filename, $json);
        echo "Results exported to: " . basename($filename) . "\n";
    }
}
