<?php
session_start();
require 'db.php';
require 'auth.php';
require_login();

// REDIRECT AMMINISTRATORI
if (is_admin()) {
    header('Location: admin_products.php');
    exit();
}

$pdo = connect();

// Gestione parametri filtro
$search = trim($_GET['q'] ?? '');
$category = $_GET['cat'] ?? '';
$sort = $_GET['sort'] ?? 'nome ASC';

// Whitelist sicurezza ordinamento
$allowedSorts = ['nome ASC', 'prezzo ASC', 'prezzo DESC', 'quantita ASC'];
if (!in_array($sort, $allowedSorts)) $sort = 'nome ASC';

// Query dinamica con FIX placeholder duplicati e ORDINAMENTO ID DESC
$sql = "SELECT * FROM prodotti WHERE 1=1";
$params = [];

if ($search !== '') {
    // FIX: Usiamo placeholder unici per evitare SQLSTATE[HY093]
    $sql .= " AND (nome LIKE :search_nome OR descrizione LIKE :search_desc)";
    $params[':search_nome'] = '%' . $search . '%';
    $params[':search_desc'] = '%' . $search . '%';
}
if ($category !== '') {
    $sql .= " AND categoria = :categoria";
    $params[':categoria'] = $category;
}

// MODIFICA: Ordinamento per ID decrescente (prodotti nuovi in cima)
$sql .= " ORDER BY " . $sort . ", id DESC"; 

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prodotti = $stmt->fetchAll();

// Risposta AJAX per filtri real-time (con FIX placeholder)
if (isset($_GET['ajax'])) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<tbody id="productsBody">';
    foreach ($prodotti as $p) {
        $hasStock = (int)$p['quantita'] > 0;
        echo "<tr>
            <td class='cell-muted'>#{$p['id']}</td>
            <td>" . htmlspecialchars($p['nome']) . "</td>
            <td><span class='tag'>" . htmlspecialchars($p['categoria']) . "</span></td>
            <td>{$p['quantita']}</td>
            <td>€" . number_format((float)$p['prezzo'], 2, ',', '.') . "</td>
            <td class='row-actions'>
                <a class='btn btn-sm btn-ghost' href='product.php?id={$p['id']}'>Dettagli</a>
                " . ($hasStock ? "
                <form action='cart.php' method='post' style='display:inline;'>
                    " . csrf_field() . "
                    <input type='hidden' name='action' value='add'>
                    <input type='hidden' name='id' value='{$p['id']}'>
                    <button class='btn btn-sm btn-primary'>+ Carrello</button>
                </form>" : "") . "
                <form action='wishlist.php' method='post' style='display:inline;'>
                    " . csrf_field() . "
                    <input type='hidden' name='action' value='toggle'>
                    <input type='hidden' name='id' value='{$p['id']}'>
                    <button class='btn btn-sm btn-ghost'>❤️</button>
                </form>
            </td>
        </tr>";
    }
    echo '</tbody>';
    echo '<div id="countChip" style="display:none;">' . count($prodotti) . '</div>';
    exit();
}

