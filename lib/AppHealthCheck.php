<?php


if(!class_exists('AppHealthCheck')) {

 
final class AppHealthCheck
{
    private static array $results = [];

    private function __construct() {}

    /* ===============================
       PUBLIC ENTRY POINT
    =============================== */

    public static function run(array $checks) : void
    {
        foreach ($checks as $type => $items) {
            foreach ($items as $label => $value) {
                self::check($type, $label, $value);
            }
        }

        self::finalize();
    }

    /* ===============================
       CHECK DISPATCHER
    =============================== */

    private static function check(string $type, string $label, $value) : void
    {
        $ok = match ($type) {
            'files'     => is_file(realpath($value)),
            'classes'   => class_exists($value, false),
            'constants' => defined($value),
            'php'       => version_compare(PHP_VERSION, $value, '>='),
            default     => false,
        };

        self::$results[] = [
            'type'  => $type,
            'label' => $label,
            'value' => $value,
            'ok'    => $ok,
        ];

        if (!$ok) {
            self::fail($type, $label, $value);
        }
    }

    /* ===============================
       FAILURE HANDLING
    =============================== */

    private static function fail(string $type, string $label, $value) : void
    {
        http_response_code(500);
        error_log("[HealthCheck FAIL] {$type}: {$label} ({$value})");
        exit;
    }

    /* ===============================
       SUCCESS PATH
    =============================== */

    private static function finalize() : void
    {
        // nothing fancy, just proof we got here
        define('APP_HEALTH_OK', true);
    }

    /* ===============================
       DEBUG OUTPUT (OPTIONAL)
    =============================== */

    public static function report() : array
    {
        return self::$results;
    }
}

}


// PathInspector::defineConstants([
//     'WH_PATH' => 'server.DOCUMENT_ROOT',
//     'WH_URL'  => 'urls.base_url',
//     'ABSPATH'  => 'server.DOCUMENT_ROOT',
// ]);

