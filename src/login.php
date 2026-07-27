<?php
session_start();
require 'db.php';
require 'auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$errors = [];
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    
    // Rate limiting su IP + Email combinati
    $ip = $_SERVER['REMOTE_ADDR'];
    $email = trim($_POST['email'] ?? '');
    if (!check_rate_limit("login_{$ip}_{$email}", 5, 600)) {
        $errors[] = 'Troppi tentativi. Riprova tra 10 minuti.';
        log_security_event('RATE_LIMIT_LOGIN', "IP: $ip, Email: $email");
    } else {
        $password = $_POST['password'] ?? '';
        if ($email === '' || $password === '') {
            $errors[] = 'Inserisci email e password.';
        } else {
            try {
                $pdo = connect();
                $stmt = $pdo->prepare("SELECT * FROM utenti WHERE email = :email");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id']     = $user['id'];
                    $_SESSION['username']    = $user['nome'];
                    $_SESSION['tipo_utente'] = $user['tipo_utente'];
                    
                    $upd = $pdo->prepare("UPDATE utenti SET ultimo_accesso = NOW() WHERE id = :id");
                    $upd->execute([':id' => $user['id']]);
                    
                    log_security_event('LOGIN_SUCCESS', "User ID: {$user['id']}");
                    header('Location: index.php');
                    exit();
                }
                
                $errors[] = 'Email o password non validi.';
                log_security_event('LOGIN_FAILED', "Email: $email");
            } catch (PDOException $e) {
                error_log('Database error in login.php: ' . $e->getMessage());
                $errors[] = 'Errore di connessione al database.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · Gestione Magazzino</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <div class="auth-head">
            <span class="auth-logo">📦</span>
            <h1>Bentornato</h1>
            <p class="auth-subtitle">Accedi al gestionale di magazzino</p>
        </div>

        <?php if ($msg = flash_get('success')): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <form class="auth-form" method="post" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autofocus
                       placeholder="nome@esempio.com"
                       value="<?= htmlspecialchars($old_email) ?>">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Accedi</button>
        </form>

        <p class="auth-switch">Non hai un account? <a href="register.php">Registrati</a></p>
    </main>
</body>
</html>
