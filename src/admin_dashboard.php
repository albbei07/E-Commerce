<?php
session_start();
require 'db.php';
require 'auth.php';
require_admin();
$pdo = connect();

// Dati per grafici
$categorie = $pdo->query("
    SELECT p.categoria, COUNT(od.id) as vendite, SUM(od.quantita * od.prezzo_unitario) as fatturato
    FROM ordine_dettagli od 
    JOIN prodotti p ON od.prodotto_id = p.id 
    GROUP BY p.categoria ORDER BY vendite DESC LIMIT 6
")->fetchAll();

$ultimi_ordini = $pdo->query("
    SELECT o.*, u.nome, u.cognome 
    FROM ordini o JOIN utenti u ON o.utente_id = u.id 
    ORDER BY o.data DESC LIMIT 5
")->fetchAll();

$stats = [
    'utenti' => $pdo->query("SELECT COUNT(*) FROM utenti")->fetchColumn(),
    'prodotti' => $pdo->query("SELECT COUNT(*) FROM prodotti")->fetchColumn(),
    'ordini' => $pdo->query("SELECT COUNT(*) FROM ordini")->fetchColumn(),
    'fatturato' => $pdo->query("SELECT COALESCE(SUM(totale),0) FROM ordini WHERE stato='completato'")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Stili specifici pagina: già coperti da style.css (sidebar, dark mode, theme-toggle) -->
    <style>
        .chart-empty {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
            font-style: italic;
        }
    </style>
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
            <a href="admin_dashboard.php" class="active"><span class="nav-icon">📊</span> Statistiche</a>
            <a href="admin_users.php"><span class="nav-icon">👥</span> Utenti</a>
            <a href="admin_orders.php"><span class="nav-icon">🧾</span> Ordini Utenti</a>
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
    <h2 class="page-title">Dashboard</h2>
    <!-- KPI Cards -->
    <?php
    $kpiColors = ['indigo', 'teal', 'orange', 'green'];
    $kpiIndex = 0;
    ?>
    <div class="stat-grid">
        <?php foreach ($stats as $label => $val): ?>
        <div class="stat-card c-<?= $kpiColors[$kpiIndex % count($kpiColors)] ?>">
            
            <div class="stat-value"><?= is_numeric($val) && $label !== 'fatturato' ? number_format($val, 0, ',', '.') : (is_numeric($val) ? '€' . number_format($val, 2, ',', '.') : $val) ?></div>
            <div class="stat-label"><?= ucfirst($label) ?></div>
        </div>
        <?php $kpiIndex++; endforeach; ?>
    </div>

    <!-- Grafico Vendite per Categoria -->
    <section class="panel panel-wide">
        <h2>Vendite per Categoria</h2>
        <?php if (!empty($categorie)): ?>
            <canvas id="chartCategorie" height="100"></canvas>
        <?php else: ?>
            <div class="chart-empty">Nessun dato disponibile. Completa alcuni ordini per visualizzare le statistiche.</div>
        <?php endif; ?>
    </section>

    <!-- Ultimi Ordini -->
    <section class="panel panel-wide">
        <h2>Ultimi Ordini</h2>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Cliente</th><th>Totale</th><th>Stato</th></tr></thead>
                <tbody>
                <?php foreach ($ultimi_ordini as $o): ?>
                    <tr>
                        <td>#<?= $o['id'] ?></td>
                        <td><?= htmlspecialchars($o['nome'] . ' ' . $o['cognome']) ?></td>
                        <td>€<?= number_format($o['totale'], 2, ',', '.') ?></td>
                        <td><span class="tag"><?= ucfirst(str_replace('_', ' ', $o['stato'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($ultimi_ordini)): ?>
                    <tr><td colspan="4" class="cell-empty">Nessun ordine recente.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
        </main>
    </div>
</div>

<script>

// Chart.js Configuration (solo se ci sono dati)
<?php if (!empty($categorie)): ?>
const ctx = document.getElementById('chartCategorie').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($categorie, 'categoria')) ?>,
        datasets: [{
            label: 'Fatturato (€)',
            data: <?= json_encode(array_column($categorie, 'fatturato')) ?>,
            backgroundColor: '#4f46e5',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
<?php endif; ?>
</script>
</body>
</html>