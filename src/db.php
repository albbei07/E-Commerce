<?php
/**
 * Connessione al database.
 * Le credenziali arrivano da variabili d'ambiente (impostate in docker-compose.yml)
 * con fallback ai valori di sviluppo, così il file non contiene segreti hardcoded.
 */
function connect(): PDO
{
    $host     = getenv('DB_HOST') ?: 'db';
    $dbname   = getenv('DB_NAME') ?: 'magazzino';
    $user     = getenv('DB_USER') ?: 'alberto';
    $password = getenv('DB_PASSWORD') ?: 'password123';

    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Se la connessione fallisce, lanciamo l'eccezione al chiamante
    // invece di fare echo qui dentro (evita di rompere pagine che
    // magari hanno già inviato header, e non espone dettagli al client).
    return new PDO($dsn, $user, $password, $options);
}
