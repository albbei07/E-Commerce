<?php
session_start();
require 'db.php';
require 'auth.php';
require_admin();
$pdo = connect();
// --- Filtro/ricerca (GET) ---
$search   = trim($_GET['q'] ?? '');
$category = trim($_GET['categoria'] ?? '');
$sort     = $_GET['sort'] ?? 'id DESC';

// Whitelist sicurezza ordinamento
$allowedSorts = ['id DESC', 'nome ASC', 'prezzo ASC', 'prezzo DESC', 'quantita ASC', 'quantita DESC'];
if (!in_array($sort, $allowedSorts)) $sort = 'id DESC';

$sql = "SELECT * FROM prodotti WHERE 1=1";
$params = [];
if ($search !== '') {
// FIX: Placeholder unici per evitare errore PDO HY093
$sql .= " AND (nome LIKE :search_nome OR descrizione LIKE :search_desc)";
$params[':search_nome'] = '%' . $search . '%';
$params[':search_desc'] = '%' . $search . '%';
}
if ($category !== '') {
$sql .= " AND categoria = :categoria";
$params[':categoria'] = $category;
}
// ORDINAMENTO (dinamico, con fallback ID decrescente)
$sql .= " ORDER BY " . $sort;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prodotti = $stmt->fetchAll();
$categorie = $pdo->query("SELECT DISTINCT categoria FROM prodotti ORDER BY categoria ASC")->fetchAll(PDO::FETCH_COLUMN);
$editing = null;
if (isset($_GET['edit'])) {
$stmt = $pdo->prepare("SELECT * FROM prodotti WHERE id = :id");
$stmt->execute([':id' => (int) $_GET['edit']]);
$editing = $stmt->fetch();
}
$lowStockThreshold = 10;

