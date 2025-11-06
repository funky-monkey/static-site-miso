<?php

declare(strict_types=1);

namespace Miso\Scaffold;

use Miso\Support\Filesystem;

class Scaffolder
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $skeletonPath,
    ) {
        if (!is_dir($this->skeletonPath)) {
            throw new \RuntimeException("Skeleton directory [{$this->skeletonPath}] not found.");
        }
    }

    public function scaffold(string $target, bool $force = false): void
    {
        $target = rtrim($target, DIRECTORY_SEPARATOR);

        if (file_exists($target) && !is_dir($target)) {
            throw new \RuntimeException("Target [$target] exists and is not a directory.");
        }

        if (!is_dir($target)) {
            $this->filesystem->ensureDirectory($target);
        } elseif (!$force && !$this->isDirectoryEmpty($target)) {
            throw new \RuntimeException("Target directory [$target] is not empty. Pass --force to overwrite.");
        }

        $this->filesystem->copyDirectory($this->skeletonPath, $target);
    }

    private function isDirectoryEmpty(string $path): bool
    {
        $iterator = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);

        return !$iterator->valid();
    }
}
