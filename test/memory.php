<?php

// Set a memory threshold for warning (in bytes)
$memory_threshold = 50 * 1024 * 1024;  // 50MB for example

// Run forever (or set max iterations/time as needed)
while (true) {
    // Get current memory usage
    $current_memory = memory_get_usage();
    $peak_memory = memory_get_peak_usage();

    // Print memory stats (you can log these to a file for better tracking)
    echo "Current Memory Usage: " . $current_memory . " bytes\n";
    echo "Peak Memory Usage: " . $peak_memory . " bytes\n";

    // Check if memory usage exceeds the threshold
    if ($current_memory > $memory_threshold) {
        echo "Warning: Memory usage exceeded threshold!\n";
        // Take action if memory exceeds threshold (like logging or cleanup)
    }

    // Perform garbage collection (can help reduce memory if possible)
    gc_collect_cycles();

    // Sleep for a while before checking again to avoid tight loop
    sleep(1);  // Check every 5 seconds (adjust as needed)
}
