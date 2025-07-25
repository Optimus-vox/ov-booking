<?php
defined('ABSPATH') || exit;

/**
 * Log grešku sa fajlom i linijom, automatski pravi folder i fajl ako ne postoji.
 *
 * @param string $message Tekst greške
 * @param string $context 'admin-calendar' ili 'general'
 * @param int|null $depth Koliko nivoa unazad da ide za trace (default 1)
 */
function ov_log_error($message, $context = 'general', $depth = 1) {
    $base_dir = plugin_dir_path(__FILE__) . '../../logs/';

    if (!file_exists($base_dir)) {
        mkdir($base_dir, 0755, true);
    }

    // Mape fajlova po kontekstu
    $file_map = [
        'admin-calendar' => 'admin-calendar.log',
        'general'        => 'plugin.log',
    ];

    $log_file = $base_dir . ($file_map[$context] ?? 'plugin.log');

    if (!file_exists($log_file)) {
        file_put_contents($log_file, ""); // kreira prazan fajl
        chmod($log_file, 0644); // postavi permisije
    }

    // Info o mestu gde je log pozvan
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 1);
    $caller = $backtrace[$depth];

    $file = isset($caller['file']) ? basename($caller['file']) : 'unknown file';
    $line = isset($caller['line']) ? $caller['line'] : 'unknown line';
    $function = isset($caller['function']) ? $caller['function'] : 'unknown function';

    $entry = "[" . date("Y-m-d H:i:s") . "] {$file}:{$line} {$function}() - {$message}" . PHP_EOL;


    // Upis
    error_log($entry, 3, $log_file);
}