<?php
session_start();
require 'db.php';
require 'auth.php';
require_login();
$pdo = connect();

$stmt = $pdo->prepare("
    SELECT o.id, o.data, o.totale, o.stato,
           GROUP_CONCAT(CONCAT(p.nome, ' (x', od.quantita, ')') SEPARATOR ', ') AS prodotti
    FROM ordini o
    JOIN ordine_dettagli od ON o.id = od.ordine_id
    JOIN prodotti p ON od.prodotto_id = p.id
    WHERE o.utente_id = ?
    GROUP BY o.id
    ORDER BY o.data DESC
");
$stmt->execute([$_SESSION['user_id']]);
$ordini = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>I miei Ordini · E-Commerce Beifiori</title>
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
            <a href="index.php"><span class="nav-icon">🛒</span> Acquista Prodotti</a>
            <a href="cart.php"><span class="nav-icon">🧺</span> Carrello</a>
            <a href="orders.php" class="active"><span class="nav-icon">📦</span> Ordini</a>
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

    <?php
    $ordCompletati = 0; $ordAttesa = 0; $speseTotali = 0.0;
    foreach ($ordini as $oRow) {
        if ($oRow['stato'] === 'completato') $ordCompletati++;
        if ($oRow['stato'] === 'in_attesa') $ordAttesa++;
        $speseTotali += (float)$oRow['totale'];
    }
    ?>
    <h2 class="page-title">I miei Ordini</h2>
    <div class="stat-grid">
        <div class="stat-card c-indigo">
            <span class="stat-icon">ⓘ</span>
            <div class="stat-value"><?= count($ordini) ?></div>
            <div class="stat-label">Ordini Totali</div>
        </div>
        <div class="stat-card c-teal">
            <span class="stat-icon">ⓘ</span>
            <div class="stat-value"><?= $ordCompletati ?></div>
            <div class="stat-label">Completati</div>
        </div>
        <div class="stat-card c-orange">
            <span class="stat-icon">ⓘ</span>
            <div class="stat-value"><?= $ordAttesa ?></div>
            <div class="stat-label">In Attesa</div>
        </div>
        <div class="stat-card c-green">
            <span class="stat-icon">ⓘ</span>
            <div class="stat-value">€<?= number_format($speseTotali, 2, ',', '.') ?></div>
            <div class="stat-label">Spesa Totale</div>
        </div>
    </div>

    <section class="panel panel-wide">
        <div class="panel-header">
            <h2>📦 Storico Ordini <span class="count-chip"><?= count($ordini) ?></span></h2>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Data</th><th>Prodotti</th><th>Totale</th><th>Stato</th></tr></thead>
                <tbody>
                <?php foreach ($ordini as $o): ?>
                    <tr>
                        <td class="cell-muted">#<?= $o['id'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($o['data'])) ?></td>
                        <td><?= htmlspecialchars($o['prodotti']) ?></td>
                        <td>€<?= number_format($o['totale'], 2, ',', '.') ?></td>
                        <td><span class="tag"><?= ucfirst(str_replace('_', ' ', $o['stato'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($ordini)): ?>
                    <tr><td colspan="5" class="cell-empty">Nessun ordine effettuato. <a href="index.php">Inizia a comprare</a>.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
        </main>
    </div>
</div>
</body>
</html>