<?php
session_start();
require 'db.php';
require 'auth.php';
require_admin();
$pdo = connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $uid = (int) ($_POST['user_id'] ?? 0);
    $act = $_POST['action'] ?? '';

    if ($uid <= 0 || $uid === (int) $_SESSION['user_id']) {
        flash_set('error', 'Non puoi modificare il tuo stesso account.');
    } else {
        try {
            if ($act === 'promote') {
                $pdo->prepare("UPDATE utenti SET tipo_utente='admin' WHERE id=?")->execute([$uid]);
                flash_set('success', 'Utente promosso ad admin.');
            } elseif ($act === 'demote') {
                $pdo->prepare("UPDATE utenti SET tipo_utente='user' WHERE id=?")->execute([$uid]);
                flash_set('success', 'Utente retrocesso a utente.');
            } elseif ($act === 'delete') {
                $pdo->prepare("DELETE FROM utenti WHERE id=?")->execute([$uid]);
                flash_set('success', 'Utente eliminato.');
            }
        } catch (PDOException $e) {
            flash_set('error', 'Errore database: ' . $e->getMessage());
        }
    }
    header('Location: admin_users.php');
    exit();
}

$utenti = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM ordini WHERE utente_id = u.id) AS num_ordini,
           (SELECT COALESCE(SUM(totale),0) FROM ordini WHERE utente_id = u.id) AS spesa_totale
    FROM utenti u
    ORDER BY tipo_utente DESC, nome ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestione Utenti · Admin</title>
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
            <a href="admin_users.php" class="active"><span class="nav-icon">👥</span> Utenti</a>
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
    <?php if ($msg = flash_get('success')): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($msg = flash_get('error')): ?><div class="alert alert-error"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <?php
    $totAdmin = 0; $spesaComplessiva = 0.0;
    foreach ($utenti as $uRow) {
        if ($uRow['tipo_utente'] === 'admin') $totAdmin++;
        $spesaComplessiva += (float)$uRow['spesa_totale'];
    }
    ?>
    <h2 class="page-title">Utenti</h2>
    <div class="stat-grid">
        <div class="stat-card c-indigo">
            
            <div class="stat-value"><?= count($utenti) ?></div>
            <div class="stat-label">Utenti Totali</div>
        </div>
        <div class="stat-card c-blue">
            
            <div class="stat-value"><?= $totAdmin ?></div>
            <div class="stat-label">Amministratori</div>
        </div>
        <div class="stat-card c-teal">
            
            <div class="stat-value"><?= count($utenti) - $totAdmin ?></div>
            <div class="stat-label">Utenti Standard</div>
        </div>
        <div class="stat-card c-green">
            
            <div class="stat-value">€<?= number_format($spesaComplessiva, 2, ',', '.') ?></div>
            <div class="stat-label">Spesa Complessiva</div>
        </div>
    </div>

    <section class="panel panel-wide">
        <div class="panel-header">
            <h2>👥 Gestione Utenti <span class="count-chip"><?= count($utenti) ?></span></h2>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Nome</th><th>Email</th><th>Ruolo</th><th>Ordini</th><th>Spesa totale</th><th>Ultimo accesso</th><th>Azioni</th></tr></thead>
                <tbody>
                <?php foreach ($utenti as $u): ?>
                    <tr>
                        <td class="cell-muted">#<?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['nome'] . ' ' . $u['cognome']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="tag" style="<?= $u['tipo_utente'] === 'admin' ? 'background: var(--primary-soft); color: var(--primary);' : '' ?>">
                                <?= $u['tipo_utente'] === 'admin' ? '👑 admin' : 'utente' ?>
                            </span>
                        </td>
                        <td><?= (int) $u['num_ordini'] ?></td>
                        <td>€<?= number_format($u['spesa_totale'], 2, ',', '.') ?></td>
                        <td class="cell-muted"><?= $u['ultimo_accesso'] ? date('d/m/Y H:i', strtotime($u['ultimo_accesso'])) : '—' ?></td>
                        <td class="row-actions">
                            <?php if ((int) $u['id'] !== (int) $_SESSION['user_id']): ?>
                                <form method="post" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <?php if ($u['tipo_utente'] === 'user'): ?>
                                        <input type="hidden" name="action" value="promote">
                                        <button class="btn btn-sm btn-primary">Promuovi</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="demote">
                                        <button class="btn btn-sm btn-ghost">Retrocedi</button>
                                    <?php endif; ?>
                                </form>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Eliminare definitivamente questo utente? Tutti i suoi ordini verranno persi.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="btn btn-sm btn-danger">Elimina</button>
                                </form>
                            <?php else: ?>
                                <span class="cell-muted">Sei tu</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
        </main>
    </div>
</div>
</body>
</html>