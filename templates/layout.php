<?php
/** @var string $title */
/** @var string $content */
/** @var array $tree */
/** @var string $currentPath */
/** @var string $basePath */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MarkBase - <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/img-internal/MarkBase.png">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/style.css">
</head>
<body>
<div class="app">
    <header class="header">
        <div class="brand">
            <a href="<?= htmlspecialchars(pageUrl($basePath), ENT_QUOTES, 'UTF-8') ?>">
                <img class="brand-logo" src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/img-internal/MarkBase.png" alt="MarkBase logo">
                <span class="brand-mark">Mark</span><span class="brand-base">Base</span>
            </a>
        </div>
        <form class="search" action="<?= htmlspecialchars(scriptUrl($basePath, 'search.php'), ENT_QUOTES, 'UTF-8') ?>" method="get">
            <div class="input-icon-group">
                <input type="search" name="q" placeholder="Search..." value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-label="Search">
                <button type="submit" class="icon-button" aria-label="Search">
                    <img src="<?= htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') ?>/img-internal/icons8-search.svg" alt="">
                </button>
            </div>
        </form>
    </header>
    <div class="main">
        <aside class="sidebar">
            <?php include __DIR__ . '/sidebar.php'; ?>
        </aside>
        <main class="content">
            <?= $content ?>
        </main>
    </div>
    <footer class="app-footer">Icons by <a target="_blank" href="https://icons8.com">Icons8</a></footer>
</div>
<div class="modal-backdrop" id="create-page-modal" hidden>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="create-page-title">
        <h2 id="create-page-title">Create New Document</h2>
        <form method="post" action="<?= htmlspecialchars(scriptUrl($basePath, 'create.php'), ENT_QUOTES, 'UTF-8') ?>" class="create-form">
            <label for="create-page-path">Path</label>
            <input id="create-page-path" type="text" name="path" placeholder="Optional folder path">
            <label for="create-page-name">Name</label>
            <input id="create-page-name" type="text" name="name" placeholder="Document name" required>
            <div class="modal-actions">
                <button id="create-page-cancel" type="button">Cancel</button>
                <button type="submit" class="primary-action">Create</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-backdrop" id="move-page-modal" hidden>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="move-page-title">
        <h2 id="move-page-title">Move or Rename Document</h2>
        <form method="post" action="<?= htmlspecialchars(scriptUrl($basePath, 'move.php'), ENT_QUOTES, 'UTF-8') ?>" class="move-form">
            <input id="move-page-from" type="hidden" name="from" value="">
            <label for="move-page-path">Path</label>
            <input id="move-page-path" type="text" name="path" placeholder="Optional folder path">
            <label for="move-page-name">Name</label>
            <input id="move-page-name" type="text" name="name" placeholder="Document name" required>
            <div class="modal-actions">
                <button id="move-page-cancel" type="button">Cancel</button>
                <button type="submit" class="primary-action">Move</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-backdrop" id="delete-page-modal" hidden>
    <div class="modal-panel" id="delete-page-panel" role="dialog" aria-modal="true" aria-labelledby="delete-page-title">
        <h2 id="delete-page-title">Delete Document</h2>
        <form method="post" action="<?= htmlspecialchars(scriptUrl($basePath, 'delete.php'), ENT_QUOTES, 'UTF-8') ?>" class="delete-form">
            <p class="modal-message"><code id="delete-page-label"></code> will be deleted.</p>
            <input id="delete-page-path" type="hidden" name="path" value="">
            <div class="modal-actions">
                <button id="delete-page-cancel" type="button">Cancel</button>
                <button type="submit" class="danger-action">Confirm</button>
            </div>
        </form>
    </div>
</div>
<script>
const filterInput = document.getElementById('tree-filter');
const filterReset = document.getElementById('tree-reset');
const treeContainer = document.getElementById('tree');
const treeEmpty = document.getElementById('tree-empty');
const createModal = document.getElementById('create-page-modal');
const createPathInput = document.getElementById('create-page-path');
const createNameInput = document.getElementById('create-page-name');
const createCancel = document.getElementById('create-page-cancel');
const createTriggers = document.querySelectorAll('.js-new-page');
const moveModal = document.getElementById('move-page-modal');
const moveFromInput = document.getElementById('move-page-from');
const movePathInput = document.getElementById('move-page-path');
const moveNameInput = document.getElementById('move-page-name');
const moveCancel = document.getElementById('move-page-cancel');
const moveTriggers = document.querySelectorAll('.js-move-page');
const deleteModal = document.getElementById('delete-page-modal');
const deletePanel = document.getElementById('delete-page-panel');
const deletePathInput = document.getElementById('delete-page-path');
const deleteLabel = document.getElementById('delete-page-label');
const deleteCancel = document.getElementById('delete-page-cancel');
const deleteTriggers = document.querySelectorAll('.js-delete-page');
const basePath = <?= json_encode($basePath) ?>;
const viewUrl = <?= json_encode(scriptUrl($basePath, 'view.php')) ?>;
const filterUrl = <?= json_encode(scriptUrl($basePath, 'filter.php')) ?>;
const originalTree = treeContainer ? treeContainer.innerHTML : '';
const treeStateKey = 'wiki:open-dirs:' + basePath;

function loadOpenDirPaths() {
    try {
        const raw = localStorage.getItem(treeStateKey);
        if (!raw) {
            return new Set();
        }
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) {
            return new Set();
        }
        return new Set(parsed.filter(v => typeof v === 'string'));
    } catch (_e) {
        return new Set();
    }
}

function saveOpenDirPaths(paths) {
    try {
        localStorage.setItem(treeStateKey, JSON.stringify(Array.from(paths)));
    } catch (_e) {
    }
}

