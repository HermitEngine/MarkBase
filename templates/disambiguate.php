<?php
/** @var string $name */
/** @var array $matches */
/** @var string $basePath */
?>
<h1>Disambiguation: <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h1>
<p>Multiple pages match this name:</p>
<ul>
    <?php foreach ($matches as $match): ?>
        <li><a href="<?= htmlspecialchars(pageUrl($basePath, $match), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($match, ENT_QUOTES, 'UTF-8') ?></a></li>
    <?php endforeach; ?>
</ul>
