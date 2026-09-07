# Benelli.Alessandro.Palestra_648

# 🏋️ Palestra 648 - Portale Servizi e-Commerce & Community Fitness

Progetto di esame per il corso di **Linguaggi per il Web**. L'applicazione è un sistema informativo ed e-commerce orientato alla gestione di abbonamenti, corsi sportivi, lezioni con personal trainer e servizi per il benessere fisico.

---

## 🚀 Caratteristiche Principali
- **Architettura ibrida (Relazionale + XML):** 
  - **MySQL:** Gestione anagrafiche, ruoli e autenticazione sicura con password hashate (BCRYPT).
  - **XML + DTD:** Gestione della logica di business dinamica (Catalogo offerte, transazioni/portafoglio crediti, post della community e archivio FAQ).
- **Controllo Accessi basato su Ruoli (RBAC):** Visitatore, Cliente, Gestore e Amministratore.
- **Sistema di Crediti Palestra:** Ricariche con approvazione amministrativa (1 Credito = 1 Euro) e checkout atomico dal carrello.
- **Algoritmo di Reputazione Dinamica:** Calcolo ponderato basato su acquisti effettivi, voti del gestore (peso 3x) e reputazione dei votanti.
- **Moderazione e FAQ Ufficiali:** Inserimento manuale ed "elevazione" dei post della community a FAQ ufficiali.

---

## 🛠️ Tecnologie Utilizzate
- **Linguaggio Backend:** PHP (>= 8.0)
- **Database Relazionale:** MySQL (PDO con Prepared Statements)
- **Linguaggi Markup/Strutturati:** HTML5, CSS3, XML, DTD, XPath
- **Librerie PHP native:** `DOMDocument`, `DOMXPath`, `PDO`

---

## ⚙️ Guida all'Installazione (Locale con XAMPP)

1. Clona o scarica il repository nella cartella `htdocs` di XAMPP (`C:/xampp/htdocs/palestra648`).
2. Avvia **Apache** e **MySQL** dal pannello di controllo di XAMPP.
3. Importa il database eseguendo lo script SQL presente nel file `schema.sql` (puoi usare phpMyAdmin all'indirizzo `http://localhost/phpmyadmin/`).
4. Assicurati che i file XML all'interno della cartella `/data/` (`catalogo.xml`, `transazioni.xml`, `community.xml`, `faq.xml`) abbiano i permessi di scrittura attivi.
5. Apri il browser all'indirizzo: `http://localhost/palestra648/index.php`

---

## 🔑 Account di Test pre-configurati
Puoi accedere subito utilizzando le seguenti utenze (Password per tutti: `Password123!`):

* **Amministratore (Admin):** `admin` (Gestione utenti, ban, approvazione crediti)
* **Gestore:** `gestore` (Gestione catalogo, sconti, moderazione community, FAQ)
* **Cliente:** `cliente1` (Acquisti, carrello, recensioni, richieste crediti)
