<?php

namespace NkCardFlow\Http;

class HttpResponse
{
    /**
     * @param array<int, string> $rawRequestHeaders
     */
    public function __construct(
        private int $statusCode,
        private string $body,
        private string $contentType,
        private array $rawRequestHeaders,
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getJson(): array
    {
        $data = json_decode($this->body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response: ' . $this->body);
        }

        return $data;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }
}
