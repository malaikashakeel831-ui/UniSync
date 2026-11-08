<?php
/**
 * UniSync configuration.
 *
 * Do NOT put real passwords in this file (it is committed to GitHub).
 * Copy app/config.local.example.php to app/config.local.php and put your
 * own database credentials there. config.local.php is ignored by git.
 */

$config = [
    'app_name'  => 'UniSync',
    'db_host'   => '127.0.0.1',
    'db_port'   => 3306,
    'db_name'   => 'unisync',
    'db_user'   => 'unisync',
    'db_pass'   => '',
    'base_url'  => null,              // null = auto-detect; or e.g. '/unisync/public'
    'timezone'  => 'Asia/Karachi',
    'debug'     => false,
    'session_timeout_minutes' => 120,
    'max_failed_logins'       => 5,
    'lockout_minutes'         => 15,
];

// Environment variables (useful for Docker / cloud hosting)
foreach (['DB_HOST' => 'db_host', 'DB_PORT' => 'db_port', 'DB_NAME' => 'db_name',
          'DB_USER' => 'db_user', 'DB_PASS' => 'db_pass', 'BASE_URL' => 'base_url'] as $env => $key) {
    $v = getenv($env);
    if ($v !== false && $v !== '') {
        $config[$key] = $v;
    }
}

// Local overrides (not committed)
$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_merge($config, require $local);
}

return $config;
