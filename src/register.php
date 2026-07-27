<?php
session_start();
require 'db.php';
require 'auth.php';
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
$errors = [];
$old = ['nome' => '', 'cognome' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $old['nome']    = trim($_POST['nome'] ?? '');
    $old['cognome'] = trim($_POST['cognome'] ?? '');
    $old['email']   = trim($_POST['email'] ?? '');
    $password       = $_POST['password'] ?? '';
    $admin_test     = isset($_POST['admin_test']);
    $tipo_utente    = $admin_test ? 'admin' : 'user';

    if ($old['nome'] === '' || $old['cognome'] === '' || $old['email'] === '' || $password === '') {
        $errors[] = 'Tutti i campi sono obbligatori.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Indirizzo email non valido.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'La password deve avere almeno 8 caratteri.';
    } else {
        try {
            $pdo = connect();
            $stmt = $pdo->prepare("SELECT id FROM utenti WHERE email = :email");
            $stmt->execute([':email' => $old['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'Email già registrata.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO utenti (nome, cognome, email, password, tipo_utente)
                     VALUES (:nome, :cognome, :email, :password, :tipo)"
                );
                $stmt->execute([
                    ':nome'     => $old['nome'],
                    ':cognome'  => $old['cognome'],
                    ':email'    => $old['email'],
                    ':password' => $hashedPassword,
                    ':tipo'     => $tipo_utente,
                ]);
                $msg = $admin_test
                    ? 'Account amministratore creato (solo test). Puoi ora effettuare il login.'
                    : 'Registrazione completata con successo. Puoi ora effettuare il login.';
                flash_set('success', $msg);
                header('Location: login.php');
                exit();
            }
        } catch (PDOException $e) {
            error_log('Database error in register.php: ' . $e->getMessage());
            $errors[] = 'Errore durante la registrazione. Riprova più tardi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrazione · Gestione Magazzino</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
<main class="auth-card">
    <div class="auth-head">
        <span class="auth-logo">📦</span>
        <h1>Crea un account</h1>
        <p class="auth-subtitle">Registrati per accedere al gestionale</p>
    </div>
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>
    <form class="auth-form" method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field-row">
            <div class="field">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" required autofocus
                       value="<?= htmlspecialchars($old['nome']) ?>">
            </div>
            <div class="field">
                <label for="cognome">Cognome</label>
                <input type="text" id="cognome" name="cognome" required
                       value="<?= htmlspecialchars($old['cognome']) ?>">
            </div>
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required
                   placeholder="nome@esempio.com"
                   value="<?= htmlspecialchars($old['email']) ?>">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8"
                   placeholder="Almeno 8 caratteri">
        </div>
        <div class="field">
            <label style="display: flex; align-items: center; gap: 8px; font-weight: normal; cursor: pointer; color: var(--text);">
                <input type="checkbox" name="admin_test" value="1" style="width: auto; margin: 0;">
                <span>Registrati come <strong>Amministratore</strong> <small style="color: var(--text-muted);">(solo per test)</small></span>
            </label>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Registrati</button>
    </form>
    <p class="auth-switch">Hai già un account? <a href="login.php">Accedi</a></p>
</main>
</body>
</html>