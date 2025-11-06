<?php

declare(strict_types=1);

namespace Miso\Support;

class Filesystem
{
    public function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException("Unable to create directory [$path].");
        }
    }

    public function emptyDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);

        foreach ($items as $item) {
            $this->deletePath($item->getPathname());
        }
    }

    public function deletePath(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            $items = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);
            foreach ($items as $item) {
                $this->deletePath($item->getPathname());
            }
            rmdir($path);
        } elseif (is_file($path) || is_link($path)) {
            unlink($path);
        }
    }

    public function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        $this->ensureDirectory($destination);

        $items = new \FilesystemIterator($source, \FilesystemIterator::SKIP_DOTS);
        foreach ($items as $item) {
            $destPath = $destination . DIRECTORY_SEPARATOR . $item->getBasename();

            if ($item->isDir()) {
                $this->copyDirectory($item->getPathname(), $destPath);
            } else {
                if (!copy($item->getPathname(), $destPath)) {
                    throw new \RuntimeException("Failed to copy [{$item->getPathname()}] to [$destPath].");
                }
            }
        }
    }

    public function writeFile(string $path, string $contents): void
    {
        $dir = dirname($path);
        $this->ensureDirectory($dir);

        if (false === file_put_contents($path, $contents)) {
            throw new \RuntimeException("Failed to write file [$path].");
        }
    }
}
