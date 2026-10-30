<?php
declare(strict_types=1);

if(!class_exists('PrettyPrint')) {

class PrettyPrint
{
    protected int $maxDepth;

    public function __construct(int $maxDepth = 5)
    {
        $this->maxDepth = $maxDepth;
    }

    public function format($value, int $depth = 0): string
    {
        if ($depth > $this->maxDepth) {
            return '[Max depth reached]';
        }

        if (is_array($value)) {
            return $this->formatArray($value, $depth);
        }

        if (is_object($value)) {
            return $this->formatObject($value, $depth);
        }

        return $this->formatScalar($value);
    }

    public function toHtml($value): string
    {
        return '<pre style="background:#1e1e1e;color:#d4d4d4;padding:1em;border-radius:4px;font-size:13px;line-height:1.5;overflow:auto">'
            . htmlspecialchars($this->format($value))
            . '</pre>';
    }

    protected function formatArray(array $array, int $depth): string
    {
        $out = "[\n";
        foreach ($array as $key => $value) {
            $out .= str_repeat('  ', $depth + 1)
                . $key . ' => '
                . $this->format($value, $depth + 1)
                . "\n";
        }
        return $out . str_repeat('  ', $depth) . ']';
    }

    protected function formatObject(object $object, int $depth): string
    {
        $out = get_class($object) . " {\n";
        foreach ((array) $object as $key => $value) {
            $out .= str_repeat('  ', $depth + 1)
                . $key . ' => '
                . $this->format($value, $depth + 1)
                . "\n";
        }
        return $out . str_repeat('  ', $depth) . '}';
    }

    protected function formatScalar($value): string
    {
        if (is_string($value)) {
            return '"' . $value . '"';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        return (string) $value;
    }

public function toEnv($value, array $overrides = [], string $prefix = ''): string
{
    $lines = [];
    $this->flattenEnv($value, $prefix, $lines, $overrides);
    return implode("\n", $lines);
}

protected function flattenEnv($value, string $prefix, array &$lines, array $overrides = []): void
{
    if (is_array($value) || is_object($value)) {
        foreach ((array) $value as $key => $val) {
            $newKey = $prefix ? $prefix . '_' . strtoupper((string) $key) : strtoupper((string) $key);
            $this->flattenEnv($val, $newKey, $lines, $overrides);
        }
    } else {
        if (array_key_exists($prefix, $overrides)) {
            $value = $overrides[$prefix];
        }
        $scalar = $this->formatScalar($value);
        $lines[] = $prefix . '=' . $scalar;
    }
}

//     public function toEnv($value, string $prefix = ''): string
// {
//     $lines = [];
//     $this->flattenEnv($value, $prefix, $lines);
//     return implode("\n", $lines);
// }


// protected function flattenEnv($value, string $prefix, array &$lines): void
// {
//     if (is_array($value) || is_object($value)) {
//         foreach ((array) $value as $key => $val) {
//             $newKey = $prefix ? $prefix . '_' . strtoupper((string) $key) : strtoupper((string) $key);
//             $this->flattenEnv($val, $newKey, $lines);
//         }
//     } else {
//         $scalar = $this->formatScalar($value);
//         $lines[] = $prefix . '=' . $scalar;
//     }
// }

}

}