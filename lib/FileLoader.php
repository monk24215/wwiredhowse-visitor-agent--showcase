<?php

if(!class_exists('FileLoader')) {
class FileLoader {
    
    private static $searchPaths = [];
    private static $typeCache = []; // Cache for performance
    
    /**
     * Add a custom search path (for BAM services)
     * @param string $path Full or relative path to search
     */
    public static function addPath($path) {
        $normalized = rtrim($path, '/') . '/';
        if (!in_array($normalized, self::$searchPaths)) {
            self::$searchPaths[] = $normalized;
        }
    }
    
    /**
     * Add multiple paths at once
     * @param array $paths Array of paths
     */
    public static function addPaths(array $paths) {
        foreach ($paths as $path) {
            self::addPath($path);
        }
    }
    
    /**
     * Include a file with full search hierarchy
     * @param string|false $type Directory type or false for direct search
     * @param string|false $filename Filename to include
     * @return bool True if found and included
     */
    public static function incs($type = false, $filename = false) {
        if (!$filename) { 
            return false; 
        }
        
        $filepath = self::findFile($type, $filename);
        
        if ($filepath) { 
            include $filepath;
            return true;
        }
        
        return false;
    }
    
    /**
     * Require a file (throws error if not found)
     * @param string|false $type Directory type
     * @param string $filename Filename
     * @return bool
     */
    public static function req($type = false, $filename = false) {
        if (!$filename) { 
            trigger_error("FileLoader::req() - No filename provided", E_USER_ERROR);
            return false;
        }
        
        $filepath = self::findFile($type, $filename);
        // echo "FileLoader::req() - File not found: {$filepath}", E_USER_ERROR;
        
        if ($filepath) { 
            require $filepath;
            return true;
        }
        
        trigger_error("FileLoader::req() - File not found: {$filename}", E_USER_ERROR);
        return false;
    }
    
    /**
     * Find file in search hierarchy
     * Searches: type directory → custom paths → root
     * @param string|false $type Directory type
     * @param string $filename Filename
     * @return string|false Full path if found, false otherwise
     */
    public static function findFile($type, $filename) {
        // Cache key for performance
        $cacheKey = ($type ? $type . ':' : '') . $filename;
        if (isset(self::$typeCache[$cacheKey])) {
            return self::$typeCache[$cacheKey];
        }
        
        // 1. Check type-specific directory first
        if ($type) {
            $paths = [
                'util'      => 'util/',
                'utilities' => 'util/',
                'assets'    => 'assets/',
                'css'       => 'assets/css/',
                'js'        => 'assets/js/',
                'classes'   => 'app/',
                'class'     => 'app/',
                'page'      => 'pages/',
                'pages'     => '_cld/pages/',
                'page'      => 'public/',
                'pages'     => 'public/',
                'service'   => 'services/',
                'services'  => 'services/',
                'shared'    => 'shared/',
                'common'    => 'common/'
            ];
            
            $basePath = $paths[$type] ?? $type . '/';
            $filepath = $basePath . $filename;
            
            if (file_exists($filepath)) {
                self::$typeCache[$cacheKey] = $filepath;
                return $filepath;
            }
        }
        
        // 2. Check custom search paths (BAM services)
        foreach (self::$searchPaths as $path) {
            // Try with type subdirectory if type is specified
            if ($type) {
                $filepath = $path . ($paths[$type] ?? $type . '/') . $filename;
                if (file_exists($filepath)) {
                    self::$typeCache[$cacheKey] = $filepath;
                    return $filepath;
                }
            }
            
            // Try direct path
            $filepath = $path . $filename;
            if (file_exists($filepath)) {
                self::$typeCache[$cacheKey] = $filepath;
                return $filepath;
            }
        }
        
        // 3. Check root directory as final fallback
        if (file_exists($filename)) {
            self::$typeCache[$cacheKey] = $filename;
            return $filename;
        }
        
        return false;
    }
    
    /**
     * Legacy method - builds path
     */
    public static function incFile($type, $filename) {
        return self::findFile($type, $filename) ?: $filename;
    }
    
    /**
     * Get file path with existence check
     */
    public static function getPath($type, $filename) {
        return self::findFile($type, $filename);
    }
    
    /**
     * Check if file exists in search hierarchy
     */
    public static function exists($type, $filename) {
        return self::findFile($type, $filename) !== false;
    }
    
    /**
     * Clear path cache (useful during development)
     */
    public static function clearCache() {
        self::$typeCache = [];
    }
}
}

