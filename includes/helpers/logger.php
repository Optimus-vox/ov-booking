<?php
defined('ABSPATH') || exit;

/**
 * Loguje poruku u custom fajl i opciono u standardni WP debug.log.
 * Automatski pravi folder i fajl ako ne postoje.
 *
 * @param string $message Tekst poruke.
 * @param string $context Kontekst za odabir log fajla (npr. 'price-calculation', 'general').
 * @param int    $depth   Koliko nivoa unazad da ide za backtrace (obično ne treba dirati).
 */
function ovb_log_error($message, $context = 'general', $depth = 1) {
    // --- Deo 1: Upis u tvoj custom log fajl (unapređeno) ---
    try {
        $base_dir = plugin_dir_path(__FILE__) . '../../logs/';

        if (!is_dir($base_dir)) {
            @mkdir($base_dir, 0755, true);
        }

        // Mapa konteksta u nazive fajlova (dodao sam nove koje koristimo)
        $file_map = [
            'admin-calendar'    => 'admin-calendar.log',
            'price-calculation' => 'pricing.log', // Log za cene
            'template-load'     => 'templates.log', // Log za učitavanje templejta
            'general'           => 'plugin.log',
        ];

        $log_file = $base_dir . ($file_map[$context] ?? 'plugin.log');

        if (!file_exists($log_file)) {
            @file_put_contents($log_file, ""); // Kreira prazan fajl
            @chmod($log_file, 0644); // Postavi permisije
        }

        // Info o mestu gde je log pozvan
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 1);
        $caller = $backtrace[$depth] ?? end($backtrace);

        $file = isset($caller['file']) ? basename($caller['file']) : 'unknown file';
        $line = isset($caller['line']) ? $caller['line'] : 'unknown line';

        $entry = "[" . date("Y-m-d H:i:s") . "] {$file}:{$line} - {$message}" . PHP_EOL;

        // Upis u tvoj custom fajl
        error_log($entry, 3, $log_file);

    } catch (Exception $e) {
        // U slučaju da upis u custom fajl ne uspe (npr. permisije)
        error_log("OVB Custom Logger Failed: " . $e->getMessage());
    }

    // --- Deo 2: Upis u standardni WP debug.log (NOVI DEO) ---
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
        // Kreiramo jednostavniju poruku za debug.log da bude preglednije
        $debug_entry = "OVB LOG [Context: {$context}] - {$message}";
        error_log($debug_entry);
    }
}