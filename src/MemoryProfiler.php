<?php

namespace PocJsonDecode;

/**
 * Memory and performance profiler utility
 */
class MemoryProfiler
{
    private $startTime;
    private $startMemory;
    private $peakMemory;
    
    public function __construct()
    {
        $this->reset();
    }
    
    /**
     * Start or reset the profiler
     */
    public function reset()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        $this->peakMemory = $this->startMemory;
    }
    
    /**
     * Record current memory usage (useful for tracking peak during streaming)
     */
    public function recordMemoryUsage()
    {
        $currentMemory = memory_get_usage(true);
        if ($currentMemory > $this->peakMemory) {
            $this->peakMemory = $currentMemory;
        }
    }
    
    /**
     * Get execution time in seconds
     */
    public function getExecutionTime()
    {
        return microtime(true) - $this->startTime;
    }
    
    /**
     * Get memory used in bytes
     */
    public function getMemoryUsed()
    {
        return memory_get_usage(true) - $this->startMemory;
    }
    
    /**
     * Get peak memory usage in bytes
     */
    public function getPeakMemoryUsed()
    {
        $currentPeak = memory_get_peak_usage(true);
        return max($this->peakMemory, $currentPeak) - $this->startMemory;
    }
    
    /**
     * Format bytes to human readable format
     */
    public static function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Format time to human readable format
     */
    public static function formatTime($seconds)
    {
        if ($seconds < 1) {
            return round($seconds * 1000, 2) . ' ms';
        }
        
        return round($seconds, 3) . ' s';
    }
    
    /**
     * Get comprehensive profiling results
     */
    public function getResults()
    {
        return [
            'execution_time' => $this->getExecutionTime(),
            'execution_time_formatted' => self::formatTime($this->getExecutionTime()),
            'memory_used' => $this->getMemoryUsed(),
            'memory_used_formatted' => self::formatBytes($this->getMemoryUsed()),
            'peak_memory' => $this->getPeakMemoryUsed(),
            'peak_memory_formatted' => self::formatBytes($this->getPeakMemoryUsed()),
        ];
    }
}
