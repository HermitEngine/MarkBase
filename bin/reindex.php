#!/usr/bin/env php
<?php

declare(strict_types=1);

use MarkBase\LinkResolver;
use MarkBase\PageRepo;
use MarkBase\SearchIndexer;

$baseDir = dirname(__DIR__);
$autoload = $baseDir . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(function (string $class) use ($baseDir) {
        if (!str_starts_with($class, 'MarkBase\\')) {
            return;
        }
        $relative = str_replace('MarkBase\\', '', $class);
        $relative = str_replace('\\', '/', $relative);
        $file = $baseDir . '/src/' . $relative . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
}

$docDir = $baseDir . '/doc';
$imgDir = $baseDir . '/img';
$cacheDir = getenv('WIKI_CACHE_DIR') ?: ($baseDir . '/cache');
$searchIndexFile = $cacheDir . '/search_index.json';

$repo = new PageRepo($docDir, $imgDir);
$linkResolver = new LinkResolver($repo);
$indexer = new SearchIndexer($repo, $linkResolver, $searchIndexFile);
$indexer->buildIndex();

echo "Search index rebuilt at {$searchIndexFile}\n";
