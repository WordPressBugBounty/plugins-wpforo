#!/usr/bin/env php
<?php
/**
 * wpForo Expert Knowledge Generator
 *
 * Analyzes the wpForo codebase and generates structured documentation
 * that can be indexed into the AI knowledge base.
 *
 * Usage: php generate-expert-knowledge.php [output-dir]
 *
 * This script extracts:
 * - All classes with their methods and properties
 * - All hooks (actions and filters) with WHERE they're called
 * - Database table schemas
 * - Settings structure
 * - Template hierarchy
 * - WordPress integration points
 */

// Configuration
$WPFORO_DIR = dirname(__DIR__);
$OUTPUT_DIR = $argv[1] ?? $WPFORO_DIR . '/docs/expert-knowledge';

// Ensure output directory exists
if (!is_dir($OUTPUT_DIR)) {
    mkdir($OUTPUT_DIR, 0755, true);
}

echo "wpForo Expert Knowledge Generator\n";
echo "==================================\n";
echo "Source: $WPFORO_DIR\n";
echo "Output: $OUTPUT_DIR\n\n";

/**
 * Extract class information from PHP files
 */
function extractClasses($dir) {
    $classes = [];
    $files = glob("$dir/classes/*.php");

    foreach ($files as $file) {
        $content = file_get_contents($file);
        $className = basename($file, '.php');

        // Extract class docblock
        preg_match('/\/\*\*[\s\S]*?\*\/\s*class\s+' . $className . '/m', $content, $docMatch);
        $docblock = $docMatch[0] ?? '';

        // Extract methods
        preg_match_all('/(?:\/\*\*[\s\S]*?\*\/\s*)?(public|private|protected)\s+(static\s+)?function\s+(\w+)\s*\(([^)]*)\)/m', $content, $methods, PREG_SET_ORDER);

        $methodList = [];
        foreach ($methods as $method) {
            $methodList[] = [
                'visibility' => $method[1],
                'static' => !empty($method[2]),
                'name' => $method[3],
                'params' => $method[4],
            ];
        }

        // Extract properties
        preg_match_all('/(public|private|protected)\s+(static\s+)?(\$\w+)/', $content, $props, PREG_SET_ORDER);

        $propList = [];
        foreach ($props as $prop) {
            $propList[] = [
                'visibility' => $prop[1],
                'static' => !empty($prop[2]),
                'name' => $prop[3],
            ];
        }

        $classes[$className] = [
            'file' => basename($file),
            'methods' => $methodList,
            'properties' => $propList,
            'method_count' => count($methodList),
        ];
    }

    return $classes;
}

/**
 * Extract all hooks (actions and filters) with context
 */
