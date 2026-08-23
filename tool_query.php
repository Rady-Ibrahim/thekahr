#!/usr/bin/env php
<?php

/**
 * Laravel Database Query Tool
 * 
 * This script bootstraps Laravel and executes SQL queries using the DB facade.
 * Usage: php tool_query.php "SELECT * FROM table_name LIMIT 10"
 */

define('LARAVEL_START', microtime(true));

// Register the auto loader
require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';

// Boot the application kernel to initialize services
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Get query from command line arguments
if ($argc < 2) {
    echo json_encode([
        'error' => true,
        'message' => 'No SQL query provided. Usage: php tool_query.php "SELECT * FROM table_name"'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

$query = $argv[1];

if (empty(trim($query))) {
    echo json_encode([
        'error' => true,
        'message' => 'Empty SQL query provided'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

try {
    // Detect query type by checking if it's a read operation
    // Remove leading whitespace and comments for better detection
    $queryClean = preg_replace('/^[\s\n\r\t]+/m', '', $query);
    $queryUpper = strtoupper(trim($queryClean));
    
    // Check for read operations
    $isSelect = strpos($queryUpper, 'SELECT') === 0;
    $isShow = strpos($queryUpper, 'SHOW') === 0;
    $isDescribe = strpos($queryUpper, 'DESCRIBE') === 0 || 
                  (strpos($queryUpper, 'DESC ') === 0 && strpos($queryUpper, 'DESCRIBE') === false);
    $isExplain = strpos($queryUpper, 'EXPLAIN') === 0;
    
    $isReadOperation = $isSelect || $isShow || $isDescribe || $isExplain;
    
    if ($isReadOperation) {
        // Use DB::select() for read operations
        $results = DB::select($query);
        
        // Convert stdClass objects to arrays for JSON encoding
        $resultsArray = array_map(function($item) {
            return (array) $item;
        }, $results);
        
        echo json_encode([
            'error' => false,
            'query' => $query,
            'type' => 'SELECT',
            'row_count' => count($resultsArray),
            'data' => $resultsArray
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        // Use DB::statement() for write operations (INSERT, UPDATE, DELETE, etc.)
        $affectedRows = DB::affectingStatement($query);
        
        echo json_encode([
            'error' => false,
            'query' => $query,
            'type' => 'WRITE',
            'affected_rows' => $affectedRows,
            'message' => 'Query executed successfully'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'query' => $query,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

exit(0);

