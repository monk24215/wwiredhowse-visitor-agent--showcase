<?php


if(!class_exists('PathInspector')) {

    class PathInspector
{

    protected static array $data = [];
    public static function collect(): array
    {
        $s = $_SERVER ?? [];

        $scheme = (!empty($s['HTTPS']) && $s['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $s['HTTP_HOST'] ?? 'localhost';

        return [

            'runtime' => [
                'php_sapi'    => PHP_SAPI,
                'php_version' => PHP_VERSION,
                'cwd'         => getcwd(),
            ],

            'filesystem' => [
                '__FILE__'           => __FILE__,
                '__DIR__'            => __DIR__,
                'realpath(__FILE__)' => realpath(__FILE__),
                'realpath(__DIR__)'  => realpath(__DIR__),
                'parent_dir'         => dirname(__DIR__),
                'grandparent_dir'    => dirname(__DIR__, 2),
            ],

            'server' => [
                'DOCUMENT_ROOT'   => $s['DOCUMENT_ROOT'] ?? null,
                'SCRIPT_FILENAME' => $s['SCRIPT_FILENAME'] ?? null,
                'SCRIPT_NAME'     => $s['SCRIPT_NAME'] ?? null,
                'PHP_SELF'        => $s['PHP_SELF'] ?? null,
                'REQUEST_URI'     => $s['REQUEST_URI'] ?? null,
            ],

            'urls' => [
                'scheme'   => $scheme,
                'host'     => $host,
                'base_url' => $scheme . '://' . $host,
                'full_url' => $scheme . '://' . $host . ($s['REQUEST_URI'] ?? ''),
            ],

            'derived_paths' => [
                'docroot + script' =>
                    ($s['DOCUMENT_ROOT'] ?? '') . ($s['SCRIPT_NAME'] ?? ''),

                'docroot + dirname(script)' =>
                    ($s['DOCUMENT_ROOT'] ?? '') . dirname($s['SCRIPT_NAME'] ?? ''),

                'cwd + script' =>
                    getcwd() . '/' . basename($s['SCRIPT_FILENAME'] ?? ''),
            ],

            'included_files' => get_included_files(),
        ];
    }

        // public static function getKee($key = 'filesystem')
        // {
        //     $co = self::collect();
        //     if(array_key_exists($key,$co)){
        //         return $co[$key]; 
        //     }
        //     return (!empty($co))? $co : array(); 

        // }

    public static function defineConstants(array $map = []): array
    {
        $data = self::collect();
        $defined = [];

        foreach ($map as $const => $path) {
            if (defined($const)) {
                continue;
            }

            // allow dot-notation paths like "server.DOCUMENT_ROOT"
            $value = self::getByPath($data, $path);

            if ($value !== null) {
                define($const, $value);
                $defined[$const] = $value;
            }
        }

        return $defined;
    }
  

        private static function getByPath(array $array, string $path)
        {
            foreach (explode('.', $path) as $segment) {
                if (!is_array($array) || !array_key_exists($segment, $array)) {
                    return null;
                }
                $array = $array[$segment];
            }
            return $array;
        }


        public static function load(array $data) : void
        {
            self::$data = $data;
        }

        /* ===============================
           Existing method compatibility
        =============================== */
        public static function getKee(string $key)
        {
            return self::$data[$key] ?? null;
        }

        /* ===============================
           NEW: recursive key lookup
           (this fixes your fatal error)
        =============================== */
        public static function findKeyRec(string $targetKey, $default = null)
        {
            return self::walk(self::$data, $targetKey) ?? $default;
        }

        private static function walk(array $array, string $targetKey)
        {
            foreach ($array as $key => $value) {
                if ($key === $targetKey) {
                    return $value;
                }
                if (is_array($value)) {
                    $found = self::walk($value, $targetKey);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
            return null;
        }


      public static function  findKeyRecursive(array $array, string $targetKey) {
        foreach ($array as $key => $value) {
            if ($key === $targetKey) {
                return $value;
            }
            if (is_array($value)) {
                $found = self::findKeyRecursive($value, $targetKey);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    public static function dump(): array
    {
        return self::collect();
    }
    public static function renderHTML(string $mode = 'pre'): string
    {
        $data = self::collect();

        if ($mode === 'table') {
            return self::renderTable($data);
        }

        $pp = new PrettyPrint();

        return '<pre style="white-space:pre-wrap;font-size:13px;">'
            . htmlspecialchars($pp->format($data), ENT_QUOTES, 'UTF-8')
            . '</pre>';
    }

    protected static function renderTable(array $data): string
    {
        $html = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px;">';

        foreach ($data as $section => $values) {
            $html .= '<tr style="background:#eee;"><th colspan="2">'
                . htmlspecialchars((string) $section)
                . '</th></tr>';

            foreach ((array) $values as $k => $v) {
                $html .= '<tr><td><strong>'
                    . htmlspecialchars((string) $k)
                    . '</strong></td><td><pre>'
                    . htmlspecialchars(print_r($v, true))
                    . '</pre></td></tr>';
            }
        }

        return $html . '</table>';
    }
}
}


PathInspector::defineConstants([
    'WH_PATH' => 'server.DOCUMENT_ROOT',
    'WH_URL'  => 'urls.base_url',
    'ABSPATH'  => 'server.DOCUMENT_ROOT',
]);