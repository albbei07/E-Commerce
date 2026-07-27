<?php
session_start();
require 'db.php';
require 'auth.php';
require_login();
$pdo = connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle' && $id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM preferiti WHERE utente_id = ? AND prodotto_id = ?");
        $stmt->execute([$_SESSION['user_id'], $id]);
        if ($stmt->fetch()) {
            $pdo->prepare("DELETE FROM preferiti WHERE utente_id = ? AND prodotto_id = ?")
                ->execute([$_SESSION['user_id'], $id]);
            flash_set('success', 'Rimosso dai preferiti.');
        } else {
            $pdo->prepare("INSERT INTO preferiti (utente_id, prodotto_id) VALUES (?, ?)")
                ->execute([$_SESSION['user_id'], $id]);
            // FIX: Usata la funzione corretta flash_set() invece di flash_set1()
            flash_set('success', 'Aggiunto ai preferiti.'); 
        }
    } elseif ($action === 'remove' && $id > 0) {
        $pdo->prepare("DELETE FROM preferiti WHERE utente_id = ? AND prodotto_id = ?")
            ->execute([$_SESSION['user_id'], $id]);
        flash_set('success', 'Rimosso dai preferiti.');
    }
    header('Location: wishlist.php');
    exit();
}

$stmt = $pdo->prepare("
    SELECT p.*, f.data AS aggiunto_il
    FROM preferiti f
    JOIN prodotti p ON f.prodotto_id = p.id
    WHERE f.utente_id = ?
    ORDER BY f.data DESC
");
$stmt->execute([$_SESSION['user_id']]);
$preferiti = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Preferiti · E-Commerce Beifiori</title>
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
            <a href="index.php"><span class="nav-icon">🛒</span> Acquista Prodotti</a>
            <a href="cart.php"><span class="nav-icon">🧺</span> Carrello</a>
            <a href="orders.php"><span class="nav-icon">📦</span> Ordini</a>
            <a href="wishlist.php" class="active"><span class="nav-icon">❤️</span> Preferiti</a>
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
    <!-- MODIFICA: Aggiunto stile flex e bottone "Torna ai prodotti" -->
    <?php if ($msg = flash_get('success')): ?>
        <div class="alert alert-success" style="display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 16px;">
            <span style="font-weight: 500;"><?= htmlspecialchars($msg) ?></span>
            <a href="index.php" class="btn btn-sm btn-primary">📦 Torna ai prodotti</a>
        </div>
    <?php endif; ?>

    <section class="panel panel-wide">
        <div class="panel-header">
            <h2>❤️ I tuoi Preferiti <span class="count-chip"><?= count($preferiti) ?></span></h2>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Prodotto</th><th>Categoria</th><th>Prezzo</th><th>Disponibilità</th><th>Aggiunto il</th><th>Azioni</th></tr></thead>
                <tbody>
                <?php foreach ($preferiti as $p): ?>
                    <tr>
                        <td>
                            <a href="product.php?id=<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></a>
                            <div class="cell-muted" style="font-size:12px;"><?= htmlspecialchars(mb_strimwidth($p['descrizione'], 0, 60, '...')) ?></div>
                        </td>
                        <td><span class="tag"><?= htmlspecialchars($p['categoria']) ?></span></td>
                        <td>€<?= number_format($p['prezzo'], 2, ',', '.') ?></td>
                        <td><?= (int) $p['quantita'] ?> pz</td>
                        <td class="cell-muted"><?= date('d/m/Y', strtotime($p['aggiunto_il'])) ?></td>
                        <td class="row-actions">
                            <?php if ((int) $p['quantita'] > 0): ?>
                                <form action="cart.php" method="post" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button class="btn btn-sm btn-primary">+ Carrello</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button class="btn btn-sm btn-danger">Rimuovi</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($preferiti)): ?>
                    <tr><td colspan="6" class="cell-empty">Nessun preferito. <a href="index.php">Esplora i prodotti</a>.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
        </main>
    </div>
</div>
<script>
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

document.querySelectorAll('form[action="cart.php"]').forEach(cartForm => {
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
</script>
</body>
</html>