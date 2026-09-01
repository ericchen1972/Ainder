<?php

declare(strict_types=1);

function ainder_config(): array
{
    static $config;

    if (is_array($config)) {
        return $config;
    }

    if (!defined('SWEETY_MYSQL_CONFIG_ONLY')) {
        define('SWEETY_MYSQL_CONFIG_ONLY', true);
    }

    require dirname(__DIR__, 2).'/mysql.php';

    $localPath = dirname(__DIR__).'/config.local.php';
    $local = is_file($localPath) ? require $localPath : [];

    $config = [
        'db_host' => $mysqlhost,
        'db_user' => $mysqluser,
        'db_password' => $mysqlpasswd,
        'db_name' => 'ainder',
        'google_client_id' => getenv('AINDER_GOOGLE_CLIENT_ID')
            ?: (string) ($local['google_client_id'] ?? ''),
        'unsplash_access_key' => (string) ($local['unsplash_access_key'] ?? ''),
        'upload_signing_key' => (string) ($local['upload_signing_key'] ?? ''),
        'public_base_url' => rtrim(
            (string) ($local['public_base_url'] ?? 'https://sweety.tw/ainder'),
            '/'
        ),
    ];

    return $config;
}
