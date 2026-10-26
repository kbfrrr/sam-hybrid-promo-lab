<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use InvalidArgumentException;
use RuntimeException;

final class Settings
{
    public static function required(string $name): string
    {
        $value = getenv($name);
        if ($value === false || trim($value) === '') {
            throw new RuntimeException("Missing environment variable: {$name}");
        }
        return $value;
    }

    public static function integer(string $name, int $default, int $min, int $max): int
    {
        $raw = getenv($name);
        if ($raw === false || $raw === '') {
            return $default;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            throw new InvalidArgumentException("{$name} must be an integer from {$min} to {$max}.");
        }
        return $value;
    }

    public static function queue(): SqsClient
    {
        // The SDK's default credential chain uses AWS_PROFILE or classroom credentials.
        return new SqsClient([
            'version' => 'latest',
            'region' => self::required('AWS_REGION'),
            'http' => ['connect_timeout' => 10, 'timeout' => 35],
        ]);
    }

    public static function smtp(): array
    {
        return [
            'host' => self::required('MAILTRAP_HOST'),
            'port' => self::integer('MAILTRAP_PORT', 2525, 1, 65535),
            'username' => self::required('MAILTRAP_USERNAME'),
            'password' => self::required('MAILTRAP_PASSWORD'),
            'from' => self::required('MAIL_FROM_ADDRESS'),
        ];
    }

    public static function sendDelay(): int
    {
        return self::integer('MAILTRAP_DELAY_SECONDS', 11, 0, 120);
    }
}