let openDirPaths = loadOpenDirPaths();

function bindTreeToggleHandlers() {
    document.querySelectorAll('.tree details[data-dir-path]').forEach(detail => {
        if (detail.dataset.toggleBound === '1') {
            return;
        }
        detail.dataset.toggleBound = '1';
        detail.addEventListener('toggle', () => {
            const path = detail.dataset.dirPath;
            if (!path) {
                return;
            }
            if (detail.open) {
                openDirPaths.add(path);
            } else {
                openDirPaths.delete(path);
            }
            saveOpenDirPaths(openDirPaths);
        });
    });
}

function initializeTreeState() {
    if (!treeContainer) {
        return;
    }

    const details = Array.from(document.querySelectorAll('.tree details[data-dir-path]'));
    const availablePaths = new Set(details.map(detail => detail.dataset.dirPath).filter(Boolean));
    openDirPaths.forEach(path => {
        if (!availablePaths.has(path)) {
            openDirPaths.delete(path);
        }
    });

    details.forEach(detail => {
        const path = detail.dataset.dirPath;
        if (path && openDirPaths.has(path)) {
            detail.open = true;
        }
        if (detail.open && path) {
            openDirPaths.add(path);
        }
    });

    saveOpenDirPaths(openDirPaths);
    bindTreeToggleHandlers();
}

function renderFiltered(list) {
    treeContainer.innerHTML = '';
    if (list.length === 0) {
        treeEmpty.style.display = 'block';
        return;
    }
    treeEmpty.style.display = 'none';
    list.forEach(path => {
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.href = viewUrl + '?path=' + encodeURIComponent(path);
        a.textContent = path;
        li.appendChild(a);
        treeContainer.appendChild(li);
    });
}

async function runFilter(value) {
    const query = value.trim();
    if (query === '') {
        if (treeContainer) {
            treeContainer.innerHTML = originalTree;
            treeContainer.classList.remove('flat');
            initializeTreeState();
        }
        treeEmpty.style.display = 'none';
        return;
    }
    const res = await fetch(filterUrl + '?q=' + encodeURIComponent(query));
    if (!res.ok) {
        return;
    }
    const data = await res.json();
    treeContainer.classList.add('flat');
    renderFiltered(data.paths || []);
}

if (filterInput) {
    filterInput.addEventListener('input', () => runFilter(filterInput.value));
}
if (filterReset) {
    filterReset.addEventListener('click', () => {
        filterInput.value = '';
        runFilter('');
    });
}

function closeCreateModal() {
    if (!createModal) {
        return;
    }
    createModal.hidden = true;
}

function closeMoveModal() {
    if (!moveModal) {
        return;
    }
    moveModal.hidden = true;
}

function closeDeleteModal() {
    if (!deleteModal) {
        return;
    }
    if (deletePanel) {
        deletePanel.classList.remove('folder-warning');
    }
    deleteModal.hidden = true;
}

function openCreateModal(defaultPath) {
    if (!createModal || !createPathInput || !createNameInput) {
        return;
    }
    closeMoveModal();
    closeDeleteModal();
    createPathInput.value = defaultPath || '';
    createNameInput.value = '';
    createModal.hidden = false;
    createNameInput.focus();
}

function openMoveModal(fromPath, defaultPath, defaultName) {
    if (!moveModal || !moveFromInput || !movePathInput || !moveNameInput) {
        return;
    }
    closeCreateModal();
    closeDeleteModal();
    moveFromInput.value = fromPath || '';
    movePathInput.value = defaultPath || '';
    moveNameInput.value = defaultName || '';
    moveModal.hidden = false;
    moveNameInput.focus();
}

function openDeleteModal(path, label, isFolder) {
    if (!deleteModal || !deletePathInput || !deleteLabel) {
        return;
    }
    closeCreateModal();
    closeMoveModal();
    deletePathInput.value = path || '';
    deleteLabel.textContent = label || path || '';
    if (deletePanel) {
        deletePanel.classList.toggle('folder-warning', isFolder === true);
    }
    deleteModal.hidden = false;
}

createTriggers.forEach(link => {
    link.addEventListener('click', event => {
        event.preventDefault();
        openCreateModal(link.dataset.defaultPath || '');
    });
});

moveTriggers.forEach(link => {
    link.addEventListener('click', event => {
        event.preventDefault();
        openMoveModal(
            link.dataset.fromPath || '',
            link.dataset.defaultPath || '',
            link.dataset.defaultName || ''
        );
    });
});

deleteTriggers.forEach(link => {
    link.addEventListener('click', event => {
        event.preventDefault();
        openDeleteModal(
            link.dataset.deletePath || '',
            link.dataset.deleteLabel || '',
            link.dataset.deleteFolder === '1'
        );
    });
});

if (createCancel) {
    createCancel.addEventListener('click', closeCreateModal);
}
if (moveCancel) {
    moveCancel.addEventListener('click', closeMoveModal);
}
if (deleteCancel) {
    deleteCancel.addEventListener('click', closeDeleteModal);
}

if (createModal) {
    createModal.addEventListener('click', event => {
        if (event.target === createModal) {
            closeCreateModal();
        }
    });
}

if (moveModal) {
    moveModal.addEventListener('click', event => {
        if (event.target === moveModal) {
            closeMoveModal();
        }
    });
}

if (deleteModal) {
    deleteModal.addEventListener('click', event => {
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
    });
}

document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        closeCreateModal();
        closeMoveModal();
        closeDeleteModal();
    }
});

initializeTreeState();
</script>
</body>
</html>
