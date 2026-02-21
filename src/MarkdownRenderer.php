<?php

declare(strict_types=1);

namespace MarkBase;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;

final class MarkdownRenderer
{
    private CommonMarkConverter $converter;
    private LinkResolver $linkResolver;
    private string $cacheDir;

    public function __construct(LinkResolver $linkResolver, string $cacheDir)
    {
        $this->linkResolver = $linkResolver;
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
        $mtime = filemtime($filePath) ?: 0;
        $cacheKey = md5($path . '|' . $mtime);
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.html';
        if (is_file($cacheFile)) {
            return file_get_contents($cacheFile) ?: '';
        }
        $markdown = file_get_contents($filePath) ?: '';
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

        return $dom->saveHTML() ?: $html;
    }
}
