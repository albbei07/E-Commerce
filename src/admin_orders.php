<?php
session_start();
require 'db.php';
require 'auth.php';
require_admin();
$pdo = connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $oid = (int) ($_POST['order_id'] ?? 0);
    $stato = $_POST['stato'] ?? '';
    if ($oid > 0 && in_array($stato, ['in_attesa', 'completato', 'annullato'])) {
        $pdo->prepare("UPDATE ordini SET stato = ? WHERE id = ?")->execute([$stato, $oid]);
        flash_set('success', "Stato ordine #$oid aggiornato.");
    }
    header('Location: admin_orders.php');
    exit();
}

$filtro = $_GET['stato'] ?? '';
$sql = "SELECT o.*, u.nome, u.cognome, u.email FROM ordini o JOIN utenti u ON o.utente_id = u.id";
$params = [];
if ($filtro !== '') {
    $sql .= " WHERE o.stato = :stato";
    $params[':stato'] = $filtro;
}
$sql .= " ORDER BY o.data DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordini = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestione Ordini · Admin</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <span class="logo-mark">📦</span>
            <span>Magazzino</span>
        </div>
        <div class="sidebar-section-label">Gestionale</div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php"><span class="nav-icon">📊</span> Statistiche</a>
            <a href="admin_users.php"><span class="nav-icon">👥</span> Utenti</a>
            <a href="admin_orders.php" class="active"><span class="nav-icon">🧾</span> Ordini Utenti</a>
            <a href="admin_logs.php"><span class="nav-icon">🗒️</span> Logs Utenti</a>
            <a href="admin_market.php"><span class="nav-icon">🛒</span> Mercato Esterno</a>
            <a href="admin_products.php"><span class="nav-icon">🏬</span> Gestione Magazzino</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php"><span class="nav-icon">🚪</span> Logout</a>
        </div>
    </aside>
    <div class="main-col">
        <header class="topbar">
            <div class="topbar-search"></div>
            <div class="topbar-actions">
                <span class="welcome">Ciao, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong> <span class="badge-admin">Admin</span></span>
                <span class="user-chip"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></span>
            </div>
        </header>
        <main class="page">
    <?php if ($msg = flash_get('success')): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <?php
    $ordAttesa = 0; $ordCompletati = 0; $fatturatoCompletati = 0.0;
    foreach ($ordini as $oRow) {
        if ($oRow['stato'] === 'in_attesa') $ordAttesa++;
        if ($oRow['stato'] === 'completato') {
            $ordCompletati++;
            $fatturatoCompletati += (float)$oRow['totale'];
        }
    }
    ?>
    <h2 class="page-title">Ordini</h2>
    <div class="stat-grid">
        <div class="stat-card c-indigo">
            
            <div class="stat-value"><?= count($ordini) ?></div>
            <div class="stat-label">Ordini Totali</div>
        </div>
        <div class="stat-card c-orange">
            
            <div class="stat-value"><?= $ordAttesa ?></div>
            <div class="stat-label">In Attesa</div>
        </div>
        <div class="stat-card c-teal">
            
            <div class="stat-value"><?= $ordCompletati ?></div>
            <div class="stat-label">Completati</div>
        </div>
        <div class="stat-card c-green">
            
            <div class="stat-value">€<?= number_format($fatturatoCompletati, 2, ',', '.') ?></div>
            <div class="stat-label">Fatturato Completati</div>
        </div>
    </div>

    <section class="panel panel-wide">
        <div class="panel-header">
            <h2>📋 Gestione Ordini <span class="count-chip"><?= count($ordini) ?></span></h2>
        </div>
        <form class="filter-bar" action="admin_orders.php" method="get">
            <select name="stato">
                <option value="">Tutti gli stati</option>
                <option value="in_attesa" <?= $filtro === 'in_attesa' ? 'selected' : '' ?>>In attesa</option>
                <option value="completato" <?= $filtro === 'completato' ? 'selected' : '' ?>>Completato</option>
                <option value="annullato" <?= $filtro === 'annullato' ? 'selected' : '' ?>>Annullato</option>
            </select>
            <button class="btn btn-primary">Filtra</button>
            <?php if ($filtro !== ''): ?><a class="btn btn-ghost" href="admin_orders.php">Reset</a><?php endif; ?>
        </form>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Data</th><th>Cliente</th><th>Totale</th><th>Stato</th><th>Azioni</th></tr></thead>
                <tbody>
                <?php foreach ($ordini as $o): ?>
                    <tr>
                        <td class="cell-muted">#<?= $o['id'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($o['data'])) ?></td>
                        <td>
                            <?= htmlspecialchars($o['nome'] . ' ' . $o['cognome']) ?>
                            <div class="cell-muted" style="font-size:12px;"><?= htmlspecialchars($o['email']) ?></div>
                        </td>
                        <td>€<?= number_format($o['totale'], 2, ',', '.') ?></td>
                        <td><span class="tag"><?= ucfirst(str_replace('_', ' ', $o['stato'])) ?></span></td>
                        <td class="row-actions">
                            <form method="post" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <select name="stato" style="width: auto; padding: 6px 10px; font-size: 13px;">
                                    <option value="in_attesa" <?= $o['stato'] === 'in_attesa' ? 'selected' : '' ?>>In attesa</option>
                                    <option value="completato" <?= $o['stato'] === 'completato' ? 'selected' : '' ?>>Completato</option>
                                    <option value="annullato" <?= $o['stato'] === 'annullato' ? 'selected' : '' ?>>Annullato</option>
                                </select>
                                <button class="btn btn-sm btn-primary">Salva</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($ordini)): ?>
                    <tr><td colspan="6" class="cell-empty">Nessun ordine trovato.</td></tr>
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