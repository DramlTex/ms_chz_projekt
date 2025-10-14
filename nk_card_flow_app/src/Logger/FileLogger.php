<?php

namespace NkCardFlow\Logger;

use DateTimeImmutable;
use RuntimeException;

class FileLogger
{
    private string $file;
    private LogLevel $level;

    public function __construct(string $file, LogLevel $level = LogLevel::INFO)
    {
        $this->file = $file;
        $this->level = $level;

        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Unable to create log directory "%s"', $dir));
        }
    }

    public function log(LogLevel $level, string $message, array $context = []): void
    {
        if ($level->value < $this->level->value) {
            return;
        }

        $time = new DateTimeImmutable();
        $contextString = $this->interpolate($message, $context);
        $line = sprintf(
            "%s [%s] %s\n",
            $time->format(DateTimeImmutable::ATOM),
            $level->name,
            $contextString
        );

        file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $replace['{' . $key . '}'] = (string) $value;
            } elseif ($value instanceof \Stringable) {
                $replace['{' . $key . '}'] = (string) $value;
            } else {
                $replace['{' . $key . '}'] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return strtr($message, $replace);
    }
}
