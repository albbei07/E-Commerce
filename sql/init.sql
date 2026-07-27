-- =====================================================================
-- SCRIPT DI INIZIALIZZAZIONE COMPLETO DATABASE MAGAZZINO (CORRETTO)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS magazzino;
USE magazzino;

-- Rimuovo TUTTE le tabelle in ordine inverso di dipendenza
DROP TABLE IF EXISTS log_movimenti;
DROP TABLE IF EXISTS recensioni;
DROP TABLE IF EXISTS preferiti;
DROP TABLE IF EXISTS ordine_dettagli;
DROP TABLE IF EXISTS ordini;
DROP TABLE IF EXISTS ordine_acquisto_dettagli;
DROP TABLE IF EXISTS ordini_acquisto;
DROP TABLE IF EXISTS budget_aziendale;
DROP TABLE IF EXISTS fornitori;
DROP TABLE IF EXISTS prodotti_esterni; -- AGGIUNTO: mancava nel tuo script originale
DROP TABLE IF EXISTS prodotti;
DROP TABLE IF EXISTS utenti;

-- =====================================================================
-- 1. TABELLE BASE UTENTI E PRODOTTI
-- =====================================================================

CREATE TABLE IF NOT EXISTS utenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cognome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    tipo_utente ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    data_registrazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_accesso TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS prodotti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descrizione TEXT NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    quantita INT NOT NULL,
    prezzo DECIMAL(10,2) NOT NULL,
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================================
-- 2. TABELLE FUNZIONALITÀ UTENTE (Ordini, Preferiti, Recensioni, Log)
-- =====================================================================

