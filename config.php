<?php
/**
 * config.php — the ONLY file you edit.
 * Fill in DB credentials, your Anthropic API key, and an admin PIN.
 */

 require_once 'setup.php'; 

return [
    // === Database ===
    'db' => [
        'host' => DB_HOST,
        'name' => DB_NAME,
        'user' => DB_USER,
        'pass' => DB_PASS,
        'charset' => 'utf8mb4',
    ],

    // === Anthropic API ===
    'anthropic_api_key' => 'sk-ant-REPLACE_ME',
    'model'             => 'claude-haiku-4-5-20251001', // fast + cheap; use claude-sonnet-4-6 for higher quality
    'max_tokens'        => 1024,

    // === Admin ===
    'admin_pin' => '1234', // CHANGE THIS — used to log into admin.php

    // === Embed ===
    // The public URL where this folder lives, e.g. https://agent.wiredhowse.com
    // Used by the widget snippet shown in the admin. No trailing slash.
    'public_url' => 'https://agent.example.com',
];
