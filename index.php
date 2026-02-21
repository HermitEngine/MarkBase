<?php

declare(strict_types=1);

use MarkBase\LinkResolver;
use MarkBase\MarkdownRenderer;
use MarkBase\PageRepo;
use MarkBase\Router;
use MarkBase\SearchIndexer;

$baseDir = __DIR__;
$docDir = $baseDir . '/doc';
$imgDir = $baseDir . '/img';
$internalImgDir = $baseDir . '/img-internal';
$cacheDir = getenv('WIKI_CACHE_DIR') ?: ($baseDir . '/cache');
$htmlCacheDir = $cacheDir . '/html';
$treeCacheFile = $cacheDir . '/tree.json';
$searchIndexFile = $cacheDir . '/search_index.json';

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

$basePath = '/wiki';
$repo = new PageRepo($docDir, $imgDir);
$linkResolver = new LinkResolver($repo, $basePath);
$renderer = new MarkdownRenderer($linkResolver, $htmlCacheDir);
$indexer = new SearchIndexer($repo, $linkResolver, $searchIndexFile);
$router = new Router($basePath);

function serveAssetDir(PageRepo $repo, string $assetDir, string $basePath, string $urlSegment): void {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $prefix = rtrim($basePath, '/') . '/' . trim($urlSegment, '/') . '/';
    if (!str_starts_with($uriPath, $prefix)) {
        return;
    }
    $rel = substr($uriPath, strlen($prefix));
    $rel = $repo->normalizePath($rel);
    $file = $assetDir . '/' . $rel;
    $realRoot = realpath($assetDir);
    $realFile = realpath($file);
    if ($realRoot === false || $realFile === false || !str_starts_with($realFile, $realRoot) || !is_file($realFile)) {
        http_response_code(404);
        exit;
    }
    $type = mime_content_type($realFile) ?: 'application/octet-stream';
    header('Content-Type: ' . $type);
    readfile($realFile);
    exit;
}

serveAssetDir($repo, $internalImgDir, $basePath, 'img-internal');
serveAssetDir($repo, $imgDir, $basePath, 'img');

function render(string $template, array $vars = []): string {
    extract($vars);
    ob_start();
    include $template;
    return ob_get_clean() ?: '';
}

function rebuildSearchIndex(SearchIndexer $indexer): void
{
    $indexer->buildIndex();
}

function treeHasMissingFiles(PageRepo $repo, array $items): bool {
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['type'])) {
            return true;
        }
        if ($item['type'] === 'file') {
            $path = $repo->normalizePath((string) ($item['path'] ?? ''));
            if ($path === '' || $repo->resolveDocFile($path) === null) {
                return true;
            }
            continue;
        }
        if ($item['type'] === 'dir') {
            $children = $item['children'] ?? null;
            if (!is_array($children) || treeHasMissingFiles($repo, $children)) {
                return true;
            }
            continue;
        }
        return true;
    }
    return false;
}

function getTree(PageRepo $repo, string $treeCacheFile): array {
    $maxMtime = $repo->maxMtime();
    if (is_file($treeCacheFile)) {
        $data = json_decode(file_get_contents($treeCacheFile) ?: '', true);
        if (is_array($data) && ($data['updated_at'] ?? 0) >= $maxMtime) {
            $cachedTree = $data['tree'] ?? [];
            if (is_array($cachedTree) && !treeHasMissingFiles($repo, $cachedTree)) {
                return $cachedTree;
            }
        }
    }
    $tree = $repo->listTree();
    if (!is_dir(dirname($treeCacheFile))) {
        mkdir(dirname($treeCacheFile), 0775, true);
    }
    file_put_contents($treeCacheFile, json_encode(['updated_at' => time(), 'tree' => $tree], JSON_PRETTY_PRINT));
    return $tree;
}

function scriptUrl(string $basePath, string $script): string {
    return rtrim($basePath, '/') . '/' . ltrim($script, '/');
}

function pageUrl(string $basePath, string $path = ''): string {
    $normalized = trim(str_replace('\\', '/', $path));
    $normalized = ltrim($normalized, '/');
    if ($normalized === '') {
        return scriptUrl($basePath, 'index.php');
    }
    return scriptUrl($basePath, 'view.php') . '?path=' . rawurlencode($normalized);
}

