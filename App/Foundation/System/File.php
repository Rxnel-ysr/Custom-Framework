<?php

namespace App\Foundation\System;

use Exception;

class File
{
    private string $filename;

    public function __construct(string $filename)
    {
        if (!is_file($filename)) {
            throw new Exception("File [$filename] not found");
        }
        $this->filename = $filename;

        return $this;
    }

    public function read(): string
    {
        return file_get_contents($this->filename);
    }

    public function delete(): bool
    {
        return unlink($this->filename);
    }

    public function move(string $destination): bool
    {
        $res = rename($this->filename, $destination);
        $res && $this->filename = $destination;
        return $res;
    }

    public function write(string $data, bool $append = false)
    {
        $mode = $append ? 'ab' : 'wb';
        $handle = fopen($this->filename, $mode);
        fwrite($handle, $data);
        fclose($handle);
    }

    public function isWritable(): bool
    {
        return is_writable($this->filename);
    }

    public function isReadable(): bool
    {
        return is_readable($this->filename);
    }

    public function size(): int
    {
        return filesize($this->filename);
    }

    public function getType(): string
    {
        return filetype($this->filename);
    }

    public function getLastEditTimestamps(): string
    {
        return date('d-m-y h:i:s', filemtime($this->filename));
    }

    public function getPerms(): int
    {
        return fileperms($this->filename);
    }

    public function setPerm(int $code = 0644): bool
    {
        return chmod($this->filename, $code);
    }

    public function getModificationTime(): int
    {
        return filemtime($this->filename);
    }

    public function getCreationTime(): int
    {
        return filectime($this->filename);
    }


    public function checksum(string $algo = 'sha256'): string
    {
        return hash_file($algo, $this->filename);
    }
}
