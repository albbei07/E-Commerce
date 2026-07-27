<?php
session_start();
require 'db.php';
require 'auth.php';
require_admin();
$pdo = connect();

// --- 1. CALCOLO BUDGET (DEVE ESSERE FATTO PRIMA DI TUTTO) ---
$budget = $pdo->query("SELECT * FROM budget_aziendale WHERE anno = YEAR(CURDATE()) LIMIT 1")->fetch();

// Se il budget per l'anno corrente non esiste, creiamolo al volo
if (!$budget) {
    $pdo->prepare("INSERT INTO budget_aziendale (anno, importo_totale, importo_speso) VALUES (?, 50000, 0)")
        ->execute([date('Y')]);
    $budget = $pdo->query("SELECT * FROM budget_aziendale WHERE anno = YEAR(CURDATE()) LIMIT 1")->fetch();
}

// Calcolo sicuro del residuo (evita valori null)
$residuo = max(0, ($budget['importo_totale'] ?? 0) - ($budget['importo_speso'] ?? 0));


// --- 2. FILTRI GET ---
$search    = trim($_GET['q'] ?? '');
$category  = $_GET['cat'] ?? '';
$fornitore = (int)($_GET['fornitore'] ?? 0);
$sort      = $_GET['sort'] ?? 'nome ASC';

$allowedSorts = ['nome ASC', 'prezzo_acquisto ASC', 'prezzo_acquisto DESC', 'tempo_consegna_giorni ASC'];
if (!in_array($sort, $allowedSorts)) $sort = 'nome ASC';


// --- 3. QUERY PRODOTTI ESTERNI (FIX PARAMETRI PDO) ---
$sql = "SELECT pe.*, f.nome_azienda AS fornitore_nome 
        FROM prodotti_esterni pe 
        JOIN fornitori f ON pe.fornitore_id = f.id 
        WHERE 1=1";
$params = [];

// FIX: Placeholder unici per evitare errore HY093 su named params duplicati
if ($search !== '') {
    $sql .= " AND (pe.nome LIKE :search_nome OR pe.descrizione LIKE :search_desc)";
    $params[':search_nome'] = '%' . $search . '%';
    $params[':search_desc'] = '%' . $search . '%';
}

if ($category !== '') {
    $sql .= " AND pe.categoria = :categoria";
    $params[':categoria'] = $category;
}

if ($fornitore > 0) {
    $sql .= " AND pe.fornitore_id = :fornitore";
    $params[':fornitore'] = $fornitore;
}

$sql .= " ORDER BY " . $sort;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prodotti_esterni = $stmt->fetchAll();


// --- 4. DATI DI SUPPORTO PER I FILTRI ---
$categorie = $pdo->query("SELECT DISTINCT categoria FROM prodotti_esterni ORDER BY categoria")->fetchAll(PDO::FETCH_COLUMN);
$fornitori_list = $pdo->query("SELECT id, nome_azienda FROM fornitori ORDER BY nome_azienda")->fetchAll();