function editUrl(string $basePath, string $path = ''): string {
    $normalized = trim(str_replace('\\', '/', $path));
    $normalized = ltrim($normalized, '/');
    if ($normalized === '') {
        return scriptUrl($basePath, 'edit.php');
    }
    return scriptUrl($basePath, 'edit.php') . '?path=' . rawurlencode($normalized);
}

function parentDocPath(string $path): string {
    $normalized = trim(str_replace('\\', '/', $path));
    $normalized = trim($normalized, '/');
    if ($normalized === '') {
        return '';
    }
    $lastSlash = strrpos($normalized, '/');
    if ($lastSlash === false) {
        return '';
    }
    return substr($normalized, 0, $lastSlash);
}

function splitDocPath(string $path): array {
    $normalized = trim(str_replace('\\', '/', $path));
    $normalized = trim($normalized, '/');
    if ($normalized === '') {
        return ['path' => '', 'name' => 'index'];
    }
    return [
        'path' => parentDocPath($normalized),
        'name' => basename($normalized),
    ];
}

function normalizeDocName(string $name): string {
    $normalized = trim(str_replace('\\', '/', $name));
    $normalized = trim($normalized, '/');
    if (strlen($normalized) >= 3 && strcasecmp(substr($normalized, -3), '.md') === 0) {
        $normalized = substr($normalized, 0, -3);
    }
    return $normalized;
}

function documentLabelFromPath(string $path): string
{
    $normalized = trim(str_replace('\\', '/', $path));
    $normalized = trim($normalized, '/');
    if ($normalized === '') {
        return 'Home';
    }
    return basename($normalized);
}

function buildBreadcrumbs(string $basePath, string $path): array {
    $breadcrumbs = [];
    $breadcrumbs[] = ['label' => 'Home', 'url' => pageUrl($basePath)];
    $normalized = trim(str_replace('\\', '/', $path));
    $normalized = ltrim($normalized, '/');
    if ($normalized === '') {
        return $breadcrumbs;
    }

    $parts = explode('/', $normalized);
    $accum = '';
    foreach ($parts as $part) {
        $accum = $accum === '' ? $part : $accum . '/' . $part;
        $breadcrumbs[] = ['label' => $part, 'url' => pageUrl($basePath, $accum)];
    }

    return $breadcrumbs;
}

function buildDirectoryIndexMarkdown(string $folderPath, array $items): string {
    $normalized = trim(str_replace('\\', '/', $folderPath), '/');
    $title = $normalized === '' ? 'Home' : basename($normalized);

    $dirItems = [];
    $fileItems = [];
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['type'], $item['name'], $item['path'])) {
            continue;
        }
        if ($item['type'] === 'dir') {
            $dirItems[] = $item;
            continue;
        }
        if ($item['type'] === 'file') {
            $fileItems[] = $item;
        }
    }

    $lines = [];
    $lines[] = '# ' . $title;
    $lines[] = '';
    $lines[] = '_Auto-generated folder index._';
    $lines[] = '';

    if ($dirItems === [] && $fileItems === []) {
        $lines[] = '_This folder is empty._';
        $lines[] = '';
        return implode("\n", $lines);
    }

    if ($dirItems !== []) {
        $lines[] = '## Folders';
        $lines[] = '';
        foreach ($dirItems as $item) {
            $target = (string) $item['path'];
            if ($normalized !== '' && str_starts_with($target, $normalized . '/')) {
                $target = substr($target, strlen($normalized) + 1);
            }
            $lines[] = '- [' . $item['name'] . '](' . $target . ')';
        }
        $lines[] = '';
    }

    if ($fileItems !== []) {
        $lines[] = '## Pages';
        $lines[] = '';
        foreach ($fileItems as $item) {
            $target = (string) $item['path'];
            if ($normalized !== '' && str_starts_with($target, $normalized . '/')) {
                $target = substr($target, strlen($normalized) + 1);
            }
            $lines[] = '- [' . $item['name'] . '](' . $target . ')';
        }
        $lines[] = '';
    }

    return implode("\n", $lines);
}

$action = $router->resolve();
$tree = getTree($repo, $treeCacheFile);

$currentPath = '';
$title = 'Home';
$content = '';

