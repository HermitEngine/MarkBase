<?php

declare(strict_types=1);

namespace MarkBase;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;

final class MarkdownRenderer
{
    private const CACHE_VERSION = 7;

    private CommonMarkConverter $converter;
    private LinkResolver $linkResolver;
    private FenBoardRenderer $fenBoardRenderer;
    private string $cacheDir;
    private string $basePath;

    public function __construct(LinkResolver $linkResolver, string $cacheDir, string $basePath = '')
    {
        $this->linkResolver = $linkResolver;
        $this->basePath = rtrim($basePath, '/');
        $this->fenBoardRenderer = new FenBoardRenderer($this->basePath);
        $this->cacheDir = rtrim($cacheDir, '/');
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }

        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $this->converter = new CommonMarkConverter([], $environment);
    }

    public function renderFile(string $path, string $filePath): string
    {
        $markdown = file_get_contents($filePath) ?: '';
        $cacheKey = md5((string) self::CACHE_VERSION . '|' . $this->basePath . '|' . $path . '|' . md5($markdown));
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.html';
        if (is_file($cacheFile)) {
            return file_get_contents($cacheFile) ?: '';
        }
        $html = $this->render($path, $markdown);
        file_put_contents($cacheFile, $html);
        return $html;
    }

    public function render(string $path, string $markdown): string
    {
        $markdown = $this->linkResolver->rewriteWikiLinks($path, $markdown);
        $html = (string) $this->converter->convert($markdown);
        return $this->rewriteHtmlLinks($path, $html);
    }

    private function rewriteHtmlLinks(string $path, string $html): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $this->rewriteFenBlocks($dom);

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = $anchor->getAttribute('href');
            if ($href === '') {
                continue;
            }
            $resolved = $this->linkResolver->resolveMarkdownLink($path, $href);
            if ($resolved !== null) {
                $anchor->setAttribute('href', $resolved);
            }
        }

        foreach ($dom->getElementsByTagName('img') as $img) {
            $src = $img->getAttribute('src');
            if ($src === '') {
                continue;
            }
            $resolved = $this->linkResolver->resolveImageLink($src);
            if ($resolved !== null) {
                $img->setAttribute('src', $resolved);
            }
        }

        $output = $dom->saveHTML() ?: $html;
        return preg_replace('/^<\?xml encoding="utf-8" \?>\s*/', '', $output) ?? $output;
    }

    private function rewriteFenBlocks(\DOMDocument $dom): void
    {
        $preNodes = [];
        foreach ($dom->getElementsByTagName('pre') as $pre) {
            $preNodes[] = $pre;
        }

        foreach ($preNodes as $pre) {
            $code = $this->firstCodeChild($pre);
            if ($code === null || !$this->isFenCodeBlock($code)) {
                continue;
            }

            $fen = $code->textContent;
            $board = $this->fenBoardRenderer->render($dom, $fen);
            if ($board === null) {
                $parsed = $this->fenBoardRenderer->parse($fen);
                $pre->setAttribute('class', trim($pre->getAttribute('class') . ' fen-board-source fen-board-source--invalid'));
                $pre->setAttribute('data-fen-error', $parsed['error'] ?? 'Invalid FEN.');
                continue;
            }

            if ($pre->parentNode !== null) {
                $pre->parentNode->replaceChild($board, $pre);
            }
        }
    }

    private function firstCodeChild(\DOMElement $pre): ?\DOMElement
    {
        foreach ($pre->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->tagName) === 'code') {
                return $child;
            }
        }
        return null;
    }

    private function isFenCodeBlock(\DOMElement $code): bool
    {
        $class = ' ' . strtolower($code->getAttribute('class')) . ' ';
        return str_contains($class, ' language-fen ') || str_contains($class, ' fen ');
    }
}
