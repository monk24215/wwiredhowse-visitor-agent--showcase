<?php


if(!class_exists('IPChecker')) {

class IPChecker {
    private $ipHash = [];
    
    public function __construct(array $ips) {
        // Create hash map for O(1) lookup
        $this->ipHash = array_flip($ips);
    }
    
    public function contains($ip) {
        return isset($this->ipHash[$ip]);
    }
}
}