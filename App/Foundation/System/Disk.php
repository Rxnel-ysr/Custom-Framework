<?php

namespace App\Foundation\System;

use Exception;
use FilesystemIterator;
use ZipArchive;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Stringable;

class Disk implements Stringable
{
    public string $root;

    /**
     * Initializes a Disk with a given root directory.
     *
     * @param string $dir Root directory for all operations.
     * @throws Exception If the given directory is not valid.
     */
    public function __construct(string $root)
    {
        if (!is_dir($root)) {
            throw new Exception("[$root] is not a directory.");
        }
        $this->root = rtrim(realpath($root), DIRECTORY_SEPARATOR);
    }

    public function __toString(): string
    {
        return $this->root;
    }

    /**
     * Normalizes a relative path to full path under root.
     *
     * @param string $path Relative path.
     * @return string Normalized full path.
     */
    private function relative(string $path): string
    {
        if (str_starts_with($path, $this->root)) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        return $this->root . DIRECTORY_SEPARATOR . $this->normalize($path);
    }

    /**
     * Normalize a filesystem path according to the current OS directory separator.
     *
     * Converts all slashes to the appropriate format (`'/'` for Unix, `'\\'` for Windows)
     *
     * @param string $path The path to normalize.
     *
     * @return string The normalized path with proper directory separators for the current OS.
     */
    public static function normalize(string $path): string
    {
        $path = rtrim($path, '/\\');
        return  str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }


    /**
     * Reads a file in chunks.
     *
     * @param string $file_path Relative path to the file.
     * @param int $bytes Number of bytes to read per chunk.
     * @param bool $return If true, returns the content; otherwise, echoes it.
     * @return string|null File content if $return is true, otherwise null.
     * @throws Exception If file doesn't exist or reading fails.
     */