switch ($action['action']) {
    case 'search':
        $query = trim($_GET['q'] ?? '');
        $results = $query === '' ? [] : $indexer->search($query);
        $content = render($baseDir . '/templates/search.php', [
            'query' => $query,
            'results' => $results,
            'basePath' => $basePath,
        ]);
        $title = 'Search';
        break;
    case 'filter':
        $query = trim($_GET['q'] ?? '');
        $paths = $query === '' ? [] : $indexer->searchPaths($query);
        header('Content-Type: application/json');
        echo json_encode(['paths' => $paths]);
        exit;
    case 'disambiguate':
        $name = trim($_GET['name'] ?? '');
        $matches = $name === '' ? [] : $repo->findByFilename($name);
        $content = render($baseDir . '/templates/disambiguate.php', [
            'name' => $name,
            'matches' => $matches,
            'basePath' => $basePath,
        ]);
        $title = 'Disambiguation';
        break;
    case 'edit':
        $currentPath = $repo->normalizePath($action['path'] ?? ($_GET['path'] ?? ''));
        $contentMd = $repo->readPage($currentPath);
        if ($contentMd === null && $repo->isDirectoryPath($currentPath)) {
            $items = $repo->listDirectory($currentPath);
            $contentMd = buildDirectoryIndexMarkdown($currentPath, $items);
            $repo->writePage($currentPath, $contentMd);
            rebuildSearchIndex($indexer);
        }
        if ($contentMd === null) {
            $contentMd = '';
        }
        $content = render($baseDir . '/templates/edit.php', [
            'path' => $currentPath,
            'content' => $contentMd,
            'basePath' => $basePath,
        ]);
        $title = documentLabelFromPath($currentPath);
        break;
    case 'save':
        if (!$router->isPost()) {
            header('Location: ' . pageUrl($basePath));
            exit;
        }
        $path = $repo->normalizePath($_POST['path'] ?? '');
        $body = $_POST['content'] ?? '';
        $repo->writePage($path, $body);
        rebuildSearchIndex($indexer);
        header('Location: ' . pageUrl($basePath, $path));
        exit;
    case 'create':
        if (!$router->isPost()) {
            header('Location: ' . pageUrl($basePath));
            exit;
        }
        $dirPath = $repo->normalizePath((string) ($_POST['path'] ?? ''));
        $name = normalizeDocName((string) ($_POST['name'] ?? ''));
        if (
            $name === '' ||
            $name === '.' ||
            $name === '..' ||
            str_contains($name, '/')
        ) {
            header('Location: ' . pageUrl($basePath, $dirPath));
            exit;
        }
        if (strlen($dirPath) >= 3 && strcasecmp(substr($dirPath, -3), '.md') === 0) {
            $dirPath = parentDocPath($dirPath);
        }
        $targetPath = $dirPath === '' ? $name : ($dirPath . '/' . $name);
        $targetPath = $repo->normalizePath($targetPath);
        if ($targetPath === '') {
            header('Location: ' . pageUrl($basePath));
            exit;
        }
        if ($repo->resolveDocFile($targetPath) === null) {
            $repo->writePage($targetPath, '');
            rebuildSearchIndex($indexer);
        }
        header('Location: ' . editUrl($basePath, $targetPath));
        exit;
    case 'move':
        if (!$router->isPost()) {
            header('Location: ' . pageUrl($basePath));
            exit;
        }
        $from = $repo->normalizePath($_POST['from'] ?? '');
        $to = $repo->normalizePath((string) ($_POST['to'] ?? ''));
        if ($to === '') {
            $toDirPath = $repo->normalizePath((string) ($_POST['path'] ?? ''));
            $toName = normalizeDocName((string) ($_POST['name'] ?? ''));
            if (
                $toName === '' ||
                $toName === '.' ||
                $toName === '..' ||
                str_contains($toName, '/')
            ) {
                header('Location: ' . pageUrl($basePath, $from));
                exit;
            }
            if (strlen($toDirPath) >= 3 && strcasecmp(substr($toDirPath, -3), '.md') === 0) {
                $toDirPath = parentDocPath($toDirPath);
            }
            $to = $repo->normalizePath($toDirPath === '' ? $toName : ($toDirPath . '/' . $toName));
        }
        if ($to === '') {
            header('Location: ' . pageUrl($basePath, $from));
            exit;
        }
        if ($from === $to) {
            header('Location: ' . pageUrl($basePath, $to));
            exit;
        }
        if ($repo->resolveDocFile($from) === null && !$repo->isDirectoryPath($from)) {
            header('Location: ' . pageUrl($basePath, $from));
            exit;
        }
        $repo->movePage($from, $to);
        rebuildSearchIndex($indexer);
        header('Location: ' . pageUrl($basePath, $to));
        exit;
    case 'delete':
        if (!$router->isPost()) {
            header('Location: ' . pageUrl($basePath));
            exit;
        }
        $target = $repo->normalizePath((string) ($_POST['path'] ?? ''));
        if ($target === '') {
            header('Location: ' . pageUrl($basePath));
            exit;
        }
        $parent = parentDocPath($target);
        if ($repo->resolveDocFile($target) === null && !$repo->isDirectoryPath($target)) {
            header('Location: ' . pageUrl($basePath, $parent));
            exit;
        }
        $repo->deletePage($target);
        rebuildSearchIndex($indexer);
        header('Location: ' . pageUrl($basePath, $parent));
        exit;
    case 'view':
    default:
        $requestedPath = $repo->normalizePath($action['path'] ?? '');
        $file = $repo->resolveDocFile($requestedPath);
        if ($file === null && $repo->isDirectoryPath($requestedPath)) {
            $currentPath = $requestedPath;
            $breadcrumbs = buildBreadcrumbs($basePath, $currentPath);
            $items = $repo->listDirectory($currentPath);
            $moveFromPath = $currentPath === '' ? 'index' : $currentPath;
            $moveParts = splitDocPath($moveFromPath);
            $canMove = $currentPath !== '';
            $deletePath = $currentPath;
            $canDelete = $deletePath !== '';
            $deleteIsFolder = true;
            $content = render($baseDir . '/templates/folder.php', [
                'path' => $currentPath,
                'createPath' => $currentPath,
                'moveFromPath' => $moveFromPath,
                'movePath' => $moveParts['path'],
                'moveName' => $moveParts['name'],
                'canMove' => $canMove,
                'deletePath' => $deletePath,
                'deleteLabel' => $deletePath,
                'canDelete' => $canDelete,
                'deleteIsFolder' => $deleteIsFolder,
                'breadcrumbs' => $breadcrumbs,
                'items' => $items,
                'basePath' => $basePath,
            ]);
            $title = documentLabelFromPath($currentPath);
            break;
        }
        if ($file === null) {
            $currentPath = $requestedPath;
            $content = '<h1>Page not found</h1><p>Use edit to create it.</p>';
            $title = documentLabelFromPath($currentPath);
            break;
        }
        $renderPath = $repo->relativePath($file);
        $isDirectoryView = $repo->isDirectoryPath($requestedPath);
        $currentPath = $isDirectoryView ? $requestedPath : $renderPath;
        $html = $renderer->renderFile($renderPath, $file);
        $breadcrumbs = buildBreadcrumbs($basePath, $currentPath);
        $backlinks = $indexer->getBacklinks($renderPath);
        $createPath = $isDirectoryView ? $currentPath : parentDocPath($currentPath);
        $moveFromPath = $currentPath === '' ? $renderPath : $currentPath;
        $moveParts = splitDocPath($moveFromPath);
        $canMove = $currentPath !== '';
        $deletePath = $currentPath === '' ? $renderPath : $currentPath;
        $canDelete = $currentPath !== '' && $deletePath !== '';
        $deleteIsFolder = $isDirectoryView && $currentPath !== '';
        $content = render($baseDir . '/templates/view.php', [
            'title' => $currentPath === '' ? 'Home' : $currentPath,
            'path' => $currentPath,
            'createPath' => $createPath,
            'moveFromPath' => $moveFromPath,
            'movePath' => $moveParts['path'],
            'moveName' => $moveParts['name'],
            'canMove' => $canMove,
            'deletePath' => $deletePath,
            'deleteLabel' => $deletePath,
            'canDelete' => $canDelete,
            'deleteIsFolder' => $deleteIsFolder,
            'html' => $html,
            'breadcrumbs' => $breadcrumbs,
            'backlinks' => $backlinks,
            'basePath' => $basePath,
        ]);
        $title = documentLabelFromPath($currentPath);
        break;
}

$page = render($baseDir . '/templates/layout.php', [
    'title' => $title,
    'content' => $content,
    'tree' => $tree,
    'currentPath' => $currentPath,
    'basePath' => $basePath,
]);

echo $page;
