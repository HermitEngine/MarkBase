<?php

declare(strict_types=1);

namespace MarkBase;

final class SearchIndexer
{
    private PageRepo $repo;
    private LinkResolver $linkResolver;
    private string $indexFile;

    public function __construct(PageRepo $repo, LinkResolver $linkResolver, string $indexFile)
    {
        $this->repo = $repo;
        $this->linkResolver = $linkResolver;
        $this->indexFile = $indexFile;
        $dir = dirname($this->indexFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    public function buildIndex(): array
    {
        $pages = [];
        $index = [];
        $backlinks = [];

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->repo->getDocRoot(), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iter as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'md') {
                continue;
            }
            $path = $this->repo->relativePath($file->getPathname());
            $markdown = file_get_contents($file->getPathname()) ?: '';
            $text = $this->stripMarkdown($markdown);
            $title = $this->extractTitle($markdown, $path);
            $tokens = $this->tokenize($text);

            $pages[$path] = [
                'title' => $title,
                'text' => $text,
                'tokens' => $tokens,
            ];

            foreach ($tokens as $token) {
                $index[$token][] = $path;
            }

            foreach ($this->linkResolver->extractLinks($markdown) as $link) {
                $resolved = $this->normalizeLinkTarget($path, $link);
                if ($resolved === null) {
                    continue;
                }
                $backlinks[$resolved] ??= [];
                if (!in_array($path, $backlinks[$resolved], true)) {
                    $backlinks[$resolved][] = $path;
                }
            }
        }

        foreach ($index as $token => $paths) {
            $index[$token] = array_values(array_unique($paths));
        }
        foreach ($backlinks as $target => $paths) {
            sort($paths);
            $backlinks[$target] = $paths;
        }

        $payload = [
            'updated_at' => time(),
            'pages' => $pages,
            'index' => $index,
            'backlinks' => $backlinks,
        ];

        file_put_contents($this->indexFile, json_encode($payload, JSON_PRETTY_PRINT));
        return $payload;
    }

    public function loadIndex(): array
    {
        $current = $this->repo->maxMtime();
        if (is_file($this->indexFile)) {
            $data = json_decode(file_get_contents($this->indexFile) ?: '', true);
            if (is_array($data) && ($data['updated_at'] ?? 0) >= $current) {
                return $data;
            }
        }
        return $this->buildIndex();
    }

    public function search(string $query): array
    {
        $index = $this->loadIndex();
        $tokens = $this->tokenize($query);
        if ($tokens === []) {
            return [];
        }
        $scores = [];
        foreach ($tokens as $token) {
            foreach ($index['index'][$token] ?? [] as $path) {
                $scores[$path] = ($scores[$path] ?? 0) + 1;
            }
        }
        arsort($scores);

        $results = [];
        foreach ($scores as $path => $score) {
            $page = $index['pages'][$path] ?? null;
            if ($page === null) {
                continue;
            }
            $results[] = [
                'path' => $path,
                'title' => $page['title'],
                'snippet' => $this->makeSnippet($page['text'], $tokens),
                'score' => $score,
            ];
        }
        return $results;
    }

    public function searchPaths(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $index = $this->loadIndex();
        $matches = [];
        foreach (($index['pages'] ?? []) as $path => $page) {
            $title = (string) ($page['title'] ?? $path);
            $titlePos = stripos($title, $query);
            $pathPos = stripos($path, $query);
            if ($titlePos === false && $pathPos === false) {
                continue;
            }
            $matches[] = [
                'path' => $path,
                'title_pos' => $titlePos === false ? PHP_INT_MAX : $titlePos,
                'path_pos' => $pathPos === false ? PHP_INT_MAX : $pathPos,
            ];
        }

        usort($matches, function (array $a, array $b): int {
            if ($a['title_pos'] !== $b['title_pos']) {
                return $a['title_pos'] <=> $b['title_pos'];
            }
            if ($a['path_pos'] !== $b['path_pos']) {
                return $a['path_pos'] <=> $b['path_pos'];
            }
            return strcasecmp($a['path'], $b['path']);
        });

        return array_map(fn(array $row) => $row['path'], $matches);
    }

    public function getBacklinks(string $path): array
    {
        $index = $this->loadIndex();
        return $index['backlinks'][$path] ?? [];
    }

    private function tokenize(string $text): array
    {
        $tokens = preg_split('/[^a-z0-9]+/i', strtolower($text)) ?: [];
        $tokens = array_filter($tokens, fn($t) => strlen($t) > 1);
        return array_values(array_unique($tokens));
    }

    private function stripMarkdown(string $markdown): string
    {
        $text = preg_replace('/```.*?```/s', ' ', $markdown) ?? $markdown;
        $text = preg_replace('/`[^`]+`/', ' ', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/\[\[([^\]]+)\]\]/', '$1', $text) ?? $text;
        $text = preg_replace('/[\#>*_~`\-]/', ' ', $text) ?? $text;
        return trim($text);
    }

    private function extractTitle(string $markdown, string $fallback): string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches)) {
            return trim($matches[1]);
        }
        return $fallback === '' ? 'Home' : $fallback;
    }

    private function makeSnippet(string $text, array $tokens): string
    {
        $lower = strtolower($text);
        $pos = null;
        $match = '';
        foreach ($tokens as $token) {
            $p = strpos($lower, $token);
            if ($p !== false && ($pos === null || $p < $pos)) {
                $pos = $p;
                $match = $token;
            }
        }
        if ($pos === null) {
            $snippet = substr($text, 0, 200);
        } else {
            $start = max(0, $pos - 60);
            $snippet = substr($text, $start, 200);
        }
        $escaped = htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8');
        if ($match !== '') {
            $escaped = preg_replace('/' . preg_quote($match, '/') . '/i', '<mark>$0</mark>', $escaped) ?? $escaped;
        }
        return $escaped;
    }

    private function normalizeLinkTarget(string $currentPath, string $link): ?string
    {
        if ($link === '') {
            return null;
        }
        $isWiki = false;
        if (str_starts_with($link, 'wiki:')) {
            $isWiki = true;
            $link = substr($link, 5);
        }
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $link)) {
            return null;
        }
        if (str_starts_with($link, '#')) {
            return null;
        }

        $link = trim($link);
        if (str_contains($link, '#')) {
            [$link] = explode('#', $link, 2);
        }
        $link = ltrim($link, '/');

        if (str_contains($link, '/')) {
            $normalized = $isWiki
                ? $this->repo->normalizePath($link)
                : $this->repo->normalizePath($this->joinPath($currentPath, $link));
            if (str_ends_with($normalized, '.md')) {
                $normalized = substr($normalized, 0, -3);
            }
            return $normalized;
        }

        $matches = $this->repo->findByFilename($link);
        if (count($matches) === 1) {
            return $matches[0];
        }
        return null;
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
}