    public function read(string $file_path, int $bytes = 4096, bool $return = false): ?string
    {
        $fullPath = $this->relative($file_path);

        if (!file_exists($fullPath)) {
            throw new Exception('File does not exist: ' . $fullPath);
        }

        $handle = fopen($fullPath, 'rb');
        if (!$handle) {
            throw new Exception('Cannot open file: ' . $fullPath);
        }

        $data = $return ? '' : null;

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, $bytes);
                if ($chunk === false) {
                    throw new Exception('Error reading file: ' . $fullPath);
                }
                $return ? $data .= $chunk : print($chunk);
            }
            return $data;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Writes data to a file.
     *
     * @param string $file_path Relative path to the file.
     * @param string $data The content to write.
     * @param bool $append If true, appends to the file; otherwise, overwrites.
     * @return void
     */
    public function write(string $file_path, string $data, bool $append = false): void
    {
        $mode = $append ? 'ab' : 'wb';
        $fullPath = $this->relative($file_path);

        $handle = fopen($fullPath, $mode);
        if (!$handle) {
            throw new Exception('Cannot open file for writing: ' . $fullPath);
        }

        fwrite($handle, $data);
        fclose($handle);
    }

    /**
     * Deletes a file or an empty directory.
     *
     * @param string $path Relative path to the file or directory.
     * @return bool True on success, false on failure.
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->relative($path);

        return is_dir($fullPath) ? rmdir($fullPath) : unlink($fullPath);
    }

    /**
     * Checks whether a file or directory exists.
     *
     * @param string $path Relative path to check.
     * @return bool True if it exists, false otherwise.
     */
    public function exist(string $path): bool
    {
        return file_exists($this->relative($path));
    }

    /**
     * Recursively copies files and directories.
     *
     * @param string $path Source relative path.
     * @param string $destination_path Destination relative path.
     * @param bool $overwrite Whether to overwrite existing files.
     * @param int $bytes Number of bytes to copy per chunk.
     * @return void
     * @throws Exception If source doesn't exist or destination exists and overwrite is false.
     */
    public function copy(string $path, string $destination_path, bool $overwrite = false, int $bytes = 4096): void
    {
        $source = $this->relative($path);
        $destination = $this->relative($destination_path);

        if (is_dir($source)) {
            if (!is_dir($destination)) {
                mkdir($destination, 0755, true);
            }
            foreach (scandir($source) as $item) {
                if ($item === '.' || $item === '..') continue;
                $this->copy("$path/$item", "$destination_path/$item", $overwrite, $bytes);
            }
            return;
        }

        if (!file_exists($source)) {
            throw new Exception('Source file does not exist: ' . $source);
        }

        if (file_exists($destination) && !$overwrite) {
            throw new Exception('Destination already exists: ' . $destination);
        }

        $srcHandle = fopen($source, 'rb');
        $dstHandle = fopen($destination, 'wb');

        try {
            while (!feof($srcHandle)) {
                fwrite($dstHandle, fread($srcHandle, $bytes));
            }
        } finally {
            fclose($srcHandle);
            fclose($dstHandle);
        }
    }

    /**
     * Moves files or directories recursively.
     *
     * @param string $path Source relative path.
     * @param string $destination_path Destination relative path.
     * @param bool $overwrite Whether to overwrite existing files.
     * @param int $bytes Number of bytes to move per chunk.
     * @return void
     * @throws Exception If source doesn't exist or destination exists and overwrite is false.
     */
    public function move(string $path, string $destination_path, bool $overwrite = false, int $bytes = 4096): void
    {
        $this->copy($path, $destination_path, $overwrite, $bytes);
        $this->delete($path);
    }

    /**
     * Calculates total size of a file or directory recursively.
     *
     * @param string $path Relative path to file or directory.
     * @return int Total size in bytes.
     */
    public function size(string $path): int
    {
        $fullPath = $this->relative($path);

        if (is_dir($fullPath)) {
            $size = 0;
            foreach (scandir($fullPath) as $item) {
                if ($item === '.' || $item === '..') continue;
                $size += $this->size("$path/$item");
            }
            return $size;
        }

        return filesize($fullPath) ?: 0;
    }

    /**
     * Returns the absolute real path if it exists.
     *
     * @param string $path Relative path.
     * @return string|null Absolute path or null if the file doesn't exist.
     */
    public function path(string $path): ?string
    {
        return realpath($this->relative($path));
    }


    /**
     * Creates a directory at the specified path.
     * 
     * If the directory already exists, the function returns `true` without any action.
     * 
     * @param string $path The directory path to be created
     * @param int $permissions Permissions to set for the new directory (default: `0755`)
     * @param bool $recursive Whether to create directories recursively if needed (default: `true`)
     * 
     * @return bool Returns `true` if the directory was created or already exists, `false` otherwise
     */
    public function mkdir(string $path, int $permissions = 0755, bool $recursive = true): bool
    {
        $fullPath = $this->relative($path);
        if (is_dir($fullPath)) {
            return true;
        }

        return mkdir($fullPath, $permissions, $recursive);
    }


    /**
     * Renames or moves a file or directory.
     * 
     * The source file must exist, and the destination must not already exist.
     * Throws an exception if either of these conditions is not met.
     * 
     * @param string $old_path The current path of the file or directory
     * @param string $new_path The new path for the file or directory
     * 
     * @return bool Returns `true` if the rename operation was successful, `false` otherwise
     * 
     * @throws Exception If the source path does not exist or the destination already exists
     */
    public function rename(string $old_path, string $new_path): bool
    {
        $old = $this->relative($old_path);
        $new = $this->relative($new_path);

        if (!file_exists($old)) {
            throw new Exception('Source does not exist: ' . $old);
        }

        if (file_exists($new)) {
            throw new Exception('Destination already exists: ' . $new);
        }

        return rename($old, $new);
    }

    /**
     * Compresses the entire root directory into a zip file.
     *
     * @param string $destination Destination zip file path.
     * @return bool True on success.
     * @throws Exception If compression fails.
     */
    public function compress(string $destination): bool
    {
        $destination = $this->relative($destination);

        $zip = new ZipArchive();
        if (!$zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new Exception('Cannot create zip file: ' . $destination);
        }

        $source = realpath($this->root);
        if (!$source || !is_dir($source)) {
            throw new Exception('Invalid source directory: ' . $this->root);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($source) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } elseif ($file->isFile()) {
                if (!$zip->addFile($filePath, $relativePath)) {
                    throw new Exception('Failed to add file: ' . $filePath);
                }
            }
        }

        return $zip->close();
    }

    /**
     * Recursively clears all contents inside the root directory,
     * leaving the root folder itself.
     * 
     * @return void
     * @throws Exception If deletion of any item fails.
     */
    public function cleanDir(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                if (!rmdir($item->getPathname())) {
                    throw new Exception("Failed to remove directory: {$item->getPathname()}");
                }
            } else {
                if (!unlink($item->getPathname())) {
                    throw new Exception("Failed to delete file: {$item->getPathname()}");
                }
            }
        }
    }

    public function forEach(?callable $fn = null): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $result = [];

        foreach ($iterator as $item) {
            $result[] = is_callable($fn) ? $fn($item) : $item;
        }

        return $result;
    }
}
