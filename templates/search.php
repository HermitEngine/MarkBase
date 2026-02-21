<?php
/** @var string $query */
/** @var array $results */
/** @var string $basePath */
?>
<h1>Search results</h1>
<p class="muted">Query: <?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?></p>
<?php if (empty($results)): ?>
    <p>No results found.</p>
<?php else: ?>
    <ul class="search-results">
        <?php foreach ($results as $result): ?>
            <li>
                <a href="<?= htmlspecialchars(pageUrl($basePath, $result['path']), ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($result['title'], ENT_QUOTES, 'UTF-8') ?>
                </a>
                <div class="snippet"><?= $result['snippet'] ?></div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
