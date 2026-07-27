<?php
session_start();
require 'db.php';
require 'auth.php';
require_login();
$pdo = connect();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit(); }

$stmt = $pdo->prepare("SELECT * FROM prodotti WHERE id = ?");
$stmt->execute([$id]);
$prodotto = $stmt->fetch();
if (!$prodotto) { header('Location: index.php'); exit(); }

// Gestione recensione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'review') {
        $voto = (int) ($_POST['voto'] ?? 0);
        $commento = trim($_POST['commento'] ?? '');
        if ($voto < 1 || $voto > 5) {
            flash_set('error', 'Il voto deve essere tra 1 e 5.');
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM recensioni WHERE utente_id = ? AND prodotto_id = ?");
                $stmt->execute([$_SESSION['user_id'], $id]);
                if ($stmt->fetch()) {
                    $pdo->prepare("UPDATE recensioni SET voto = ?, commento = ?, data = NOW() WHERE utente_id = ? AND prodotto_id = ?")
                        ->execute([$voto, $commento, $_SESSION['user_id'], $id]);
                    flash_set('success', 'Recensione aggiornata.');
                } else {
                    $pdo->prepare("INSERT INTO recensioni (utente_id, prodotto_id, voto, commento) VALUES (?, ?, ?, ?)")
                        ->execute([$_SESSION['user_id'], $id, $voto, $commento]);
                    flash_set('success', 'Recensione pubblicata.');
                }
            } catch (PDOException $e) {
                flash_set('error', 'Errore nel salvataggio della recensione.');
            }
        }
        header("Location: product.php?id=$id");
        exit();
    }
}

// Recupera recensioni
$stmt = $pdo->prepare("
    SELECT r.*, u.nome, u.cognome
    FROM recensioni r
    JOIN utenti u ON r.utente_id = u.id
    WHERE r.prodotto_id = ?
    ORDER BY r.data DESC
");
$stmt->execute([$id]);
$recensioni = $stmt->fetchAll();

// Media voto
$avg = $pdo->prepare("SELECT AVG(voto) as media, COUNT(*) as tot FROM recensioni WHERE prodotto_id = ?");
$avg->execute([$id]);
$stats = $avg->fetch();

// La mia recensione (se esiste)
$my = $pdo->prepare("SELECT * FROM recensioni WHERE utente_id = ? AND prodotto_id = ?");
$my->execute([$_SESSION['user_id'], $id]);
$my_review = $my->fetch();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($prodotto['nome']) ?> · E-Commerce Beifiori</title>
<link rel="stylesheet" href="style.css">
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
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
            <div>
                <h2><?= htmlspecialchars($prodotto['nome']) ?></h2>
                <p style="margin: 8px 0;"><span class="tag"><?= htmlspecialchars($prodotto['categoria']) ?></span></p>
                <p style="margin-top: 16px; line-height: 1.6;"><?= nl2br(htmlspecialchars($prodotto['descrizione'])) ?></p>
            </div>
            <div style="background: var(--primary-soft); padding: 20px; border-radius: var(--radius); text-align: center;">
                <div style="font-size: 28px; font-weight: 700; color: var(--primary);">€<?= number_format($prodotto['prezzo'], 2, ',', '.') ?></div>
                <div style="margin: 8px 0;"><?= (int) $prodotto['quantita'] ?> disponibili</div>
                <?php if ($stats['tot'] > 0): ?>
                    <div style="font-size: 14px; color: var(--text-muted);">⭐ <?= number_format($stats['media'], 1) ?>/5 (<?= $stats['tot'] ?> recensioni)</div>
                <?php endif; ?>
                <?php if ((int) $prodotto['quantita'] > 0): ?>
                    <form action="cart.php" method="post" style="margin-top: 12px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="return" value="product.php?id=<?= $id ?>">
                        <button class="btn btn-primary btn-block">+ Aggiungi al carrello</button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-ghost btn-block" disabled>Esaurito</button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="panel panel-wide">
        <h2>⭐ Recensioni (<?= count($recensioni) ?>)</h2>

        <form method="post" style="margin-bottom: 24px; padding: 16px; background: var(--bg); border-radius: var(--radius-sm);">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="review">
            <div class="field-row">
                <div class="field">
                    <label for="voto">Il tuo voto</label>
                    <select id="voto" name="voto" required>
                        <option value="">Seleziona...</option>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>" <?= ($my_review && $my_review['voto'] == $i) ? 'selected' : '' ?>><?= $i ?> stelle</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="field" style="flex: 2;">
                    <label for="commento">Commento</label>
                    <textarea id="commento" name="commento" rows="2"><?= htmlspecialchars($my_review['commento'] ?? '') ?></textarea>
                </div>
            </div>
            <button class="btn btn-primary"><?= $my_review ? 'Aggiorna recensione' : 'Pubblica recensione' ?></button>
        </form>

        <?php if (empty($recensioni)): ?>
            <p class="cell-empty">Nessuna recensione ancora. Sii il primo!</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($recensioni as $r): ?>
                    <div style="padding: 14px; border: 1px solid var(--border); border-radius: var(--radius-sm);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                            <strong><?= htmlspecialchars($r['nome'] . ' ' . $r['cognome']) ?></strong>
                            <span class="cell-muted" style="font-size: 12px;"><?= date('d/m/Y', strtotime($r['data'])) ?></span>
                        </div>
                        <div style="color: var(--warning);">
                            <?php for ($i = 0; $i < $r['voto']; $i++): ?>⭐<?php endfor; ?>
                        </div>
                        <?php if ($r['commento']): ?>
                            <p style="margin: 8px 0 0;"><?= nl2br(htmlspecialchars($r['commento'])) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
        </main>
    </div>
</div>
</body>
</html>