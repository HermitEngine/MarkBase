<?php
/** @var string $path */
/** @var string $content */
/** @var string $basePath */
?>
<h1>Edit <?= htmlspecialchars($path === '' ? 'Home' : $path, ENT_QUOTES, 'UTF-8') ?></h1>
<form method="post" action="<?= htmlspecialchars(scriptUrl($basePath, 'save.php'), ENT_QUOTES, 'UTF-8') ?>" class="editor">
    <input type="hidden" name="path" value="<?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?>">
    <textarea name="content" rows="24"><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></textarea>
    <div class="editor-actions">
        <button type="submit">Save</button>
        <a class="button" href="<?= htmlspecialchars(pageUrl($basePath, $path), ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
    </div>
</form>
