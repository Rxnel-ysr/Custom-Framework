<?php

namespace App\Foundation\Configuration;

use ArrayAccess;

class Config implements ArrayAccess
{
    public function __construct(
        protected string $root,
        protected string $cachePath,
        protected array $configs = []
    ) {}

    protected function cacheFile(): string
    {
        return rtrim($this->root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . ltrim($this->cachePath, DIRECTORY_SEPARATOR);
    }

    public function readCache(): static
    {
        $cfg = require $this->cacheFile();

        $this->configs = is_array($cfg) ? $cfg : [];

        return $this;
    }

    public function cached(array $except = []): static
    {
        $cacheFile = $this->cacheFile();
        $dirname = dirname($cacheFile);

        if (!is_dir($dirname)) {
            mkdir($dirname, 0777, true);
        }

        $toBeSaved = empty($except)
            ? $this->configs
            : array_filter(
                $this->configs,
                fn($key) => !in_array($key, $except),
                ARRAY_FILTER_USE_KEY
            );

        file_put_contents(
            $cacheFile,
            "<?php\nreturn " . var_export($toBeSaved, true) . ";\n"
        );

        return $this;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->configs[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->configs[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->configs[] = $value;
        } else {
            $this->configs[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->configs[$offset]);
    }
}
