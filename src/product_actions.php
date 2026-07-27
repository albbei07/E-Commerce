<?php
session_start();

require 'db.php';
require 'auth.php';

require_admin();

// --- Export CSV ---
if (isset($_POST['action']) && $_POST['action'] === 'export_csv') {
    csrf_verify();

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename="prodotti_' .
        date('Y-m-d_His') .
        '.csv"'
    );

    $pdo = connect();

    $out = fopen('php://output', 'w');

    fputcsv($out, [
        'ID',
        'Nome',
        'Descrizione',
        'Categoria',
        'Quantità',
        'Prezzo (€)'
    ]);

    $stmt = $pdo->query(
        "SELECT id, nome, descrizione, categoria, quantita, prezzo
         FROM prodotti
         ORDER BY nome ASC"
    );

    while ($row = $stmt->fetch()) {
        fputcsv($out, $row);
    }

    fclose($out);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_products.php');
    exit();
}

csrf_verify();

$action = $_POST['action'] ?? '';

try {
    $pdo = connect();

    // UPDATE LIMITATO:
    // Solo nome, descrizione, riduzione quantità e nuovo prezzo
    if ($action === 'update') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            throw new InvalidArgumentException('ID prodotto non valido.');
        }

        $nome        = trim($_POST['nome'] ?? '');
        $descrizione = trim($_POST['descrizione'] ?? '');
        $riduci_qty  = max(0, (int) ($_POST['riduci_quantita'] ?? 0));

        // Riceviamo il nuovo prezzo assoluto
        $nuovo_prezzo_input = $_POST['nuovo_prezzo'] ?? null;

        if ($nome === '' || $descrizione === '') {
            throw new InvalidArgumentException(
                'Nome e descrizione sono obbligatori.'
            );
        }

        if (
            !is_numeric($nuovo_prezzo_input) ||
            (float) $nuovo_prezzo_input < 0
        ) {
            throw new InvalidArgumentException(
                'Il prezzo deve essere un numero non negativo.'
            );
        }

        $nuovo_prezzo = round((float) $nuovo_prezzo_input, 2);

        // Recupero dati attuali
        $old = $pdo->prepare(
            "SELECT *
             FROM prodotti
             WHERE id = ?"
        );

        $old->execute([$id]);

        $vecchio = $old->fetch();

        if (!$vecchio) {
            throw new InvalidArgumentException('Prodotto non trovato.');
        }

        // Calcolo nuova quantità (solo riduzioni)
        $nuova_quantita = max(
            0,
            (int) $vecchio['quantita'] - $riduci_qty
        );

        // Aggiornamento DB
        $stmt = $pdo->prepare(
            "UPDATE prodotti
             SET
                nome = :nome,
                descrizione = :descrizione,
                quantita = :quantita,
                prezzo = :prezzo
             WHERE id = :id"
        );

        $stmt->execute([
            ':nome'        => $nome,
            ':descrizione' => $descrizione,
            ':quantita'    => $nuova_quantita,
            ':prezzo'      => $nuovo_prezzo,
            ':id'          => $id,
        ]);

        // Log riduzione quantità
        if ($riduci_qty > 0) {
            $pdo->prepare(
                "INSERT INTO log_movimenti
                    (prodotto_id, utente_id, tipo_movimento, quantita, note)
                 VALUES (?, ?, 'scarico', ?, ?)"
            )->execute([
                $id,
                $_SESSION['user_id'],
                $riduci_qty,
                'Riduzione manuale quantità'
            ]);
        }

        // Log variazione prezzo
        $prezzo_vecchio = (float) $vecchio['prezzo'];

        if ($nuovo_prezzo != $prezzo_vecchio) {

            $differenza = $nuovo_prezzo - $prezzo_vecchio;

            $tipo_log = $differenza > 0
                ? 'carico'
                : 'modifica';

            $descrizione_log = $differenza > 0
                ? "Aumento prezzo da €" .
                    number_format($prezzo_vecchio, 2, ',', '.') .
                    " a €" .
                    number_format($nuovo_prezzo, 2, ',', '.')
                : "Riduzione prezzo da €" .
                    number_format($prezzo_vecchio, 2, ',', '.') .
                    " a €" .
                    number_format($nuovo_prezzo, 2, ',', '.');

            $pdo->prepare(
                "INSERT INTO log_movimenti
                    (prodotto_id, utente_id, tipo_movimento, quantita, note)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([
                $id,
                $_SESSION['user_id'],
                $tipo_log,
                0,
                $descrizione_log
            ]);
        }

        flash_set(
            'success',
            'Prodotto aggiornato. Nuova qty: ' .
            $nuova_quantita .
            ' | Nuovo prezzo: €' .
            number_format($nuovo_prezzo, 2, ',', '.')
        );
    }

    // DELETE con log scarico residuo
    elseif ($action === 'delete') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            throw new InvalidArgumentException('ID prodotto non valido.');
        }

        // Recupero dati prima dell'eliminazione
        $old = $pdo->prepare(
            "SELECT nome, quantita
             FROM prodotti
             WHERE id = ?"
        );

        $old->execute([$id]);

        $vecchio = $old->fetch();

        if ($vecchio) {
            $pdo->prepare(
                "INSERT INTO log_movimenti
                    (prodotto_id, utente_id, tipo_movimento, quantita, note)
                 VALUES (?, ?, 'scarico', ?, ?)"
            )->execute([
                $id,
                $_SESSION['user_id'],
                $vecchio['quantita'],
                "Eliminazione prodotto «{$vecchio['nome']}» " .
                "(scarico residuo: {$vecchio['quantita']})"
            ]);
        }

        // Eliminazione
        $pdo->prepare(
            "DELETE FROM prodotti
             WHERE id = :id"
        )->execute([
            ':id' => $id
        ]);

        flash_set('success', 'Prodotto eliminato.');
    }

    else {
        throw new InvalidArgumentException(
            'Azione non riconosciuta. Sono permesse solo modifiche limitate o eliminazione.'
        );
    }

} catch (InvalidArgumentException $e) {

    flash_set('error', $e->getMessage());

} catch (PDOException $e) {

    error_log(
        'Database error in product_actions.php: ' .
        $e->getMessage()
    );

    flash_set(
        'error',
        'Errore del database durante l\'operazione.'
    );
}

header('Location: admin_products.php');
exit();