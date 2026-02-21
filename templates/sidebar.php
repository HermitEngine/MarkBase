<?php
/** @var array $tree */
/** @var string $currentPath */
/** @var string $basePath */

$renderTree = function (
    array $items,
    string $currentPath,
    int $depth = 0,
    string $parentPath = ''
) use (&$renderTree, $basePath) {
    if ($depth === 0) {
        echo '<ul class="tree" id="tree">';
    } else {
        echo '<ul class="tree">';
    }
    foreach ($items as $item) {
        if ($item['type'] === 'dir') {
            $dirPath = $parentPath === '' ? $item['name'] : $parentPath . '/' . $item['name'];
            $isActiveBranch = $currentPath === $dirPath || str_starts_with($currentPath, $dirPath . '/');
            $open = ($depth === 0 || $isActiveBranch) ? ' open' : '';
            echo '<li class="dir">';
            echo '<details class="' . ($depth === 0 ? 'root' : '') . '" data-dir-path="' . htmlspecialchars($dirPath, ENT_QUOTES, 'UTF-8') . '"' . $open . '>';
            echo '<summary>' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . '</summary>';
            $renderTree($item['children'], $currentPath, $depth + 1, $dirPath);
            echo '</details>';
            echo '</li>';
        } else {
            $path = $item['path'];
            $label = str_ends_with($item['name'], '.md') ? substr($item['name'], 0, -3) : $item['name'];
            $active = $path === $currentPath ? ' active' : '';
            echo '<li class="file' . $active . '">';
            echo '<a href="' . htmlspecialchars(pageUrl($basePath, $path), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
            echo '</li>';
        }
    }
    echo '</ul>';
};
?>
<div class="sidebar-header">
    <div class="input-icon-group">
        <input id="tree-filter" type="search" placeholder="Filter pages" autocomplete="off" aria-label="Filter pages">
        <button id="tree-reset" type="button" class="icon-button" aria-label="Reset filter">
            <img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/img-internal/icons8-close-100.svg" alt="">
        </button>
    </div>
</div>
<div class="sidebar-tree">
    <?php $renderTree($tree, $currentPath); ?>
    <div id="tree-empty" class="tree-empty" style="display: none;">No matches</div>
</div>
