<?php
declare(strict_types=1);

namespace App\Support;

use App\Exceptions\HttpException;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class HttpHelpers
{
    public static function requireMethod(string $method): void
    {
        $incoming = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($incoming !== strtoupper($method)) {
            header('Allow: ' . strtoupper($method));
            http_response_code(405);
            self::json(['error' => 'Метод не поддерживается.']);
            exit;
        }
    }

    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string,mixed>
     */
    public static function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false) {
            throw new InvalidArgumentException('Не удалось прочитать тело запроса.');
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Некорректный JSON: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('JSON должен содержать объект или массив.');
        }

        return $decoded;
    }

    public static function handleException(Throwable $exception): void
    {
        $status = 500;
        $payload = ['error' => $exception->getMessage()];

        if ($exception instanceof HttpException) {
            $status = $exception->getStatusCode();
            if ($exception->getResponse() !== null) {
                $payload['details'] = $exception->getResponse();
            }
        } elseif ($exception instanceof InvalidArgumentException) {
            $status = 400;
        }

        self::json($payload, $status);
    }
}
