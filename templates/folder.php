<?php
/** @var string $path */
/** @var string $createPath */
/** @var string $moveFromPath */
/** @var string $movePath */
/** @var string $moveName */
/** @var bool $canMove */
/** @var string $deletePath */
/** @var string $deleteLabel */
/** @var bool $canDelete */
/** @var bool $deleteIsFolder */
/** @var array $breadcrumbs */
/** @var array $items */
/** @var string $basePath */
?>
<div class="breadcrumbs-row">
    <div class="breadcrumbs">
        <?php $lastIndex = count($breadcrumbs) - 1; ?>
        <?php foreach ($breadcrumbs as $index => $crumb): ?>
            <?php if ($index > 0): ?>
                <span class="sep">/</span>
            <?php endif; ?>
            <?php if ($index === $lastIndex): ?>
                <span class="current"><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
                <a href="<?= htmlspecialchars($crumb['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <div class="breadcrumb-actions">
        <a class="icon-link upload-link js-upload-page" href="<?= htmlspecialchars(scriptUrl($basePath, 'upload.php'), ENT_QUOTES, 'UTF-8') ?>" data-default-path="<?= htmlspecialchars($createPath, ENT_QUOTES, 'UTF-8') ?>" aria-label="Upload markdown files" title="Upload">
            <img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/img-internal/icons8-upload-to-the-cloud.svg" alt="">
        </a>
        <a class="icon-link new-link js-new-page" href="<?= htmlspecialchars(scriptUrl($basePath, 'create.php'), ENT_QUOTES, 'UTF-8') ?>" data-default-path="<?= htmlspecialchars($createPath, ENT_QUOTES, 'UTF-8') ?>" aria-label="New page" title="New">
            <img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/img-internal/icons8-plus-100.svg" alt="">
        </a>
        <a class="icon-link edit-link" href="<?= htmlspecialchars(editUrl($basePath, $path), ENT_QUOTES, 'UTF-8') ?>" aria-label="Edit page" title="Edit">
            <img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/img-internal/icons8-edit-pencil-100.svg" alt="">
        </a>
        <?php if ($canMove): ?>
            <a class="icon-link move-link js-move-page" href="<?= htmlspecialchars(scriptUrl($basePath, 'move.php'), ENT_QUOTES, 'UTF-8') ?>" data-from-path="<?= htmlspecialchars($moveFromPath, ENT_QUOTES, 'UTF-8') ?>" data-default-path="<?= htmlspecialchars($movePath, ENT_QUOTES, 'UTF-8') ?>" data-default-name="<?= htmlspecialchars($moveName, ENT_QUOTES, 'UTF-8') ?>" aria-label="Move or rename page" title="Move / Rename">
                <img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/img-internal/icons8-share.svg" alt="">
            </a>
        <?php endif; ?>
        <?php if ($canDelete): ?>
            <a class="icon-link delete-link js-delete-page" href="<?= htmlspecialchars(scriptUrl($basePath, 'delete.php'), ENT_QUOTES, 'UTF-8') ?>" data-delete-path="<?= htmlspecialchars($deletePath, ENT_QUOTES, 'UTF-8') ?>" data-delete-label="<?= htmlspecialchars($deleteLabel, ENT_QUOTES, 'UTF-8') ?>" data-delete-folder="<?= $deleteIsFolder ? '1' : '0' ?>" aria-label="Delete page" title="Delete">
                <img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/img-internal/icons8-delete-100.svg" alt="">
            </a>
        <?php endif; ?>
    </div>
</div>

<article class="page">
    <h1><?= htmlspecialchars($path === '' ? 'Home' : $path, ENT_QUOTES, 'UTF-8') ?></h1>

    <?php
    $dirs = [];
    $files = [];
    foreach ($items as $item) {
        if (!is_array($item) || !isset($item['type'], $item['name'], $item['path'])) {
            continue;
        }
        if ($item['type'] === 'dir') {
            $dirs[] = $item;
            continue;
        }
        if ($item['type'] === 'file') {
            $files[] = $item;
        }
    }
    ?>

    <?php if ($dirs === [] && $files === []): ?>
        <p>This folder is empty.</p>
    <?php else: ?>
        <?php if ($dirs !== []): ?>
            <h2>Folders</h2>
            <ul>
                <?php foreach ($dirs as $item): ?>
                    <li>
                        <a href="<?= htmlspecialchars(pageUrl($basePath, (string) $item['path']), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) $item['name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($files !== []): ?>
            <h2>Pages</h2>
            <ul>
                <?php foreach ($files as $item): ?>
                    <li>
                        <a href="<?= htmlspecialchars(pageUrl($basePath, (string) $item['path']), ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) $item['name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</article>
