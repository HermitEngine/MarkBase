<?php

declare(strict_types=1);

namespace MarkBase;

final class PageRepo
{
    private const DIRECTORY_README = 'README.md';

    private string $docRoot;
    private string $imgRoot;

    public function __construct(string $docRoot, string $imgRoot)
    {
        $this->docRoot = rtrim($docRoot, '/');
        $this->imgRoot = rtrim($imgRoot, '/');
    }

    public function getDocRoot(): string
    {
        return $this->docRoot;
    }

    public function getImgRoot(): string
    {
        return $this->imgRoot;
    }

    public function normalizePath(string $path): string
    {
        $path = trim($path);
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');
        if ($path === '') {
            return '';
        }
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    public function resolveDocFile(string $path): ?string
    {
        $normalized = $this->normalizePath($path);
        if ($normalized === '') {
            $rootIndex = $this->resolveDirectoryIndex('');
            if ($rootIndex !== null) {
                return $rootIndex;
            }
            $readme = $this->docRoot . '/' . self::DIRECTORY_README;
            if ($this->isPathAllowed($readme, $this->docRoot) && is_file($readme)) {
                return $readme;
            }
            return null;
        }

        if (!str_ends_with($normalized, '.md')) {
            $directory = $this->resolveDirectoryAbsolute($normalized);
            if ($directory !== null) {
                return $this->findIndexFileInDirectory($directory);
            }
        }

        $candidate = str_ends_with($normalized, '.md')
            ? ($this->docRoot . '/' . $normalized)
            : ($this->docRoot . '/' . $normalized . '.md');

        if ($this->isPathAllowed($candidate, $this->docRoot) && is_file($candidate)) {
            return $candidate;
        }

        return null;
    }

    public function readPage(string $path): ?string
    {
        $file = $this->resolveDocFile($path);
        if ($file === null) {
            return null;
        }
        return file_get_contents($file) ?: '';
    }

    public function writePage(string $path, string $content): string
    {
        $file = $this->resolveWriteTarget($path);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!$this->isPathAllowed($file, $this->docRoot)) {
            throw new \RuntimeException('Invalid path.');
        }
        file_put_contents($file, $content);
        return $file;
    }

    public function movePage(string $from, string $to): void
    {
        $fromPath = $this->normalizePath($from);
        $toPath = $this->normalizePath($to);

        if ($fromPath !== '' && $this->resolveDirectoryAbsolute($fromPath) !== null) {
            $this->moveDirectory($fromPath, $toPath);
            return;
        }

        $fromFile = $this->resolveDocFile($from);
        if ($fromFile === null) {
            throw new \RuntimeException('Source page not found.');
        }
        $toFile = $this->resolveWriteTarget($to);
        $dir = dirname($toFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!$this->isPathAllowed($toFile, $this->docRoot)) {
            throw new \RuntimeException('Invalid target path.');
        }
        rename($fromFile, $toFile);
    }

    private function moveDirectory(string $fromPath, string $toPath): void
    {
        if ($fromPath === '') {
            throw new \RuntimeException('Source folder not found.');
        }
        if ($toPath === '') {
            throw new \RuntimeException('Invalid target path.');
        }
        if ($toPath === $fromPath || str_starts_with($toPath, $fromPath . '/')) {
            throw new \RuntimeException('Cannot move a folder into itself.');
        }

        $fromDirectory = $this->resolveDirectoryAbsolute($fromPath);
        if ($fromDirectory === null) {
            throw new \RuntimeException('Source folder not found.');
        }

        if ($this->resolveDirectoryAbsolute($toPath) !== null || $this->resolveDocFile($toPath) !== null) {
            throw new \RuntimeException('Target path already exists.');
        }

        $toDirectory = $this->docRoot . '/' . $toPath;
        $parent = dirname($toDirectory);
        if (!is_dir($parent)) {
            mkdir($parent, 0775, true);
        }
        if (!$this->isPathAllowed($toDirectory, $this->docRoot)) {
            throw new \RuntimeException('Invalid target path.');
        }

        if (!rename($fromDirectory, $toDirectory)) {
            throw new \RuntimeException('Failed to move folder.');
        }
    }

    public function deletePage(string $path): void
    {
        $normalized = $this->normalizePath($path);
        if ($normalized !== '' && $this->resolveDirectoryAbsolute($normalized) !== null) {
            $this->deleteDirectory($normalized);
            return;
        }

        $file = $this->resolveDocFile($path);
        if ($file === null) {
            throw new \RuntimeException('Source page not found.');
        }
        if (!$this->isPathAllowed($file, $this->docRoot)) {
            throw new \RuntimeException('Invalid path.');
        }
        if (!unlink($file)) {
            throw new \RuntimeException('Failed to delete page.');
        }
    }

