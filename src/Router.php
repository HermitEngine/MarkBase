<?php

declare(strict_types=1);

namespace MarkBase;

final class Router
{
    private string $basePath;

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function resolve(): array
    {
        $scriptAction = $this->resolveScriptAction();
        if ($scriptAction !== null) {
            if ($scriptAction === 'view') {
                return ['action' => 'view', 'path' => (string) ($_GET['path'] ?? '')];
            }
            if ($scriptAction === 'edit') {
                return ['action' => 'edit', 'path' => (string) ($_GET['path'] ?? '')];
            }
            return ['action' => $scriptAction];
        }

        $path = $this->getPath();
        if ($path === 'search') {
            return ['action' => 'search'];
        }
        if ($path === 'filter') {
            return ['action' => 'filter'];
        }
        if ($path === 'disambiguate') {
            return ['action' => 'disambiguate'];
        }
        if ($path === 'edit') {
            return ['action' => 'edit'];
        }
        if ($path === 'save') {
            return ['action' => 'save'];
        }
        if ($path === 'create') {
            return ['action' => 'create'];
        }
        if ($path === 'upload') {
            return ['action' => 'upload'];
        }
        if ($path === 'move') {
            return ['action' => 'move'];
        }
        if ($path === 'delete') {
            return ['action' => 'delete'];
        }
        if (str_starts_with($path, 'edit/')) {
            return ['action' => 'edit', 'path' => substr($path, 5)];
        }
        return ['action' => 'view', 'path' => $path];
    }

    private function resolveScriptAction(): ?string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $script = basename($path);

        return match ($script) {
            'index.php', 'view.php' => 'view',
            'search.php' => 'search',
            'filter.php' => 'filter',
            'disambiguate.php' => 'disambiguate',
            'edit.php' => 'edit',
            'save.php' => 'save',
            'create.php' => 'create',
            'upload.php' => 'upload',
            'move.php' => 'move',
            'delete.php' => 'delete',
            default => null,
        };
    }

    public function getPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }
        $path = trim($path, '/');
        return $path;
    }

    public function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}
