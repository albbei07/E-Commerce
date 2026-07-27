<?php
/**
 * Funzioni condivise per sessione, autenticazione, CSRF e Sicurezza.
 * Va incluso DOPO session_start().
 */

// --- RATE LIMITING (File-based, no dipendenze esterne) ---
function check_rate_limit(string $key, int $max_attempts = 5, int $window_seconds = 600): bool {
    // Usa la cartella temp di sistema per memorizzare i tentativi
    $file = sys_get_temp_dir() . '/rate_' . md5($key);
    $now = time();
    
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([$now => 1]));
        return true;
    }
    
    $data = json_decode(file_get_contents($file), true) ?: [];
    
    // Pulisce i tentativi scaduti oltre la finestra temporale
    $data = array_filter($data, fn($ts) => ($now - $ts) < $window_seconds, ARRAY_FILTER_USE_KEY);
    
    // Se ha superato il numero massimo di tentativi, blocca
    if (array_sum($data) >= $max_attempts) {
        return false; 
    }
    
    // Registra il nuovo tentativo
    $data[$now] = ($data[$now] ?? 0) + 1;
    file_put_contents($file, json_encode($data));
    return true;
}

// --- LOG SICUREZZA ---
function log_security_event(string $event, string $details = ''): void {
    $log = date('Y-m-d H:i:s') . " | {$_SERVER['REMOTE_ADDR']} | {$event} | {$details}\n";
    file_put_contents(sys_get_temp_dir() . '/security.log', $log, FILE_APPEND);
}

// --- AUTENTICAZIONE ---
function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

function require_admin(): void {
    require_login();
    if (($_SESSION['tipo_utente'] ?? '') !== 'admin') {
        http_response_code(403);
        echo "Accesso negato: sezione riservata agli amministratori.";
        exit();
    }
}

function is_admin(): bool {
    return ($_SESSION['tipo_utente'] ?? '') === 'admin';
}

// --- CSRF ---
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Richiesta non valida (CSRF token mancante o errato). Torna indietro e riprova.');
    }
}

// --- FLASH MESSAGES ---
function flash_set(string $key, string $message): void {
    $_SESSION[$key] = $message;
}


function flash_get(string $key): ?string {
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}