    private function deleteDirectory(string $path): void
    {
        if ($path === '') {
            throw new \RuntimeException('Cannot delete root folder.');
        }
        $directory = $this->resolveDirectoryAbsolute($path);
        if ($directory === null) {
            throw new \RuntimeException('Source folder not found.');
        }

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $item) {
            if ($item->isDir()) {
                if (!rmdir($item->getPathname())) {
                    throw new \RuntimeException('Failed to delete folder.');
                }
                continue;
            }
            if (!unlink($item->getPathname())) {
                throw new \RuntimeException('Failed to delete folder.');
            }
        }
        if (!rmdir($directory)) {
            throw new \RuntimeException('Failed to delete folder.');
        }
    }

    public function listTree(): array
    {
        return $this->buildTree($this->docRoot);
    }

    public function isDirectoryPath(string $path): bool
    {
        return $this->resolveDirectoryAbsolute($path) !== null;
    }

    public function resolveDirectoryIndex(string $path): ?string
    {
        $directory = $this->resolveDirectoryAbsolute($path);
        if ($directory === null) {
            return null;
        }
        return $this->findIndexFileInDirectory($directory);
    }

    public function listDirectory(string $path): array
    {
        $directory = $this->resolveDirectoryAbsolute($path);
        if ($directory === null) {
            return [];
        }

        $items = [];
        $handle = opendir($directory);
        if ($handle === false) {
            return $items;
        }

        $normalizedParent = $this->normalizePath($path);
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fullPath = $directory . '/' . $entry;
            if (is_dir($fullPath)) {
                $childPath = $normalizedParent === '' ? $entry : $normalizedParent . '/' . $entry;
                $items[] = [
                    'type' => 'dir',
                    'name' => $entry,
                    'path' => $this->normalizePath($childPath),
                ];
                continue;
            }
            if (!is_file($fullPath) || strcasecmp((string) pathinfo($entry, PATHINFO_EXTENSION), 'md') !== 0) {
                continue;
            }
            if (strcasecmp($entry, self::DIRECTORY_README) === 0) {
                continue;
            }
            $items[] = [
                'type' => 'file',
                'name' => (string) pathinfo($entry, PATHINFO_FILENAME),
                'path' => $this->relativePath($fullPath),
            ];
        }
        closedir($handle);

        usort($items, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }
            $cmp = strcasecmp($a['name'], $b['name']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a['name'], $b['name']);
        });

        return $items;
    }

    private function buildTree(string $dir): array
    {
        $items = [];
        $handle = opendir($dir);
        if ($handle === false) {
            return $items;
        }
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $items[] = [
                    'type' => 'dir',
                    'name' => $entry,
                    'children' => $this->buildTree($path),
                ];
            } elseif (is_file($path) && strcasecmp((string) pathinfo($entry, PATHINFO_EXTENSION), 'md') === 0) {
                $items[] = [
                    'type' => 'file',
                    'name' => $entry,
                    'path' => $this->relativePath($path),
                ];
            }
        }
        closedir($handle);
        usort($items, function ($a, $b) {
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'dir' ? -1 : 1;
            }
            $cmp = strcasecmp($a['name'], $b['name']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($a['name'], $b['name']);
        });
        return $items;
    }

    public function relativePath(string $filePath): string
    {
        $relative = ltrim(str_replace($this->docRoot, '', $filePath), '/');
        if (strlen($relative) >= 3 && strcasecmp(substr($relative, -3), '.md') === 0) {
            $relative = substr($relative, 0, -3);
        }
        return $relative;
    }

    public function findByFilename(string $filename): array
    {
        $matches = [];
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->docRoot, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iter as $file) {
            if ($file->isFile() && strcasecmp($file->getExtension(), 'md') === 0) {
                if ($file->getBasename('.md') === $filename) {
                    $matches[] = $this->relativePath($file->getPathname());
                }
            }
        }
        sort($matches);
        return $matches;
    }

    public function maxMtime(): int
    {
        $max = 0;
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->docRoot, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iter as $file) {
            if ($file->isFile() && strcasecmp($file->getExtension(), 'md') === 0) {
                $mtime = $file->getMTime();
                if ($mtime > $max) {
                    $max = $mtime;
                }
            }
        }
        return $max;
    }

    private function resolveDirectoryAbsolute(string $path): ?string
    {
        $normalized = $this->normalizePath($path);
        $directory = $normalized === '' ? $this->docRoot : ($this->docRoot . '/' . $normalized);
        if (!is_dir($directory)) {
            return null;
        }
        if (!$this->isWithinRoot($directory, $this->docRoot)) {
            return null;
        }
        return $directory;
    }

    private function findIndexFileInDirectory(string $directory): ?string
    {
        $handle = opendir($directory);
        if ($handle === false) {
            return null;
        }

        $matches = [];
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (!is_file($path)) {
                continue;
            }
            if (strcasecmp($entry, self::DIRECTORY_README) === 0) {
                $matches[] = $path;
            }
        }
        closedir($handle);

        if ($matches === []) {
            return null;
        }

        usort($matches, static function (string $a, string $b): int {
            $baseA = basename($a);
            $baseB = basename($b);
            $exactA = $baseA === self::DIRECTORY_README;
            $exactB = $baseB === self::DIRECTORY_README;
            if ($exactA !== $exactB) {
                return $exactA ? -1 : 1;
            }
            $cmp = strcasecmp($baseA, $baseB);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp($baseA, $baseB);
        });

        return $matches[0];
    }

    private function resolveWriteTarget(string $path): string
    {
        $normalized = $this->normalizePath($path);
        if ($normalized === '') {
            return $this->docRoot . '/' . self::DIRECTORY_README;
        }
        if (str_ends_with($normalized, '.md')) {
            return $this->docRoot . '/' . $normalized;
        }

        $directory = $this->resolveDirectoryAbsolute($normalized);
        if ($directory !== null) {
            return $this->findIndexFileInDirectory($directory) ?? ($directory . '/' . self::DIRECTORY_README);
        }

        return $this->docRoot . '/' . $normalized . '.md';
    }

    private function isWithinRoot(string $path, string $root): bool
    {
        $realRoot = realpath($root);
        $realPath = realpath($path);
        if ($realRoot === false || $realPath === false) {
            return false;
        }
        $realRoot = rtrim($realRoot, '/');
        return $realPath === $realRoot || str_starts_with($realPath, $realRoot . '/');
    }

    private function isPathAllowed(string $file, string $root): bool
    {
        $realRoot = realpath($root);
        $realFile = realpath(dirname($file));
        if ($realRoot === false || $realFile === false) {
            return false;
        }
        $realRoot = rtrim($realRoot, '/');
        return $realFile === $realRoot || str_starts_with($realFile, $realRoot . '/');
    }
}