if(!class_exists('PageRenderer')) {
class PageRenderer {
    /**
     * Render a PHP file with FileLoader integration
     * @param string $pageFile Filename (auto-adds .php if needed)
     * @param string|bool $type FileLoader type or true for custom full path
     * @param array $data Optional data to extract into scope
     * @return bool True if rendered, false otherwise
     */
    public static function render($pageFile, $type = 'public', $data = []) {
        // Extract data into local scope for the included file
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }
        
        // If $type is true, treat as custom full path
        if ($type === true) {
            if (file_exists($pageFile)) {
                include $pageFile;
                return true;
            } else {
                echo "Error: File '{$pageFile}' not found.";
                return false;
            }
        }
        
        // Auto-add .php if no extension
        $filename = (pathinfo($pageFile, PATHINFO_EXTENSION) === '') ? $pageFile . '.php' : $pageFile;
        //var_dump($pageFile);
        // Find via FileLoader
        $filepath = FileLoader::findFile($type, $filename);
        
        if ($filepath) {
            include $filepath;
            return true;
        } else {
            echo "Error: File '{$filename}' not found in '{$type}' directory or search paths.";
            return false;
        }
    }
    
    /**
     * Render and return output as string (buffered)
     */
    public static function fetch($pageFile, $type = 'pages', $data = []) {
        ob_start();
        self::render($pageFile, $type, $data);
        return ob_get_clean();
    }
}
}


// Simple project structure:
// /classes/
// /util/
// /pages/
// config.php
// index.php

// require_once 'FileLoader.php';  // Drop it in

// // Works immediately - no addPath() needed
// FileLoader::incs('class', 'EmailSender.php');
// FileLoader::incs('util', 'clickbank_helpers.php');
// PageRenderer::render('email_preview');

// // Loads from root automatically
// FileLoader::incs(false, 'config.php');

// In your BAM bootstrap or service initialization
// FileLoader::addPath(__DIR__ . '/services/clickbank-suite');
// FileLoader::addPath(__DIR__ . '/shared/utilities');
// FileLoader::addPath(__DIR__ . '/common/classes');

// // Now files can be found across your entire BAM structure
// FileLoader::incs('class', 'Database.php');     // Checks all registered paths
// FileLoader::incs('util', 'helpers.php');       // Searches everywhere
// PageRenderer::render('dashboard');             // Works across services



// Bootstrap your BAM application
//FileLoader::addPaths([
//     __DIR__ . '/services/clickbank-suite',
//     __DIR__ . '/services/email-tracker',
//     __DIR__ . '/services/offer-rotator',
//     __DIR__ . '/shared',
//     __DIR__ . '/common'
// ]);

// // Works across all services
// FileLoader::incs('class', 'Database.php');          // Searches all service paths
// FileLoader::incs('util', 'email_helpers.php');      // Found in any registered path
// PageRenderer::render('dashboard');                   // Searches pages/ in all paths

// // Service-specific
// PageRenderer::render('clickbank_stats', 'pages');   // Finds in any service's pages/
// FileLoader::req('class', 'HopTracker');            // Required (error if not found)

// // With data passing
// PageRenderer::render('header', 'pages', [
//     'title' => 'BAM Dashboard',
//     'user' => $currentUser
// ]);

// // Fetch as string
// $sidebar = PageRenderer::fetch('sidebar', 'pages', ['stats' => $data]);