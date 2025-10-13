<?php

namespace NkCardFlow\Config;

use InvalidArgumentException;

class Config
{
    public function __construct(private array $config)
    {
    }

    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $segments = explode('.', $path);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                if (func_num_args() > 1) {
                    return $default;
                }

                throw new InvalidArgumentException(sprintf('Configuration key "%s" not found', $path));
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
