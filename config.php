<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This laboratory uses PHP CLI entry scripts.');
}

date_default_timezone_set('UTC');

set_exception_handler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
    exit(1);
});

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Dependencies are missing. Run composer install in marketing-email-lab first.');
}
require $autoload;

// This CLI-only application and the AWS SDK read getenv(), so enable putenv support.
// Existing terminal variables take precedence over values in the optional .env file.
Dotenv\Dotenv::createUnsafeImmutable(__DIR__)->safeLoad();

// Configuration is lazy: offline entry scripts do not construct SQS/SMTP clients.
// Environment variables are read by Settings when the corresponding service is used.
