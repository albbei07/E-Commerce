<?php
session_start();
require 'db.php';
require 'auth.php';
require_login();

// FIX: Inizializzazione connessione DB
$pdo = connect();

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Gestione azioni POST (Aggiunta o Checkout)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id = (int)($_POST['id'] ?? 0);
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        
        $stmt = $pdo->prepare("SELECT quantita FROM prodotti WHERE id = ?");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        
        if ($p && $p['quantita'] >= ($_SESSION['cart'][$id] ?? 0) + $qty) {
            $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + $qty;
            flash_set('success', 'Articolo aggiunto al carrello!');
        } else {
            flash_set('error', 'Scorta insufficiente per questo prodotto.');
        }
        header('Location: cart.php');
        exit();
    }

    if ($action === 'remove') {
        $id = (int)($_POST['id'] ?? 0);
        unset($_SESSION['cart'][$id]);
        header('Location: cart.php');
        exit();
    }

    if ($action === 'checkout') {
        if (empty($_SESSION['cart'])) {
            flash_set('error', 'Il carrello è vuoto.');
            header('Location: cart.php');
            exit();
        }

        $pdo->beginTransaction();
        try {
            $totale = 0;
            $dettagli = [];
            
            foreach ($_SESSION['cart'] as $pid => $qta) {
                $stmt = $pdo->prepare("SELECT * FROM prodotti WHERE id = ? FOR UPDATE");
                $stmt->execute([$pid]);
                $p = $stmt->fetch();
                
                if (!$p || $p['quantita'] < $qta) {
                    throw new Exception("Scorta insufficiente per '{$p['nome']}'. Disponibile: {$p['quantita']}");
                }
                
                $totale += $p['prezzo'] * $qta;
                $dettagli[] = ['p' => $p, 'qta' => $qta];
            }

            // Crea ordine
            $pdo->prepare("INSERT INTO ordini (utente_id, totale) VALUES (?, ?)")
                ->execute([$_SESSION['user_id'], $totale]);
            $oid = (int)$pdo->lastInsertId();

            // Salva dettagli, aggiorna scorte e REGISTRA IL LOG
            foreach ($dettagli as $d) {
                $pdo->prepare("INSERT INTO ordine_dettagli (ordine_id, prodotto_id, quantita, prezzo_unitario) VALUES (?, ?, ?, ?)")
                    ->execute([$oid, $d['p']['id'], $d['qta'], $d['p']['prezzo']]);
                
                $pdo->prepare("UPDATE prodotti SET quantita = quantita - ? WHERE id = ?")
                    ->execute([$d['qta'], $d['p']['id']]);

                // --- NUOVA MODIFICA: LOG SCARICO CHECKOUT ---
                // Registra lo scarico nel log movimenti visibile in admin_logs.php
                $pdo->prepare("INSERT INTO log_movimenti (prodotto_id, utente_id, tipo_movimento, quantita, note) VALUES (?, ?, 'scarico', ?, ?)")
                    ->execute([
                        $d['p']['id'], 
                        $_SESSION['user_id'], 
                        $d['qta'], 
                        "Checkout ordine #$oid: {$d['p']['nome']} (x{$d['qta']})"
                    ]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            flash_set('success', "🎉 Ordine #$oid effettuato con successo! Totale: €" . number_format($totale, 2, ',', '.'));
            header('Location: orders.php');
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            flash_set('error', $e->getMessage());
            header('Location: cart.php');
            exit();
        }
    }
}

// Visualizzazione carrello
$carrello = [];
$totale = 0;
if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
    $stmt = $pdo->query("SELECT * FROM prodotti WHERE id IN ($ids)");
    $prodotti_db = $stmt->fetchAll();
    
    foreach ($prodotti_db as $p) {
        $qta = $_SESSION['cart'][$p['id']];
        $subtotale = $p['prezzo'] * $qta;
        $totale += $subtotale;
        $carrello[] = [
            'id' => $p['id'],
            'nome' => $p['nome'],
            'prezzo' => $p['prezzo'],
            'qta' => $qta,
            'subtotale' => $subtotale
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Carrello · E-Commerce Beifiori</title>
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
            <a href="cart.php" class="active"><span class="nav-icon">🧺</span> Carrello</a>
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
            <h2>🛒 Il tuo Carrello</h2>
        </div>
        <?php if (empty($carrello)): ?>
            <p class="cell-empty">Il carrello è vuoto. <a href="index.php">Torna ai prodotti</a>.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Prodotto</th><th>Prezzo</th><th>Qtà</th><th>Subtotale</th><th>Azioni</th></tr></thead>
                    <tbody>
                    <?php foreach ($carrello as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['nome']) ?></td>
                            <td>€<?= number_format($item['prezzo'], 2, ',', '.') ?></td>
                            <td><?= $item['qta'] ?></td>
                            <td>€<?= number_format($item['subtotale'], 2, ',', '.') ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button class="btn btn-sm btn-danger">Rimuovi</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="text-align: right; margin: 20px 0; padding-top: 16px; border-top: 1px solid var(--border);">
                <h3>Totale: €<?= number_format($totale, 2, ',', '.') ?></h3>
                <form method="post" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="checkout">
                    <button class="btn btn-primary">💳 Procedi al Checkout</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
        </main>
    </div>
</div>
</body>
</html>