<?php
declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class HttpException extends RuntimeException
{
    private int $statusCode;

    /** @var array<string,mixed>|null */
    private ?array $response;

    /**
     * @param int $statusCode HTTP статус ответа
     * @param string $message Сообщение об ошибке
     * @param array<string,mixed>|null $response Полезная нагрузка ответа
     */
    public function __construct(int $statusCode, string $message, ?array $response = null)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->response = $response;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }
}
