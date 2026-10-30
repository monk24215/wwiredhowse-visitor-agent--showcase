<?php
 
if(!class_exists('PageRenderer')) {
class PageRenderer {
    /**
     * Render a PHP file with FileLoader integration
     * @param string $pageFile Filename (auto-adds .php if needed)
     * @param string|bool $type FileLoader type or true for custom full path
     * @param array $data Optional data to extract into scope
     * @return bool True if rendered, false otherwise
     */
    public static function render($pageFile, $type = 'pages', $data = []) {
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