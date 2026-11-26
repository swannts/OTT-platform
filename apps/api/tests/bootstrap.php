<?php

$testDatabase = getenv('STAGING_TEST_DATABASE') ?: 'swann_test';
if (! preg_match('/^swann_test(?:_[a-zA-Z0-9_-]+)?$/', $testDatabase)) {
    throw new RuntimeException('STAGING_TEST_DATABASE must use the isolated swann_test[_suffix] naming convention.');
}

$testingEnvironment = [
    'APP_ENV' => 'testing',
    'BCRYPT_ROUNDS' => '4',
    'CACHE_DRIVER' => 'array',
    'DB_CONNECTION' => 'mysql',
    'DB_DATABASE' => $testDatabase,
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'TELESCOPE_ENABLED' => 'false',
];

foreach ($testingEnvironment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$databaseHost = getenv('DB_HOST') ?: '127.0.0.1';
$databasePort = getenv('DB_PORT') ?: '3306';
$databaseUser = getenv('DB_USERNAME') ?: 'root';
$databasePassword = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$databaseHost};port={$databasePort};charset=utf8mb4",
        $databaseUser,
        $databasePassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$testDatabase.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (Throwable $error) {
    throw new RuntimeException("Could not prepare the isolated {$testDatabase} database for PHPUnit.", 0, $error);
}

require dirname(__DIR__).'/vendor/autoload.php';