function extractHooks($dir) {
    $hooks = [
        'actions' => [],
        'filters' => [],
    ];

    // Search all PHP files
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') continue;

        $content = file_get_contents($file->getPathname());
        $relativePath = str_replace($dir . '/', '', $file->getPathname());

        // Find do_action calls
        preg_match_all('/do_action\s*\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*([^)]+))?\)/m', $content, $actions, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($actions as $match) {
            $hookName = $match[1][0];
            $params = isset($match[2]) ? trim($match[2][0]) : '';
            $lineNum = substr_count(substr($content, 0, $match[0][1]), "\n") + 1;

            if (!isset($hooks['actions'][$hookName])) {
                $hooks['actions'][$hookName] = [];
            }
            $hooks['actions'][$hookName][] = [
                'file' => $relativePath,
                'line' => $lineNum,
                'params' => $params,
            ];
        }

        // Find apply_filters calls
        preg_match_all('/apply_filters\s*\(\s*[\'"]([^\'"]+)[\'"](?:\s*,\s*([^)]+))?\)/m', $content, $filters, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($filters as $match) {
            $hookName = $match[1][0];
            $params = isset($match[2]) ? trim($match[2][0]) : '';
            $lineNum = substr_count(substr($content, 0, $match[0][1]), "\n") + 1;

            if (!isset($hooks['filters'][$hookName])) {
                $hooks['filters'][$hookName] = [];
            }
            $hooks['filters'][$hookName][] = [
                'file' => $relativePath,
                'line' => $lineNum,
                'params' => $params,
            ];
        }
    }

    // Sort by hook name
    ksort($hooks['actions']);
    ksort($hooks['filters']);

    return $hooks;
}

/**
 * Extract database table definitions
 */
function extractTables($dir) {
    $tables = [];

    // Check install-sql.php for table definitions
    $installFile = "$dir/includes/install-sql.php";
    if (file_exists($installFile)) {
        $content = file_get_contents($installFile);

        // Find CREATE TABLE statements
        preg_match_all('/CREATE TABLE[^`]*`([^`]+)`\s*\(([^;]+)\)/m', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $tableName = $match[1];
            $definition = $match[2];

            // Extract columns
            preg_match_all('/`(\w+)`\s+([A-Z]+(?:\([^)]+\))?)/m', $definition, $columns, PREG_SET_ORDER);

            $columnList = [];
            foreach ($columns as $col) {
                $columnList[] = [
                    'name' => $col[1],
                    'type' => $col[2],
                ];
            }

            $tables[$tableName] = [
                'columns' => $columnList,
            ];
        }
    }

    return $tables;
}

/**
 * Extract settings structure
 */
function extractSettings($dir) {
    $settingsFile = "$dir/classes/Settings.php";
    if (!file_exists($settingsFile)) return [];

    $content = file_get_contents($settingsFile);

    // Find setting categories
    $settings = [];

    // Look for setting arrays
    preg_match_all('/[\'"](\w+)[\'"]\s*=>\s*\[\s*[\'"]type[\'"]\s*=>\s*[\'"](\w+)[\'"]/', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $settings[$match[1]] = [
            'type' => $match[2],
        ];
    }

    return $settings;
}

/**
 * Generate markdown documentation
 */
function generateMarkdown($data, $outputDir) {
    // 1. Core Classes Documentation
    $classesDoc = "# wpForo Core Classes Reference\n\n";
    $classesDoc .= "This document provides a complete reference of all wpForo core classes.\n\n";
    $classesDoc .= "## Overview\n\n";
    $classesDoc .= "| Class | Methods | File |\n";
    $classesDoc .= "|-------|---------|------|\n";

    foreach ($data['classes'] as $name => $info) {
        $classesDoc .= "| {$name} | {$info['method_count']} | {$info['file']} |\n";
    }

    $classesDoc .= "\n---\n\n";

    foreach ($data['classes'] as $name => $info) {
        $classesDoc .= "## {$name}\n\n";
        $classesDoc .= "**File**: `classes/{$info['file']}`\n\n";

        if (!empty($info['methods'])) {
            $classesDoc .= "### Methods\n\n";
            foreach ($info['methods'] as $method) {
                $static = $method['static'] ? 'static ' : '';
                $classesDoc .= "- `{$method['visibility']} {$static}{$method['name']}({$method['params']})`\n";
            }
        }

        $classesDoc .= "\n---\n\n";
    }

    file_put_contents("$outputDir/core-classes.md", $classesDoc);
    echo "Generated: core-classes.md\n";

    // 2. Hooks Reference
    $hooksDoc = "# wpForo Hooks Reference\n\n";
    $hooksDoc .= "Complete list of all wpForo actions and filters.\n\n";

    $hooksDoc .= "## Actions (" . count($data['hooks']['actions']) . " total)\n\n";
    $hooksDoc .= "Actions allow you to execute code at specific points in wpForo.\n\n";

    foreach ($data['hooks']['actions'] as $hookName => $locations) {
        $hooksDoc .= "### `{$hookName}`\n\n";
        $hooksDoc .= "**Called in**:\n";
        foreach ($locations as $loc) {
            $hooksDoc .= "- `{$loc['file']}:{$loc['line']}`";
            if ($loc['params']) {
                $hooksDoc .= " - Params: `{$loc['params']}`";
            }
            $hooksDoc .= "\n";
        }
        $hooksDoc .= "\n";
    }

    $hooksDoc .= "---\n\n";
    $hooksDoc .= "## Filters (" . count($data['hooks']['filters']) . " total)\n\n";
    $hooksDoc .= "Filters allow you to modify data at specific points.\n\n";

    foreach ($data['hooks']['filters'] as $hookName => $locations) {
        $hooksDoc .= "### `{$hookName}`\n\n";
        $hooksDoc .= "**Called in**:\n";
        foreach ($locations as $loc) {
            $hooksDoc .= "- `{$loc['file']}:{$loc['line']}`";
            if ($loc['params']) {
                $hooksDoc .= " - Params: `{$loc['params']}`";
            }
            $hooksDoc .= "\n";
        }
        $hooksDoc .= "\n";
    }

    file_put_contents("$outputDir/hooks-reference.md", $hooksDoc);
    echo "Generated: hooks-reference.md\n";

    // 3. Database Schema
    $dbDoc = "# wpForo Database Schema\n\n";
    $dbDoc .= "Complete database table reference.\n\n";

    foreach ($data['tables'] as $tableName => $tableInfo) {
        $dbDoc .= "## `{$tableName}`\n\n";
        $dbDoc .= "| Column | Type |\n";
        $dbDoc .= "|--------|------|\n";
        foreach ($tableInfo['columns'] as $col) {
            $dbDoc .= "| {$col['name']} | {$col['type']} |\n";
        }
        $dbDoc .= "\n";
    }

    file_put_contents("$outputDir/database-schema.md", $dbDoc);
    echo "Generated: database-schema.md\n";

    // 4. Summary JSON for RAG ingestion
    $summary = [
        'generated_at' => date('Y-m-d H:i:s'),
        'stats' => [
            'classes' => count($data['classes']),
            'methods' => array_sum(array_column($data['classes'], 'method_count')),
            'actions' => count($data['hooks']['actions']),
            'filters' => count($data['hooks']['filters']),
            'tables' => count($data['tables']),
        ],
        'classes' => array_keys($data['classes']),
        'hooks' => [
            'actions' => array_keys($data['hooks']['actions']),
            'filters' => array_keys($data['hooks']['filters']),
        ],
    ];

    file_put_contents("$outputDir/summary.json", json_encode($summary, JSON_PRETTY_PRINT));
    echo "Generated: summary.json\n";
}

// Main execution
echo "Extracting classes...\n";
$classes = extractClasses($WPFORO_DIR);

echo "Extracting hooks...\n";
$hooks = extractHooks($WPFORO_DIR);

echo "Extracting database schema...\n";
$tables = extractTables($WPFORO_DIR);

echo "Extracting settings...\n";
$settings = extractSettings($WPFORO_DIR);

echo "\nGenerating documentation...\n";
$data = [
    'classes' => $classes,
    'hooks' => $hooks,
    'tables' => $tables,
    'settings' => $settings,
];

generateMarkdown($data, $OUTPUT_DIR);

echo "\n=== Summary ===\n";
echo "Classes: " . count($classes) . "\n";
echo "Actions: " . count($hooks['actions']) . "\n";
echo "Filters: " . count($hooks['filters']) . "\n";
echo "Tables: " . count($tables) . "\n";
echo "\nDone! Output in: $OUTPUT_DIR\n";
