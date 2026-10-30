<?php


if(!class_exists('IPAddress')) {

class IPAddress {
    private $ip;
    private $version;
    
    public function __construct($ip = null) {
        $this->ip = $ip ?? $this->getCurrentIP();
        $this->version = $this->detectVersion();
    }
    
    /**
     * Get the current user's IP address
     */
    private function getCurrentIP() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
    
    /**
     * Detect IP version (4 or 6)
     */
    private function detectVersion() {
        if (filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 4;
        } elseif (filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return 6;
        }
        return null;
    }
    
    public function getIP() {
        return $this->ip;
    }
    
    public function getVersion() {
        return $this->version;
    }
    
    public function isValid() {
        return filter_var($this->ip, FILTER_VALIDATE_IP) !== false;
    }
    
    public function isPrivate() {
        return !filter_var(
            $this->ip, 
            FILTER_VALIDATE_IP, 
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
    
    public function isLocalhost() {
        return in_array($this->ip, ['127.0.0.1', '::1', 'localhost']);
    }
    
    /**
     * Check if IP exists in array of IPs (exact match)
     */
    public function isInArray(array $ipList) {
        return in_array($this->ip, $ipList, true);
    }
    
    /**
     * Check if IP matches any pattern (supports wildcards)
     */
    public function matchesAny(array $patterns) {
        foreach ($patterns as $pattern) {
            if ($this->matchesPattern($pattern)) {
                return true;
            }
        }
        return false;
    }
    
    private function matchesPattern($pattern) {
        $pattern = str_replace('.', '\.', $pattern);
        $pattern = str_replace('*', '\d+', $pattern);
        return preg_match("/^{$pattern}$/", $this->ip) === 1;
    }
    
    /**
     * Check if IP is in a specific CIDR range
     */
    public function isInRange($range) {
        if (strpos($range, '/') === false) {
            return $this->ip === $range;
        }
        
        list($subnet, $mask) = explode('/', $range);
        $ip_long = ip2long($this->ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - (int)$mask);
        
        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }
    
    /**
     * Check if IP is in any of the provided CIDR ranges
     */
    public function isInRanges(array $ranges) {
        foreach ($ranges as $range) {
            if ($this->isInRange($range)) {
                return true;
            }
        }
        return false;
    }
    
    public function toLong() {
        if ($this->version === 4) {
            return ip2long($this->ip);
        }
        return null;
    }
    
    public function toArray() {
        return [
            'ip' => $this->ip,
            'version' => $this->version,
            'is_valid' => $this->isValid(),
            'is_private' => $this->isPrivate(),
            'is_localhost' => $this->isLocalhost(),
        ];
    }
    
    public function __toString() {
        return $this->ip;
    }
}
}