$categorie = $pdo->query("SELECT DISTINCT categoria FROM prodotti ORDER BY categoria ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>E-Commerce Beifiori</title>
<link rel="stylesheet" href="style.css">
<style>
    .toast-cart {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    padding: 14px 18px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 9999;
    animation: slideInToast 0.25s ease-out;
    }
    .toast-cart .btn {
    white-space: nowrap;
    }
    @keyframes slideInToast {
        from { transform: translateX(30px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    .toast-wishlist {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    padding: 14px 18px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 9999;
    animation: slideInToast 0.25s ease-out;
    }
    .toast-wishlist.removed {
    background: #f1f1f1;
    color: #555;
    border-color: #ddd;
    }
    .toast-wishlist .btn {
    white-space: nowrap;
    }
</style>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="logo-mark">📦</span>
            <span>Beifiori Shop</span>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="active"><span class="nav-icon">🛒</span> Acquista Prodotti</a>
            <a href="cart.php"><span class="nav-icon">🧺</span> Carrello</a>
            <a href="orders.php"><span class="nav-icon">📦</span> Ordini</a>
            <a href="wishlist.php"><span class="nav-icon">❤️</span> Preferiti</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php"><span class="nav-icon">🚪</span> Logout</a>
        </div>
    </aside>
    <div class="main-col">
        <header class="topbar">
            <div class="topbar-search"></div>
            <div class="topbar-actions">
                <span class="welcome">Ciao, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span>
                <span class="user-chip"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></span>
            </div>
        </header>
        <main class="page">
    <?php if ($msg = flash_get('success')): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($msg = flash_get('error')): ?><div class="alert alert-error"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <section class="panel panel-wide">
        <div class="panel-header">
            <h2>Prodotti in magazzino <span class="count-chip" id="countChip"><?= count($prodotti) ?></span></h2>
            
            <!-- BARRA FILTRI UNIFICATA -->
            <form class="filter-bar" action="index.php" method="get" id="filterForm">
                <input type="text" name="q" id="searchInput" placeholder="Cerca prodotto..." 
                       value="<?= htmlspecialchars($search) ?>">
                <select name="cat" id="categoryFilter">
                    <option value="">Tutte le categorie</option>
                    <?php foreach ($categorie as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= ($category === $c) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="sort" id="sortFilter">
                    <option value="nome ASC" <?= ($sort === 'nome ASC') ? 'selected' : '' ?>>Nome A-Z</option>
                    <option value="prezzo ASC" <?= ($sort === 'prezzo ASC') ? 'selected' : '' ?>>Prezzo ↑</option>
                    <option value="prezzo DESC" <?= ($sort === 'prezzo DESC') ? 'selected' : '' ?>>Prezzo ↓</option>
                    <option value="quantita ASC" <?= ($sort === 'quantita ASC') ? 'selected' : '' ?>>Disponibilità ↑</option>
                </select>
                
                <button type="submit" class="btn btn-primary">Filtra</button>
                
                <?php if ($search !== '' || $category !== '' || $sort !== 'nome ASC'): ?>
                    <a class="btn btn-ghost" href="index.php">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Nome</th><th>Categoria</th><th>Qtà</th><th>Prezzo</th><th>Azioni</th></tr></thead>
                <tbody id="productsBody">
                    <?php foreach ($prodotti as $p): 
                        $hasStock = (int)$p['quantita'] > 0;
                    ?>
                    <tr>
                        <td class="cell-muted">#<?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['nome']) ?></td>
                        <td><span class="tag"><?= htmlspecialchars($p['categoria']) ?></span></td>
                        <td><?= $p['quantita'] ?></td>
                        <td>€<?= number_format((float)$p['prezzo'], 2, ',', '.') ?></td>
                        <td class="row-actions">
                            <a class="btn btn-sm btn-ghost" href="product.php?id=<?= $p['id'] ?>">Dettagli</a>
                            <?php if ($hasStock): ?>
                                <form action="cart.php" method="post" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-sm btn-primary">+ Carrello</button>
                                </form>
                            <?php endif; ?>
                            <form action="wishlist.php" method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button class="btn btn-sm btn-ghost">❤️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($prodotti)): ?><tr><td colspan="6" class="cell-empty">Nessun prodotto trovato.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
        </main>
    </div>
</div>

<script>
let debounceTimer;
const form = document.getElementById('filterForm');
const searchInput = document.getElementById('searchInput');
const categoryFilter = document.getElementById('categoryFilter');
const sortFilter = document.getElementById('sortFilter');

const fetchProductsRealtime = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const params = new URLSearchParams({
            q: searchInput.value,
            cat: categoryFilter.value,
            sort: sortFilter.value,
            ajax: '1'
        });

        fetch(`index.php?${params}`)
            .then(r => r.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newBody = doc.getElementById('productsBody');
                const newCount = doc.getElementById('countChip');

                if (newBody) document.getElementById('productsBody').innerHTML = newBody.innerHTML;
                if (newCount) document.getElementById('countChip').textContent = newCount.textContent;

                attachCartHandlers();
                attachWishlistHandlers();
            })
            .catch(err => console.error('Errore fetch:', err));
    }, 300);
};

[searchInput, categoryFilter, sortFilter].forEach(el => {
    el.addEventListener('input', fetchProductsRealtime);
});

[categoryFilter, sortFilter].forEach(el => {
    el.addEventListener('change', (e) => {
        e.preventDefault();
        fetchProductsRealtime();
    });
});

// ==== GESTIONE "+ CARRELLO" SENZA REDIRECT ====

function showCartToast() {
    const existing = document.querySelector('.toast-cart');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'toast-cart';
    toast.innerHTML = `
        <span>✅ Articolo aggiunto al carrello</span>
        <a href="cart.php" class="btn btn-sm btn-primary">Vai al carrello</a>
    `;
    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 4000);
}

function attachCartHandlers() {
    document.querySelectorAll('form[action="cart.php"]').forEach(cartForm => {
        if (cartForm.dataset.bound === '1') return;
        cartForm.dataset.bound = '1';

        cartForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(cartForm);

            fetch('cart.php', { method: 'POST', body: formData })
                .then(r => {
                    if (r.ok) {
                        showCartToast();
                    } else {
                        console.error('Errore aggiunta al carrello, status:', r.status);
                    }
                })
                .catch(err => console.error('Errore fetch carrello:', err));
        });
    });
}

// ==== GESTIONE "❤️ PREFERITI" SENZA REDIRECT ====

function showWishlistToast(wasAdded) {
    const existing = document.querySelector('.toast-wishlist');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'toast-wishlist' + (wasAdded ? '' : ' removed');
    toast.innerHTML = wasAdded
        ? `<span>❤️ Aggiunto ai preferiti</span>
           <a href="wishlist.php" class="btn btn-sm btn-primary">Vai ai preferiti</a>`
        : `<span>🤍 Rimosso dai preferiti</span>
           <a href="wishlist.php" class="btn btn-sm btn-ghost">Vai ai preferiti</a>`;
    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 4000);
}

function attachWishlistHandlers() {
    document.querySelectorAll('form[action="wishlist.php"]').forEach(wishForm => {
        if (wishForm.dataset.bound === '1') return;
        wishForm.dataset.bound = '1';

        wishForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = wishForm.querySelector('button');
            const formData = new FormData(wishForm);

            // Stato attuale prima del toggle: se il bottone ha classe "active" era già nei preferiti
            const wasActive = btn.classList.contains('wishlist-active');

            fetch('wishlist.php', { method: 'POST', body: formData })
                .then(r => {
                    if (r.ok) {
                        const nowAdded = !wasActive;
                        btn.classList.toggle('wishlist-active', nowAdded);
                        showWishlistToast(nowAdded);
                    } else {
                        console.error('Errore aggiornamento preferiti, status:', r.status);
                    }
                })
                .catch(err => console.error('Errore fetch preferiti:', err));
        });
    });
}

// Attiva al caricamento iniziale
attachCartHandlers();
attachWishlistHandlers();
</script>
</body>
</html>