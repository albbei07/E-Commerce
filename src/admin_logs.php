<?php
session_start();
require 'db.php';
require 'auth.php';
require_admin();
$pdo = connect();

$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_prodotto = trim($_GET['prodotto'] ?? '');
$sql = "SELECT l.*, p.nome AS prodotto_nome, u.nome AS utente_nome, u.cognome AS utente_cognome, u.tipo_utente AS utente_tipo
FROM log_movimenti l
LEFT JOIN prodotti p ON l.prodotto_id = p.id
LEFT JOIN utenti u ON l.utente_id = u.id
WHERE 1=1";
$params = [];
if ($filtro_tipo !== '') {
    $sql .= " AND l.tipo_movimento = :tipo";
    $params[':tipo'] = $filtro_tipo;
}
if ($filtro_prodotto !== '') {
    $sql .= " AND p.nome LIKE :prod";
    $params[':prod'] = '%' . $filtro_prodotto . '%';
}
$sql .= " ORDER BY l.data DESC LIMIT 500";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$log = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Movimenti · Admin</title>
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
                <a href="admin_orders.php"><span class="nav-icon">🧾</span> Ordini Utenti</a>
                <a href="admin_logs.php" class="active"><span class="nav-icon">🗒️</span> Logs Utenti</a>
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

        <?php
        $totCarico = 0; $totScarico = 0; $totModifica = 0;
        foreach ($log as $lRow) {
            if ($lRow['tipo_movimento'] === 'carico') $totCarico++;
            elseif ($lRow['tipo_movimento'] === 'scarico') $totScarico++;
            else $totModifica++;
        }
        ?>
        <h2 class="page-title">Log Movimenti</h2>
        <div class="stat-grid">
            <div class="stat-card c-indigo">
                
                <div class="stat-value"><?= count($log) ?></div>
                <div class="stat-label">Movimenti Totali</div>
            </div>
            <div class="stat-card c-green">
                
                <div class="stat-value"><?= $totCarico ?></div>
                <div class="stat-label">Carichi (+)</div>
            </div>
            <div class="stat-card c-orange">
                
                <div class="stat-value"><?= $totScarico ?></div>
                <div class="stat-label">Scarichi (−)</div>
            </div>
            <div class="stat-card c-cyan">
                
                <div class="stat-value"><?= $totModifica ?></div>
                <div class="stat-label">Modifiche</div>
            </div>
        </div>

        <section class="panel panel-wide">
            <div class="panel-header">
                <h2>📜 Log Movimenti Magazzino <span class="count-chip"><?= count($log) ?></span></h2>
            </div>
            <form class="filter-bar" action="admin_logs.php" method="get">
                <input type="text" name="prodotto" placeholder="Cerca prodotto..."
                    value="<?= htmlspecialchars($filtro_prodotto) ?>">
                <select name="tipo">
                    <option value="">Tutti i movimenti</option>
                    <option value="carico" <?= $filtro_tipo === 'carico' ? 'selected' : '' ?>>Carico (+)</option>
                    <option value="scarico" <?= $filtro_tipo === 'scarico' ? 'selected' : '' ?>>Scarico (−)</option>
                    <option value="modifica" <?= $filtro_tipo === 'modifica' ? 'selected' : '' ?>>Modifica</option>
                </select>
                <button class="btn btn-primary">Filtra</button>
                <?php if ($filtro_tipo !== '' || $filtro_prodotto !== ''): ?>
                <a class="btn btn-ghost" href="admin_logs.php">Reset</a>
                <?php endif; ?>
            </form>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Prodotto</th>
                            <th>Utente</th>
                            <th>Tipo</th>
                            <th>Qtà</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($log as $l): ?>
                        <tr>
                            <td class="cell-muted"><?= date('d/m/Y H:i', strtotime($l['data'])) ?></td>
                            <td><?= htmlspecialchars($l['prodotto_nome'] ?? '(eliminato)') ?></td>
                            <td>
                                <?php if ($l['utente_id']): ?>
                                <?= htmlspecialchars($l['utente_nome'] . ' ' . $l['utente_cognome']) ?>
                                <?php if (($l['utente_tipo'] ?? '') === 'admin'): ?>
                                <span class="badge badge-admin" style="margin-left:4px;">admin</span>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="cell-muted">Sistema</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $color = $l['tipo_movimento'] === 'carico' ? 'var(--success)' : ($l['tipo_movimento'] === 'scarico' ? 'var(--danger)' : 'var(--warning)');
                                $label = $l['tipo_movimento'] === 'carico' ? '↑ Carico' : ($l['tipo_movimento'] === 'scarico' ? '↓ Scarico' : '↻ Modifica');
                                ?>
                                <span class="tag" style="background: <?= $color ?>22; color: <?= $color ?>;"><?= $label ?></span>
                            </td>
                            <!-- FIX: Il segno ora dipende dal tipo_movimento, non dal valore assoluto della quantità -->
                            <td>
                                <strong>
                                    <?php
                                    $qty = (int) $l['quantita'];
                                    if ($l['tipo_movimento'] === 'scarico') {
                                        echo '-' . $qty;
                                    } elseif ($l['tipo_movimento'] === 'carico') {
                                        echo '+' . $qty;
                                    } else {
                                        echo $qty; // Per 'modifica' o altri tipi
                                    }
                                    ?>
                                </strong>
                            </td>
                            <td class="cell-muted"><?= htmlspecialchars($l['note'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($log)): ?>
                        <tr>
                            <td colspan="6" class="cell-empty">Nessun movimento registrato.</td>
                        </tr>
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