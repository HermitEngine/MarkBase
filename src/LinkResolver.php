<?php

declare(strict_types=1);

namespace MarkBase;

final class LinkResolver
{
    private PageRepo $repo;
    private string $basePath;

    public function __construct(PageRepo $repo, string $basePath = '')
    {
        $this->repo = $repo;
        $this->basePath = rtrim($basePath, '/');
    }

    public function resolveWikiLink(string $currentPath, string $target): array
    {
        $target = trim($target);
        if ($target === '') {
            return ['url' => '#', 'label' => $target, 'ambiguous' => false, 'candidates' => []];
        }
        if (str_contains($target, '/')) {
            $resolved = $this->repo->normalizePath($target);
            if (str_ends_with($resolved, '.md')) {
                $resolved = substr($resolved, 0, -3);
            }
            return ['url' => $this->viewUrl($resolved), 'label' => $target, 'ambiguous' => false, 'candidates' => []];
        }
        $matches = $this->repo->findByFilename($target);
        if (count($matches) === 1) {
            return ['url' => $this->viewUrl($matches[0]), 'label' => $target, 'ambiguous' => false, 'candidates' => []];
        }
        if (count($matches) > 1) {
            return ['url' => $this->scriptUrl('disambiguate.php') . '?name=' . rawurlencode($target), 'label' => $target, 'ambiguous' => true, 'candidates' => $matches];
        }
        $createPath = $this->repo->normalizePath($this->joinPath($currentPath, $target));
        return ['url' => $this->editUrl($createPath), 'label' => $target, 'ambiguous' => false, 'candidates' => []];
    }

    public function resolveMarkdownLink(string $currentPath, string $target): ?string
    {
        $target = trim($target);
        if ($target === '' || str_starts_with($target, '#')) {
            return $target;
        }
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $target)) {
            return $target;
        }
        $fragment = '';
        if (str_contains($target, '#')) {
            [$target, $fragment] = explode('#', $target, 2);
            $fragment = '#' . $fragment;
        }
        if (str_starts_with($target, '/')) {
            $normalized = $this->repo->normalizePath($target);
            if (str_ends_with($normalized, '.md')) {
                $normalized = substr($normalized, 0, -3);
            }
            return $this->viewUrl($normalized, $fragment);
        }

        $base = $this->repo->normalizePath($currentPath);
        $resolved = $this->repo->normalizePath($this->joinPath($base, $target));
        if (str_ends_with($resolved, '.md')) {
            $resolved = substr($resolved, 0, -3);
        }
        return $this->viewUrl($resolved, $fragment);
    }

    public function resolveImageLink(string $target): ?string
    {
        $target = trim($target);
        if ($target === '') {
            return null;
        }
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $target)) {
            return $target;
        }
        $normalized = $this->repo->normalizePath($target);
        if (str_starts_with($normalized, '../') || str_starts_with($normalized, '..')) {
            return null;
        }
        return $this->basePath . '/img/' . $normalized;
    }

    public function rewriteWikiLinks(string $currentPath, string $markdown): string
    {
        return preg_replace_callback('/\[\[([^\]]+)\]\]/', function ($matches) use ($currentPath) {
            $target = trim($matches[1]);
            $resolved = $this->resolveWikiLink($currentPath, $target);
            $label = $target === '' ? $matches[0] : $target;
            return '[' . $label . '](' . $resolved['url'] . ')';
        }, $markdown) ?? $markdown;
    }

    public function extractLinks(string $markdown): array
    {
        $links = [];
        if (preg_match_all('/\[[^\]]+\]\(([^)]+)\)/', $markdown, $matches)) {
            foreach ($matches[1] as $url) {
                $links[] = $url;
            }
        }
        if (preg_match_all('/\[\[([^\]]+)\]\]/', $markdown, $matches)) {
            foreach ($matches[1] as $wiki) {
                $links[] = 'wiki:' . trim($wiki);
            }
        }
        return $links;
    }

    private function joinPath(string $base, string $target): string
    {
        if ($base === '') {
            return $target;
        }
        $baseDir = str_contains($base, '/') ? dirname($base) : '';
        if ($baseDir === '.') {
            $baseDir = '';
        }
        return $baseDir === '' ? $target : $baseDir . '/' . $target;
    }

    private function scriptUrl(string $script): string
    {
        return $this->basePath . '/' . ltrim($script, '/');
    }

    private function viewUrl(string $path, string $fragment = ''): string
    {
        $normalized = $this->repo->normalizePath($path);
        if ($normalized === '') {
            return $this->scriptUrl('index.php') . $fragment;
        }
        return $this->scriptUrl('view.php') . '?path=' . rawurlencode($normalized) . $fragment;
    }

    private function editUrl(string $path): string
    {
        $normalized = $this->repo->normalizePath($path);
        if ($normalized === '') {
            return $this->scriptUrl('edit.php');
        }
        return $this->scriptUrl('edit.php') . '?path=' . rawurlencode($normalized);
    }
}