// --- 5. GESTIONE ACQUISTO POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'acquista') {
        $ext_id = (int)($_POST['ext_id'] ?? 0);
        $qty    = max(1, (int)($_POST['qty'] ?? 1));
        
        try {
            $pdo->beginTransaction();
            
            // Ricalcoliamo il budget DENTRO la transazione con FOR UPDATE per sicurezza
            $stmt_budget = $pdo->prepare("SELECT * FROM budget_aziendale WHERE anno = YEAR(CURDATE()) LIMIT 1 FOR UPDATE");
            $stmt_budget->execute();
            $current_budget = $stmt_budget->fetch();
            
            if (!$current_budget) throw new RuntimeException("Budget annuale non trovato.");
            
            $residuo_attuale = max(0, $current_budget['importo_totale'] - $current_budget['importo_speso']);
            
            // Recupero prodotto esterno
            $stmt_prod = $pdo->prepare("
                SELECT pe.*, f.nome_azienda AS fornitore_nome 
                FROM prodotti_esterni pe 
                JOIN fornitori f ON pe.fornitore_id = f.id 
                WHERE pe.id = ?
            ");
            $stmt_prod->execute([$ext_id]);
            $prodotto_ext = $stmt_prod->fetch();
            
            if (!$prodotto_ext) throw new RuntimeException('Prodotto non trovato.');
            
            $costo_totale = round($prodotto_ext['prezzo_acquisto'] * $qty, 2);
            
            if ($costo_totale > $residuo_attuale) {
                throw new RuntimeException("Budget insufficiente! Costo: €" . number_format($costo_totale, 2, ',', '.') . " | Residuo: €" . number_format($residuo_attuale, 2, ',', '.'));
            }
            
            // 1. Aggiorna budget
            $pdo->prepare("UPDATE budget_aziendale SET importo_speso = importo_speso + ? WHERE id = ?")
                ->execute([$costo_totale, $current_budget['id']]);
            
            // 2. Cerca se prodotto esiste già in magazzino
            $stmt_check = $pdo->prepare("SELECT id, quantita FROM prodotti WHERE nome = ? AND categoria = ? LIMIT 1");
            $stmt_check->execute([$prodotto_ext['nome'], $prodotto_ext['categoria']]);
            $existing = $stmt_check->fetch();
            
            if ($existing) {
                $pdo->prepare("UPDATE prodotti SET quantita = quantita + ?, last_update = NOW() WHERE id = ?")
                    ->execute([$qty, $existing['id']]);
                $msg_detail = "Aggiornata scorta di «{$prodotto_ext['nome']}» (+{$qty})";
            } else {
                $pdo->prepare("INSERT INTO prodotti (nome, descrizione, categoria, quantita, prezzo) VALUES (?, ?, ?, ?, ?)")
                    ->execute([
                        $prodotto_ext['nome'],
                        $prodotto_ext['descrizione'],
                        $prodotto_ext['categoria'],
                        $qty,
                        $prodotto_ext['prezzo_suggerito_vendita']
                    ]);
                $msg_detail = "Nuovo prodotto «{$prodotto_ext['nome']}» aggiunto al magazzino ({$qty} pz)";
            }
            
            // 3. Log movimento carico
            $new_prod_id = $existing['id'] ?? (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO log_movimenti (prodotto_id, utente_id, tipo_movimento, quantita, note) VALUES (?, ?, 'carico', ?, ?)")
                ->execute([$new_prod_id, $_SESSION['user_id'], $qty, "Acquisto esterno da {$prodotto_ext['fornitore_nome']}"]);
            
            // 4. Registra ordine acquisto storico
            $pdo->prepare("INSERT INTO ordini_acquisto (fornitore_id, stato, costo_totale, note_admin) VALUES (?, 'ricevuto', ?, ?)")
                ->execute([$prodotto_ext['fornitore_id'], $costo_totale, "Acquisto rapido: {$qty}x {$prodotto_ext['nome']}"]);
            
            $pdo->commit();
            flash_set('success', "$msg_detail. Spesa: €" . number_format($costo_totale, 2, ',', '.'));
            
        } catch (RuntimeException | PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Errore acquisto market: ' . $e->getMessage());
            flash_set('error', $e->getMessage() ?: 'Errore durante l\'acquisto. Riprova.');
        }
        
        header('Location: admin_market.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mercato Esterno · Acquisti Admin</title>
<link rel="stylesheet" href="style.css">
<style>
.budget-widget { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; border-radius: var(--radius); padding: 20px 24px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
.budget-widget h3 { font-size: 13px; opacity: 0.85; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em; }
.budget-widget .amount { font-size: 28px; font-weight: 700; }
.budget-widget .detail { text-align: right; font-size: 13px; opacity: 0.8; line-height: 1.6; }
.qty-input { width: 70px !important; text-align: center; padding: 8px !important; }
.cost-preview { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
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
            <a href="admin_dashboard.php"><span class="nav-icon">📊</span> Statistiche</a>
            <a href="admin_users.php"><span class="nav-icon">👥</span> Utenti</a>
            <a href="admin_orders.php"><span class="nav-icon">🧾</span> Ordini Utenti</a>
            <a href="admin_logs.php"><span class="nav-icon">🗒️</span> Logs Utenti</a>
            <a href="admin_market.php" class="active"><span class="nav-icon">🛒</span> Mercato Esterno</a>
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
    <?php if ($msg = flash_get('error')): ?><div class="alert alert-error"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <!-- WIDGET BUDGET (ORA $residuo È DEFINITO SICURAMENTE) -->
    <div class="budget-widget">
        <div>
            <h3>Budget <?= date('Y') ?> — Residuo</h3>
            <div class="amount">€<?= number_format($residuo, 2, ',', '.') ?></div>
        </div>
        <div class="detail">
            Totale: €<?= number_format((float)$budget['importo_totale'], 2, ',', '.') ?><br>
            Speso: €<?= number_format((float)$budget['importo_speso'], 2, ',', '.') ?>
        </div>
    </div>

    <!-- SEZIONE FILTRI -->
    <section class="panel panel-wide">
        <div class="panel-header">
            <h2>Catalogo Fornitori <span class="count-chip"><?= count($prodotti_esterni) ?></span></h2>
        </div>
        <form class="filter-bar" action="admin_market.php" method="get">
            <input type="text" name="q" placeholder="Cerca prodotto..." value="<?= htmlspecialchars($search) ?>">
            <select name="cat">
                <option value="">Tutte le categorie</option>
                <?php foreach ($categorie as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= ($category === $c) ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="fornitore">
                <option value="">Tutti i fornitori</option>
                <?php foreach ($fornitori_list as $f): ?>
                    <option value="<?= $f['id'] ?>" <?= ($fornitore === $f['id']) ? 'selected' : '' ?>><?= htmlspecialchars($f['nome_azienda']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="sort">
                <option value="nome ASC" <?= ($sort === 'nome ASC') ? 'selected' : '' ?>>Nome A-Z</option>
                <option value="prezzo_acquisto ASC" <?= ($sort === 'prezzo_acquisto ASC') ? 'selected' : '' ?>>Prezzo ↑</option>
                <option value="prezzo_acquisto DESC" <?= ($sort === 'prezzo_acquisto DESC') ? 'selected' : '' ?>>Prezzo ↓</option>
                <option value="tempo_consegna_giorni ASC" <?= ($sort === 'tempo_consegna_giorni ASC') ? 'selected' : '' ?>>Consegna ↑</option>
            </select>
            <button type="submit" class="btn btn-primary">Filtra</button>
            <?php if ($search || $category || $fornitore || $sort !== 'nome ASC'): ?>
                <a class="btn btn-ghost" href="admin_market.php">Reset</a>
            <?php endif; ?>
        </form>

        <!-- TABELLA PRODOTTI ESTERNI CON ACQUISTO DIRETTO -->
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr><th>Fornitore</th><th>Prodotto</th><th>Categoria</th><th>Prezzo Acquisto</th><th>Min. Ordine</th><th>Consegna</th><th>Acquista</th></tr>
                </thead>
                <tbody>
                <?php foreach ($prodotti_esterni as $p): 
                    $costo_preview = round($p['prezzo_acquisto'] * $p['quantita_minima_ordine'], 2);
                    $can_afford = $costo_preview <= $residuo;
                ?>
                    <tr class="<?= !$can_afford ? 'row-low-stock' : '' ?>">
                        <td class="cell-muted"><?= htmlspecialchars($p['fornitore_nome']) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($p['nome']) ?></strong>
                            <div class="cell-muted" style="font-size:12px; margin-top:2px;"><?= htmlspecialchars(mb_strimwidth($p['descrizione'], 0, 60, '...')) ?></div>
                        </td>
                        <td><span class="tag"><?= htmlspecialchars($p['categoria']) ?></span></td>
                        <td><strong>€<?= number_format((float)$p['prezzo_acquisto'], 2, ',', '.') ?></strong></td>
                        <td><?= $p['quantita_minima_ordine'] ?> pz</td>
                        <td><?= $p['tempo_consegna_giorni'] ?> gg</td>
                        <td class="row-actions">
                            <form method="post" style="display:flex; align-items:center; gap:6px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="acquista">
                                <input type="hidden" name="ext_id" value="<?= $p['id'] ?>">
                                <input type="number" name="qty" class="qty-input" min="<?= $p['quantita_minima_ordine'] ?>" value="<?= $p['quantita_minima_ordine'] ?>" step="<?= $p['quantita_minima_ordine'] ?>" required>
                                <button type="submit" class="btn btn-sm btn-primary" <?= !$can_afford ? 'disabled title="Budget insufficiente"' : '' ?>>
                                     Acquista
                                </button>
                            </form>
                            <div class="cost-preview">Min: €<?= number_format($costo_preview, 2, ',', '.') ?></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($prodotti_esterni)): ?>
                    <tr><td colspan="7" class="cell-empty">Nessun prodotto trovato con questi filtri.</td></tr>
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