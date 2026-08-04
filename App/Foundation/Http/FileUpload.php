<?php

namespace App\Foundation\Http;

use App\Foundation\Exceptions\Framework\LowLevelException;

class FileUploadException extends LowLevelException {}

class FileUpload
{
    public function __construct(
        private int $size,
        private string $tmp_path,
        private string $filename,
        private string $mimetype
    ) {}


    static public function fromArray(array $upload): self
    {

        if (!$upload['error'] == UPLOAD_ERR_OK) {
            throw new FileUploadException("Failed to upload file.");
        }

        return new self(
            $upload['size'],
            $upload['tmp_name'],
            $upload['name'],
            $upload['type']
        );
    }

    public function saveAs(string $filename): bool
    {
        return move_uploaded_file($this->tmp_path, $filename);
    }

    public function getTempName(): string
    {
        return $this->tmp_path;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getMimetype(): string
    {
        return $this->mimetype;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getExtension(): string
    {
        return mime_to_extension($this->mimetype);
    }
}