// Metriche per le card riepilogative in stile dashboard
$totProdotti = count($prodotti);
$totCategorie = count($categorie);
$scorteBasse = 0;
$valoreMagazzino = 0.0;
foreach ($prodotti as $pStat) {
    $valoreMagazzino += (float)$pStat['prezzo'] * (int)$pStat['quantita'];
    if ((int)$pStat['quantita'] <= $lowStockThreshold) {
        $scorteBasse++;
    }
}
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pannello Admin · Gestione Magazzino</title>
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
                <a href="admin_logs.php"><span class="nav-icon">🗒️</span> Logs Utenti</a>
                <a href="admin_market.php"><span class="nav-icon">🛒</span> Mercato Esterno</a>
                <a href="admin_products.php" class="active"><span class="nav-icon">🏬</span> Gestione Magazzino</a>
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
        <?php if ($msg = flash_get('success')): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash_get('error')): ?>
        <div class="alert alert-error"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <!-- Messaggio rosso se ricerca non trova nulla -->
        <?php if (($search !== '' || $category !== '') && empty($prodotti)): ?>
        <div class="alert alert-error">
            Nessun prodotto trovato con i criteri inseriti.
            <a href="admin_products.php" style="color:inherit; text-decoration:underline;">Resetta filtri</a>
        </div>
        <?php endif; ?>

        <h2 class="page-title">Dashboard Magazzino</h2>
        <div class="stat-grid">
            <div class="stat-card c-indigo">
                
                <div class="stat-value"><?= $totProdotti ?></div>
                <div class="stat-label">Prodotti Totali</div>
            </div>
            <div class="stat-card c-teal">
                
                <div class="stat-value"><?= $totCategorie ?></div>
                <div class="stat-label">Categorie Attive</div>
            </div>
            <div class="stat-card c-orange">
                
                <div class="stat-value"><?= $scorteBasse ?></div>
                <div class="stat-label">Prodotti Scorta Bassa</div>
            </div>
            <div class="stat-card c-cyan">
                
                <div class="stat-value">€<?= number_format($valoreMagazzino, 2, ',', '.') ?></div>
                <div class="stat-label">Valore Magazzino</div>
            </div>
        </div>

        <div class="admin-grid">
            <!-- FORM MODIFICA LIMITATO -->
            <section class="panel">
                <h2><?= $editing ? 'Modifica prodotto #' . (int) $editing['id'] : 'Seleziona un prodotto da modificare' ?>
                </h2>

                <?php if ($editing): ?>
                <form class="stack-form" action="product_actions.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">

                    <div class="field">
                        <label for="nome">Nuovo Nome</label>
                        <input type="text" id="nome" name="nome" required maxlength="100"
                            value="<?= htmlspecialchars($editing['nome']) ?>">
                    </div>

                    <div class="field">
                        <label for="descrizione">Nuova Descrizione</label>
                        <textarea id="descrizione" name="descrizione" required
                            rows="3"><?= htmlspecialchars($editing['descrizione']) ?></textarea>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label for="riduci_quantita">Riduci Quantità (-)</label>
                            <input type="number" id="riduci_quantita" name="riduci_quantita" min="0" step="1"
                                max="<?= (int)$editing['quantita'] ?>" placeholder="Es: 5">
                            <small style="color:var(--text-muted)">Disponibile: <?= (int)$editing['quantita'] ?></small>
                        </div>

                        <!-- MODIFICA: campo prezzo da "Riduci" a "Nuovo prezzo" (valore assoluto, permette aumenti e diminuzioni) -->
                        <div class="field">
                            <label for="nuovo_prezzo">Nuovo Prezzo Esposizione (€)</label>
                            <input type="number" id="nuovo_prezzo" name="nuovo_prezzo" required min="0" step="0.01"
                                value="<?= htmlspecialchars(number_format((float)$editing['prezzo'], 2, '.', '')) ?>">
                            <small style="color:var(--text-muted)">Attuale:
                                €<?= number_format((float)$editing['prezzo'], 2, ',', '.') ?></small>
                        </div>
                    </div>

                    <div class="field-actions">
                        <button type="submit" class="btn btn-primary">Salva Modifiche</button>
                        <a class="btn btn-ghost" href="admin_products.php">Annulla</a>
                    </div>
                </form>
                <?php else: ?>
                <p class="cell-empty">Clicca su "Modifica" nella tabella a destra per editare un prodotto.</p>
                <?php endif; ?>
            </section>

            <!-- LISTA PRODOTTI CON RIACQUISTO -->
            <section class="panel panel-wide">
                <div class="panel-header">
                    <h2>Prodotti in magazzino <span class="count-chip"><?= count($prodotti) ?></span></h2>
                </div>
                <form class="filter-bar" action="admin_products.php" method="get">
                    <input type="text" name="q" placeholder="Cerca per nome o descrizione..."
                        value="<?= htmlspecialchars($search) ?>">
                    <select name="categoria">
                        <option value="">Tutte le categorie</option>
                        <?php foreach ($categorie as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $category === $c ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="sort">
                        <option value="id DESC" <?= $sort === 'id DESC' ? 'selected' : '' ?>>Più recenti</option>
                        <option value="nome ASC" <?= $sort === 'nome ASC' ? 'selected' : '' ?>>Nome A-Z</option>
                        <option value="prezzo ASC" <?= $sort === 'prezzo ASC' ? 'selected' : '' ?>>Prezzo ↑</option>
                        <option value="prezzo DESC" <?= $sort === 'prezzo DESC' ? 'selected' : '' ?>>Prezzo ↓</option>
                        <option value="quantita ASC" <?= $sort === 'quantita ASC' ? 'selected' : '' ?>>Quantità ↑</option>
                        <option value="quantita DESC" <?= $sort === 'quantita DESC' ? 'selected' : '' ?>>Quantità ↓</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filtra</button>
                    <?php if ($search !== '' || $category !== '' || $sort !== 'id DESC'): ?>
                    <a class="btn btn-ghost" href="admin_products.php">Reset</a>
                    <?php endif; ?>
                </form>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Categoria</th>
                                <th>Quantità</th>
                                <th>Prezzo</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prodotti as $prodotto): ?>
                            <tr class="<?= (int) $prodotto['quantita'] <= $lowStockThreshold ? 'row-low-stock' : '' ?>">
                                <td class="cell-muted">#<?= (int) $prodotto['id'] ?></td>
                                <td><?= htmlspecialchars($prodotto['nome']) ?></td>
                                <td><span class="tag"><?= htmlspecialchars($prodotto['categoria']) ?></span></td>
                                <td>
                                    <?= (int) $prodotto['quantita'] ?>
                                    <?php if ((int) $prodotto['quantita'] <= $lowStockThreshold): ?>
                                    <span class="badge badge-warning">scorta bassa</span>
                                    <?php endif; ?>
                                </td>
                                <td>€<?= number_format((float) $prodotto['prezzo'], 2, ',', '.') ?></td>
                                <td class="row-actions">
                                    <a class="btn btn-sm btn-ghost"
                                        href="admin_products.php?edit=<?= (int) $prodotto['id'] ?>">Modifica</a>
                                    <!-- PULSANTE RIACQUISTA -->
                                    <a class="btn btn-sm btn-primary"
                                        href="admin_market.php?q=<?= urlencode($prodotto['nome']) ?>&cat=<?= urlencode($prodotto['categoria']) ?>"
                                        title="Cerca questo prodotto nel mercato esterno">
                                        🔄 Riacquista
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($prodotti) && !($search !== '' || $category !== '')): ?>
                            <tr>
                                <td colspan="6" class="cell-empty">Nessun prodotto presente.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
            </main>
        </div>
    </div>
</body>

</html>