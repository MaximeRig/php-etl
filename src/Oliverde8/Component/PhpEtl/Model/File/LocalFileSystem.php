<?php

declare(strict_types=1);

namespace Oliverde8\Component\PhpEtl\Model\File;

use Symfony\Component\Filesystem\Filesystem;

class LocalFileSystem implements FileSystemInterface
{
    protected string $rootPath;

    protected Filesystem $filesystem;

    /**
     * @param $rootPath
     */
    public function __construct($rootPath = null)
    {
        if (!$rootPath) {
            $rootPath = getcwd();
        }

        $this->rootPath = $rootPath;
        $this->filesystem = new Filesystem();
    }

    /**
     * @return string
     */
    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    public function fileExists(string $path): bool
    {
        return $this->filesystem->exists($this->rootPath . "/" . $path);
    }

    public function write(string $path, string $contents, array $config = []): void
    {
        $this->filesystem->dumpFile($this->rootPath . "/" . $path, $contents);
    }

    public function writeStream(string $path, $contents, array $config = []): void
    {
        stream_copy_to_stream($contents, fopen($this->rootPath . "/" . $path, 'w+'));
    }

    public function read(string $path): string
    {
        return file_get_contents($this->rootPath . "/" . $path);
    }

    public function readStream(string $path)
    {
        return fopen($this->rootPath . "/" . $path, 'r');
    }

    public function delete(string $path): void
    {
        $this->filesystem->remove($this->rootPath . "/" . $path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->filesystem->remove($this->rootPath . "/" . $path);
    }

    public function createDirectory(string $path, array $config = []): void
    {
        $this->filesystem->mkdir($this->rootPath . "/" . $path);
    }

    public function listContents(string $path): array
    {
        $files = scandir($this->rootPath . "/" .$path);
        return $files ?: [];
    }

    public function move(string $source, string $destination, array $config = [])
    {
        $this->filesystem->rename($this->rootPath . "/" . $source, $this->rootPath . "/" . $destination);
    }

    public function copy(string $source, string $destination, array $config = [])
    {
        $this->filesystem->copy($this->rootPath . "/" . $source, $this->rootPath . "/" . $destination);
    }
}