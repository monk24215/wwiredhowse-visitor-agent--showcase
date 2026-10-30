<?php
if(!class_exists('Env')) {
class Env {
    private static $data = null;
    private static $loaded = false;
    
    public static function load($file = null) {
        if (self::$loaded) {
            return true;
        }
        
    
        $file = $file ?? ABSPATH . 'lib/.env';
        
        if (!file_exists($file)) {
            trigger_error("Environment file not found: {$file}", E_USER_ERROR);
            return false;
        }
        
        // Parse with INI_SCANNER_RAW to handle special characters
        self::$data = parse_ini_file($file, false, INI_SCANNER_RAW);
        
        if (self::$data === false) {
            trigger_error("Failed to parse environment file: {$file}", E_USER_ERROR);
            return false;
        }
        
        // Remove quotes if present
        foreach (self::$data as $key => $value) {
            if (is_string($value)) {
                // Remove surrounding quotes
                self::$data[$key] = trim($value, '"\'');
            }
        }
        
        self::$loaded = true;
        return true;
    }
    
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$data[$key] ?? $default;
    }
    
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$data;
    }
    
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset(self::$data[$key]);
    }
}
}