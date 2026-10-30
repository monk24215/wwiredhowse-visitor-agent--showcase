<?php
class QueryParam {
    
    /**
     * Get value from GET query with optional default
     * @param string $key Query parameter name
     * @param mixed $default Default value if not set (default: 'index')
     * @return mixed
     */
    public static function get($key, $default = 'index') {
        return $_GET[$key] ?? $default;
    }
    
    /**
     * Get multiple params at once
     * @param array $params ['key' => 'default', 'key2' => 'default2']
     * @return array
     */
    public static function getMultiple(array $params) {
        $result = [];
        foreach ($params as $key => $default) {
            $result[$key] = self::get($key, $default);
        }
        return $result;
    }
    
    /**
     * Check if parameter exists in GET
     * @param string $key
     * @return bool
     */
    public static function has($key) {
        return isset($_GET[$key]);
    }
}


// // Simple usage - defaults to 'index'
// $page = QueryParam::get('page');  // ?page=about → 'about'
//                                    // (no ?page) → 'index'

// // Custom default
// $page = QueryParam::get('page', 'home');  // ?page=about → 'about'
//                                            // (no ?page) → 'home'

// // Numbers
// $id = QueryParam::get('id', 0);           // ?id=123 → '123'
//                                            // (no ?id) → 0

// // Multiple params
// $params = QueryParam::getMultiple([
//     'page' => 'index',
//     'action' => 'view',
//     'id' => 0
// ]);
// // $params['page'], $params['action'], $params['id']

// // Check existence
// if (QueryParam::has('page')) {
//     // Do something
// }