CREATE TABLE IF NOT EXISTS ordini (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utente_id INT NOT NULL,
    data DATETIME DEFAULT CURRENT_TIMESTAMP,
    stato ENUM('in_attesa', 'completato', 'annullato') DEFAULT 'in_attesa',
    totale DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ordine_dettagli (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordine_id INT NOT NULL,
    prodotto_id INT NOT NULL,
    quantita INT NOT NULL,
    prezzo_unitario DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (ordine_id) REFERENCES ordini(id) ON DELETE CASCADE,
    FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS preferiti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utente_id INT NOT NULL,
    prodotto_id INT NOT NULL,
    data DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_preferito (utente_id, prodotto_id),
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS recensioni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utente_id INT NOT NULL,
    prodotto_id INT NOT NULL,
    voto TINYINT NOT NULL CHECK (voto BETWEEN 1 AND 5),
    commento TEXT,
    data DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_recensione (utente_id, prodotto_id),
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE CASCADE,
    FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS log_movimenti (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prodotto_id INT NOT NULL,
    utente_id INT,
    tipo_movimento ENUM('carico', 'scarico', 'modifica') NOT NULL,
    quantita INT NOT NULL,
    data DATETIME DEFAULT CURRENT_TIMESTAMP,
    note VARCHAR(255),
    FOREIGN KEY (prodotto_id) REFERENCES prodotti(id) ON DELETE CASCADE,
    FOREIGN KEY (utente_id) REFERENCES utenti(id) ON DELETE SET NULL
);

-- =====================================================================
-- 3. INSERIMENTO DATI DI BASE (Utenti e Prodotti Magazzino)
-- =====================================================================

INSERT INTO utenti (nome, cognome, email, password, tipo_utente, data_registrazione, ultimo_accesso) VALUES
('Mario', 'Rossi', 'mario.rossi@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-01-10 10:00:00', '2026-06-20 14:30:00'),
('Giulia', 'Bianchi', 'giulia.bianchi@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-01-15 11:30:00', '2026-06-22 09:15:00'),
('Luca', 'Verdi', 'luca.verdi@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '2026-02-01 14:20:00', '2026-06-18 18:45:00'),
('Francesca', 'Neri', 'francesca.neri@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '2026-02-20 08:10:00', '2026-06-21 11:00:00'),
('Alessandro', 'Gialli', 'alessandro.gialli@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '2026-03-05 16:50:00', NULL);

INSERT INTO prodotti (nome, descrizione, categoria, quantita, prezzo) VALUES
('Smartphone Alpha X', 'Smartphone di ultima generazione con schermo OLED da 6.5 pollici e tripla fotocamera.', 'Elettronica', 25, 799.99),
('Laptop Pro 14', 'Computer portatile con processore octa-core, 16GB RAM e SSD da 512GB.', 'Informatica', 12, 1249.50),
('Cuffie Wireless Noise-Cancelling', 'Cuffie over-ear con cancellazione attiva del rumore e 30 ore di autonomia.', 'Audio', 40, 199.00),
('Smartwatch FitActive', 'Orologio fitness con monitoraggio cardiaco, GPS integrato e resistenza all\'acqua.', 'Elettronica', 30, 129.90),
('Tastiera Meccanica RGB', 'Tastiera da gaming con switch meccanici retroilluminati e poggiapolsi.', 'Gaming', 50, 89.99),
('Mouse Ergonomico Wireless', 'Mouse ottico ad alta precisione progettato per il comfort prolungato.', 'Informatica', 60, 45.00),
('Monitor 27 pollici 4K', 'Monitor IPS con risoluzione Ultra HD, HDR e frequenza di aggiornamento a 60Hz.', 'Informatica', 15, 349.00),
('Webcam Full HD 1080p', 'Webcam con microfono integrato per videoconferenze e streaming nitidi.', 'Informatica', 35, 59.90),
('Hard Disk Esterno 2TB', 'Disco rigido portatile USB 3.0 per il backup sicuro dei tuoi dati.', 'Informatica', 45, 79.99),
('Chiavetta USB 128GB', 'Memoria flash USB 3.1 ad alta velocità di trasferimento.', 'Informatica', 100, 19.99),
('Caricatore Wireless Rapido', 'Base di ricarica senza fili compatibile con tutti i dispositivi Qi.', 'Accessori', 80, 29.99),
('Power Bank 20000mAh', 'Batteria portatile con doppia porta USB e ricarica rapida.', 'Accessori', 55, 39.99),
('Zaino per Laptop', 'Zaino antifurto impermeabile con scomparto dedicato per computer fino a 15.6 pollici.', 'Accessori', 40, 49.99),
('Sedia Gaming Ergonomica', 'Sedia da ufficio e gaming regolabile con supporto lombare.', 'Arredamento', 10, 189.90),
('Lampada LED da Scrivania', 'Lampada da tavolo con intensità e temperatura di colore regolabili e porta USB.', 'Arredamento', 25, 34.90),
('Altoparlante Bluetooth Waterproof', 'Cassa portatile resistente all\'acqua con suono stereo a 360 gradi.', 'Audio', 50, 69.99),
('Microfono a Condensatore USB', 'Microfono professionale per podcast, streaming e registrazione vocale.', 'Audio', 20, 99.00),
('Router Wi-Fi 6 Mesh', 'Sistema Wi-Fi avanzato per coprire ogni angolo della casa senza interruzioni.', 'Networking', 15, 149.99),
('Switch di Rete 8 Porte', 'Switch Gigabit Ethernet metal-case plug-and-play.', 'Networking', 30, 32.50),
('Cavo HDMI 2.1 2M', 'Cavo ad alta velocità 8K a 60Hz per console e TV.', 'Accessori', 120, 14.99),
('Adattatore Hub USB-C', 'Hub multiporta con HDMI, USB 3.0, lettore SD e pass-through Power Delivery.', 'Accessori', 70, 39.00),
('Tablet Ultra 11', 'Tablet con display Retina, 128GB di memoria e supporto penna digitale.', 'Elettronica', 18, 499.00),
('Custodia per Tablet', 'Custodia protettiva in pelle sintetica con funzione stand.', 'Accessori', 60, 21.99),
('Pennino per Touchscreen', 'Stilo di precisione ricaricabile per scrittura e disegno su tablet.', 'Accessori', 40, 29.99),
('Scanner di Documenti', 'Scanner portatile duplex a colori per documenti e foto.', 'Informatica', 8, 159.00),
('Stampante Multifunzione Laser', 'Stampante monocromatica wi-fi con scanner e fotocopiatrice.', 'Informatica', 10, 179.90),
('Cartuccia Toner Nero', 'Toner originale ad alta resa per stampanti laser.', 'Consumabili', 50, 55.00),
('Risma Carta A4', 'Risma di carta per fotocopie e stampa 80g/m2 (500 fogli).', 'Consumabili', 200, 5.50),
('Kit Cacciaviti di Precisione', 'Set di 115 utensili per la riparazione di smartphone, laptop ed elettronica.', 'Fai da te', 35, 24.99),
('Tappetino Mouse XL', 'Tappetino da gaming esteso con superficie antiscivolo.', 'Gaming', 90, 18.50),
('Controller Bluetooth per Gaming', 'Joypad ergonomico compatibile con PC, console e dispositivi mobili.', 'Gaming', 45, 49.99),
('Supporto per Laptop', 'Stand in alluminio pieghevole e regolabile in altezza per computer portatili.', 'Accessori', 50, 27.99),
('Cuffie In-Ear Sport', 'Auricolari sportivi Bluetooth con archetti auricolari flessibili.', 'Audio', 60, 35.00),
('Lettore e-Reader', 'Dispositivo per la lettura di e-book con schermo antiriflesso e luce integrata.', 'Elettronica', 22, 119.99),
('Custodia per e-Reader', 'Cover sottile con chiusura magnetica per e-reader.', 'Accessori', 25, 15.99),
('Proiettore Portatile LED', 'Mini proiettore multimediale HD per home cinema e presentazioni.', 'Elettronica', 12, 219.00),
('Telone per Proiettore', 'Schermo di proiezione 100 pollici 16:9 portatile e richiudibile.', 'Accessori', 15, 65.00),
('Hub USB 3.0 Alimentato', 'Multipresa USB a 7 porte con interruttori individuali e alimentatore.', 'Accessori', 30, 28.50),
('Scheda MicroSD 256GB', 'Scheda di memoria UHS-I con adattatore SD incluso per smartphone e action cam.', 'Informatica', 75, 32.99),
('Action Cam 4K', 'Videocamera sportiva subacquea con custodia e accessori inclusi.', 'Elettronica', 18, 139.99);

-- Dati di esempio funzionalità utente
INSERT INTO ordini (id, utente_id, stato, totale) VALUES (1, 3, 'completato', 889.98);
INSERT INTO ordine_dettagli (ordine_id, prodotto_id, quantita, prezzo_unitario) VALUES (1, 1, 1, 799.99), (1, 5, 1, 89.99);
INSERT INTO preferiti (utente_id, prodotto_id) VALUES (4, 3);
INSERT INTO recensioni (utente_id, prodotto_id, voto, commento) VALUES (3, 1, 5, 'Ottimo smartphone, batteria duratura e fotocamera fantastica!');
INSERT INTO log_movimenti (prodotto_id, utente_id, tipo_movimento, quantita, note) VALUES 
(1, 1, 'carico', 25, 'Carico iniziale magazzino'),
(5, 2, 'modifica', 0, 'Aggiornamento prezzo e descrizione prodotto'),
(1, 3, 'scarico', 1, 'Ordine #1 completato');

-- =====================================================================
-- 4. SISTEMA ACQUISTI ADMIN (Fornitori, Budget, Ordini Acquisto)
-- =====================================================================

CREATE TABLE IF NOT EXISTS fornitori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_azienda VARCHAR(150) NOT NULL,
    contatto_email VARCHAR(100),
    telefono VARCHAR(20),
    indirizzo TEXT,
    note VARCHAR(255),
    data_registrazione TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS budget_aziendale (
    id INT AUTO_INCREMENT PRIMARY KEY,
    anno INT NOT NULL UNIQUE,
    importo_totale DECIMAL(12,2) NOT NULL DEFAULT 0,
    importo_speso DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS ordini_acquisto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fornitore_id INT NOT NULL,
    data_ordine DATETIME DEFAULT CURRENT_TIMESTAMP,
    stato ENUM('in_attesa', 'ordinato', 'spedito', 'ricevuto', 'annullato') DEFAULT 'in_attesa',
    costo_totale DECIMAL(10,2) NOT NULL DEFAULT 0,
    note_admin TEXT,
    FOREIGN KEY (fornitore_id) REFERENCES fornitori(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS ordine_acquisto_dettagli (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ordine_acquisto_id INT NOT NULL,
    nome_prodotto VARCHAR(150) NOT NULL,
    quantita INT NOT NULL,
    prezzo_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (ordine_acquisto_id) REFERENCES ordini_acquisto(id) ON DELETE CASCADE
);

-- ⚠️ CORREZIONE CRITICA: La tabella prodotti_esterni DEVE essere creata PRIMA degli INSERT
-- perché ha una FK verso fornitori. Nel tuo script originale era alla fine.
CREATE TABLE IF NOT EXISTS prodotti_esterni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fornitore_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    descrizione TEXT,
    categoria VARCHAR(50) NOT NULL,
    prezzo_acquisto DECIMAL(10,2) NOT NULL,
    prezzo_suggerito_vendita DECIMAL(10,2) NOT NULL,
    quantita_minima_ordine INT NOT NULL DEFAULT 1,
    tempo_consegna_giorni INT NOT NULL DEFAULT 7,
    FOREIGN KEY (fornitore_id) REFERENCES fornitori(id) ON DELETE RESTRICT
);

-- Dati esempio sistema acquisti
INSERT INTO budget_aziendale (anno, importo_totale, importo_speso) VALUES
(2024, 40000.00, 38500.00),
(2025, 45000.00, 42100.00),
(2026, 50000.00, 12450.00);

INSERT INTO fornitori (nome_azienda, contatto_email, telefono, indirizzo, note) VALUES
('TechSupply S.r.l.', 'info@techsupply.it', '+39 02 1234567', 'Via Milano 10, 20121 MI', 'Fornitore principale elettronica'),
('Global Electronics', 'sales@globalelec.com', '+39 06 9876543', 'Viale Roma 45, 00185 RM', 'Importatore diretto Asia'),
('Office & More', 'ordini@officeandmore.it', '+39 055 1112223', 'Via Firenze 8, 50123 FI', 'Cancelleria e arredamento ufficio'),
('GreenEnergy Srl', 'commerciale@greenenergy.it', '+39 011 3344556', 'Corso Torino 22, 10121 TO', 'Pannelli solari e batterie'),
('AudioPro Distribution', 'b2b@audiopro.eu', '+39 051 7788990', 'Via Bologna 15, 40126 BO', 'Distributore autorizzato cuffie/mic'),
('GamingGear Italia', 'wholesale@gaminggear.it', '+39 081 2233445', 'Via Napoli 33, 80133 NA', 'Periferiche gaming bulk'),
('NetWork Solutions', 'support@networksol.it', '+39 040 6677889', 'Via Trieste 12, 34121 TS', 'Router, switch e cablaggio'),
('PrintMaster SpA', 'vendite@printmaster.it', '+39 049 5566778', 'Via Padova 9, 35122 PD', 'Stampanti laser e toner originali'),
('SmartHome Hub', 'orders@smarthomehub.com', '+39 02 9988776', 'Via Monza 5, 20900 MB', 'Domotica e sensori IoT'),
('EcoPackaging Srl', 'info@ecopack.it', '+39 059 4433221', 'Via Modena 18, 41121 MO', 'Imballaggi sostenibili e nastri');

INSERT INTO ordini_acquisto (id, fornitore_id, data_ordine, stato, costo_totale, note_admin) VALUES
(1, 1, '2026-01-15 09:30:00', 'ricevuto', 3200.00, 'Rifornimento iniziale Q1'),
(2, 5, '2026-03-10 14:15:00', 'spedito', 2970.00, 'Urgente per evento aziendale'),
(3, 6, '2026-05-20 11:00:00', 'in_attesa', 2249.75, 'Pre-ordine estivo gaming'),
(4, 3, '2026-06-01 16:45:00', 'annullato', 1500.00, 'Annullato: fornitore fuori stock'),
(5, 7, '2026-07-05 08:20:00', 'ordinato', 2530.25, 'Upgrade rete ufficio piano 2');

INSERT INTO ordine_acquisto_dettagli (ordine_acquisto_id, nome_prodotto, quantita, prezzo_unitario) VALUES
(1, 'Smartphone Alpha X', 4, 799.99),
(2, 'Cuffie Wireless Noise-Cancelling', 10, 199.00),
(2, 'Microfono a Condensatore USB', 5, 99.00),
(2, 'Altoparlante Bluetooth Waterproof', 10, 69.99),
(3, 'Tastiera Meccanica RGB', 15, 89.99),
(3, 'Mouse Ergonomico Wireless', 20, 45.00),
(4, 'Sedia Gaming Ergonomica', 5, 189.90),
(4, 'Lampada LED da Scrivania', 10, 34.90),
(4, 'Supporto per Laptop', 10, 27.99),
(5, 'Router Wi-Fi 6 Mesh', 5, 149.99),
(5, 'Switch di Rete 8 Porte', 20, 32.50),
(5, 'Cavo HDMI 2.1 2M', 50, 14.99),
(5, 'Adattatore Hub USB-C', 10, 39.00);

-- Inserimento 50+ prodotti esterni (ORA LA TABELLA ESISTE GIÀ)
-- =====================================================================
-- INSERIMENTO prodotti_esterni (includono anche i nomi già presenti in prodotti)
-- =====================================================================

INSERT INTO prodotti_esterni (fornitore_id, nome, descrizione, categoria, prezzo_acquisto, prezzo_suggerito_vendita, quantita_minima_ordine, tempo_consegna_giorni) VALUES
(1, 'Smartphone Alpha X', 'Smartphone di ultima generazione con schermo OLED da 6.5 pollici e tripla fotocamera.', 'Elettronica', 450.00, 799.99, 5, 10),
(2, 'Laptop Pro 14', 'Computer portatile con processore octa-core, 16GB RAM e SSD da 512GB.', 'Informatica', 850.00, 1249.50, 3, 12),
(5, 'Cuffie Wireless Noise-Cancelling', 'Cuffie over-ear con cancellazione attiva del rumore e 30 ore di autonomia.', 'Audio', 90.00, 199.00, 10, 8),
(1, 'Smartwatch FitActive', 'Orologio fitness con monitoraggio cardiaco, GPS integrato e resistenza all''acqua.', 'Elettronica', 65.00, 129.90, 10, 9),
(6, 'Tastiera Meccanica RGB', 'Tastiera da gaming con switch meccanici retroilluminati e poggiapolsi.', 'Gaming', 55.00, 89.99, 15, 7),
(2, 'Mouse Ergonomico Wireless', 'Mouse ottico ad alta precisione progettato per il comfort prolungato.', 'Informatica', 20.00, 45.00, 20, 6),
(2, 'Monitor 27 pollici 4K', 'Monitor IPS con risoluzione Ultra HD, HDR e frequenza di aggiornamento a 60Hz.', 'Informatica', 220.00, 349.00, 5, 12),
(2, 'Webcam Full HD 1080p', 'Webcam con microfono integrato per videoconferenze e streaming nitidi.', 'Informatica', 30.00, 59.90, 15, 6),
(2, 'Hard Disk Esterno 2TB', 'Disco rigido portatile USB 3.0 per il backup sicuro dei tuoi dati.', 'Informatica', 45.00, 79.99, 10, 8),
(2, 'Chiavetta USB 128GB', 'Memoria flash USB 3.1 ad alta velocità di trasferimento.', 'Informatica', 8.00, 19.99, 30, 5),
(1, 'Caricatore Wireless Rapido', 'Base di ricarica senza fili compatibile con tutti i dispositivi Qi.', 'Accessori', 12.00, 29.99, 20, 7),
(1, 'Power Bank 20000mAh', 'Batteria portatile con doppia porta USB e ricarica rapida.', 'Accessori', 18.00, 39.99, 15, 8),
(3, 'Zaino per Laptop', 'Zaino antifurto impermeabile con scomparto dedicato per computer fino a 15.6 pollici.', 'Accessori', 25.00, 49.99, 10, 9),
(3, 'Sedia Gaming Ergonomica', 'Sedia da ufficio e gaming regolabile con supporto lombare.', 'Arredamento', 120.00, 189.90, 3, 14),
(3, 'Lampada LED da Scrivania', 'Lampada da tavolo con intensità e temperatura di colore regolabili e porta USB.', 'Arredamento', 15.00, 34.90, 10, 7),
(5, 'Altoparlante Bluetooth Waterproof', 'Cassa portatile resistente all''acqua con suono stereo a 360 gradi.', 'Audio', 35.00, 69.99, 10, 8),
(5, 'Microfono a Condensatore USB', 'Microfono professionale per podcast, streaming e registrazione vocale.', 'Audio', 50.00, 99.00, 8, 9),
(7, 'Router Wi-Fi 6 Mesh', 'Sistema Wi-Fi avanzato per coprire ogni angolo della casa senza interruzioni.', 'Networking', 90.00, 149.99, 5, 10),
(7, 'Switch di Rete 8 Porte', 'Switch Gigabit Ethernet metal-case plug-and-play.', 'Networking', 18.00, 32.50, 15, 7),
(1, 'Cavo HDMI 2.1 2M', 'Cavo ad alta velocità 8K a 60Hz per console e TV.', 'Accessori', 5.00, 14.99, 40, 5),
(2, 'Adattatore Hub USB-C', 'Hub multiporta con HDMI, USB 3.0, lettore SD e pass-through Power Delivery.', 'Accessori', 20.00, 39.00, 15, 7),
(1, 'Tablet Ultra 11', 'Tablet con display Retina, 128GB di memoria e supporto penna digitale.', 'Elettronica', 320.00, 499.00, 5, 12),
(1, 'Custodia per Tablet', 'Custodia protettiva in pelle sintetica con funzione stand.', 'Accessori', 8.00, 21.99, 20, 6),
(1, 'Pennino per Touchscreen', 'Stilo di precisione ricaricabile per scrittura e disegno su tablet.', 'Accessori', 12.00, 29.99, 15, 7),
(2, 'Scanner di Documenti', 'Scanner portatile duplex a colori per documenti e foto.', 'Informatica', 90.00, 159.00, 3, 11),
(8, 'Stampante Multifunzione Laser', 'Stampante monocromatica wi-fi con scanner e fotocopiatrice.', 'Informatica', 110.00, 179.90, 3, 10),
(8, 'Cartuccia Toner Nero', 'Toner originale ad alta resa per stampanti laser.', 'Consumabili', 35.00, 55.00, 20, 6),
(8, 'Risma Carta A4', 'Risma di carta per fotocopie e stampa 80g/m2 (500 fogli).', 'Consumabili', 3.00, 5.50, 50, 4),
(1, 'Kit Cacciaviti di Precisione', 'Set di 115 utensili per la riparazione di smartphone, laptop ed elettronica.', 'Fai da te', 10.00, 24.99, 15, 9),
(6, 'Tappetino Mouse XL', 'Tappetino da gaming esteso con superficie antiscivolo.', 'Gaming', 6.00, 18.50, 25, 6),
(6, 'Controller Bluetooth per Gaming', 'Joypad ergonomico compatibile con PC, console e dispositivi mobili.', 'Gaming', 30.00, 49.99, 10, 8),
(3, 'Supporto per Laptop', 'Stand in alluminio pieghevole e regolabile in altezza per computer portatili.', 'Accessori', 12.00, 27.99, 15, 7),
(5, 'Cuffie In-Ear Sport', 'Auricolari sportivi Bluetooth con archetti auricolari flessibili.', 'Audio', 15.00, 35.00, 20, 7),
(1, 'Lettore e-Reader', 'Dispositivo per la lettura di e-book con schermo antiriflesso e luce integrata.', 'Elettronica', 70.00, 119.99, 8, 10),
(1, 'Custodia per e-Reader', 'Cover sottile con chiusura magnetica per e-reader.', 'Accessori', 6.00, 15.99, 20, 6),
(1, 'Proiettore Portatile LED', 'Mini proiettore multimediale HD per home cinema e presentazioni.', 'Elettronica', 140.00, 219.00, 5, 12),
(1, 'Telone per Proiettore', 'Schermo di proiezione 100 pollici 16:9 portatile e richiudibile.', 'Accessori', 30.00, 65.00, 10, 9),
(2, 'Hub USB 3.0 Alimentato', 'Multipresa USB a 7 porte con interruttori individuali e alimentatore.', 'Accessori', 14.00, 28.50, 15, 7),
(2, 'Scheda MicroSD 256GB', 'Scheda di memoria UHS-I con adattatore SD incluso per smartphone e action cam.', 'Informatica', 18.00, 32.99, 25, 6),
(1, 'Action Cam 4K', 'Videocamera sportiva subacquea con custodia e accessori inclusi.', 'Elettronica', 85.00, 139.99, 5, 11),
(1, 'Drone Mini Foldable', 'Peso <250g, camera 4K, autonomia 30 min.', 'Elettronica', 180.00, 299.99, 4, 12),
(1, 'VR Headset Standalone', 'Visore realtà virtuale senza PC, tracking inside-out.', 'Elettronica', 210.00, 349.99, 3, 15),
(2, 'RAM DDR5 32GB Kit', '5600MHz CL36, compatibilità Intel/AMD.', 'Informatica', 75.00, 119.99, 15, 4),
(2, 'NAS 4 Bay Enterprise', 'CPU quad-core, 8GB ECC, RAID hardware.', 'Informatica', 380.00, 599.99, 2, 15),
(3, 'Scrivania Alzata Elettrica', 'Motore doppio, memoria 4 posizioni, cavo management.', 'Arredamento', 180.00, 289.99, 3, 10),
(9, 'Hub Domotica Zigbee/Matter', 'Gateway universale, automazioni locali.', 'Elettronica', 45.00, 74.99, 10, 6),
(10, 'Scatole Corrugate Miste', 'Assortimento 5 misure, kraft riciclato.', 'Accessori', 25.00, 39.99, 20, 4),
(4, 'Pannello Solare Portatile 100W', 'Pannello fotovoltaico pieghevole per ricarica outdoor.', 'Elettronica', 95.00, 159.99, 5, 12),
(4, 'Power Station 500Wh', 'Generatore portatile con uscite AC/DC/USB per emergenze.', 'Elettronica', 250.00, 419.99, 3, 14),
(9, 'Presa Smart Wi-Fi', 'Presa intelligente compatibile con Alexa e Google Home.', 'Elettronica', 8.00, 16.99, 30, 6),
(9, 'Videocamera di Sicurezza Wi-Fi', 'Telecamera IP indoor con visione notturna e audio bidirezionale.', 'Elettronica', 28.00, 49.99, 15, 8),
(9, 'Sensore di Movimento Zigbee', 'Sensore PIR per automazioni domotiche.', 'Elettronica', 9.00, 17.99, 25, 6),
(7, 'Cavo di Rete Cat 6 5M', 'Cavo Ethernet schermato per connessioni stabili ad alta velocità.', 'Networking', 3.50, 8.99, 50, 5),
(7, 'Ripetitore Wi-Fi Dual Band', 'Extender segnale wireless per coprire zone d''ombra.', 'Networking', 22.00, 38.99, 15, 7),
(3, 'Sedia da Ufficio Base', 'Sedia girevole imbottita con regolazione altezza a gas.', 'Arredamento', 45.00, 79.99, 8, 10),
(3, 'Scrivania in Legno 120x60', 'Scrivania compatta con gambe in metallo verniciato.', 'Arredamento', 65.00, 109.99, 5, 11),
(10, 'Nastro Adesivo da Imballaggio', 'Confezione da 6 rotoli nastro trasparente resistente.', 'Accessori', 4.00, 8.99, 40, 5),
(10, 'Pluriball Rotolo 50m', 'Pellicola a bolle protettiva per imballaggi fragili.', 'Accessori', 12.00, 21.99, 20, 6),
(6, 'Sedia Gaming con Poggiapiedi', 'Poltrona gaming reclinabile con poggiapiedi estraibile.', 'Gaming', 140.00, 229.99, 3, 13),
(6, 'Mouse Gaming Wireless RGB', 'Mouse da gaming ad alta precisione con illuminazione personalizzabile.', 'Gaming', 25.00, 44.99, 15, 7),
(8, 'Cartuccia Toner Colori Kit', 'Set 4 colori CMYK per stampanti laser a colori.', 'Consumabili', 65.00, 109.99, 10, 8),
(8, 'Etichette Adesive A4 (100 fogli)', 'Fogli etichette universali per stampanti laser e inkjet.', 'Consumabili', 6.00, 12.99, 25, 5),
(5, 'Cavo Audio Jack 3.5mm 2M', 'Cavo audio stereo per collegamento dispositivi hi-fi.', 'Audio', 2.50, 6.99, 40, 5),
(5, 'Supporto da Scrivania per Microfono', 'Braccio articolato con clip antivibrazione per microfoni USB.', 'Audio', 15.00, 27.99, 15, 8),
(2, 'Docking Station USB-C Dual Monitor', 'Base di espansione con doppia uscita video e ricarica laptop.', 'Informatica', 95.00, 159.99, 5, 10),
(2, 'SSD Esterno 1TB USB-C', 'Unità a stato solido portatile ad alta velocità di trasferimento.', 'Informatica', 60.00, 99.99, 10, 9),
(1, 'Auricolari True Wireless', 'Auricolari in-ear con custodia di ricarica e cancellazione rumore.', 'Elettronica', 40.00, 79.99, 15, 8),
(1, 'Gimbal Stabilizzatore per Smartphone', 'Stabilizzatore a 3 assi per riprese video fluide.', 'Elettronica', 55.00, 99.99, 8, 11);