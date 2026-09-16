# Benelli.Alessandro.Palestra_648

# 🏋️ Palestra 648 - Portale Servizi e-Commerce & Community Fitness

Progetto di esame per il corso di **Linguaggi per il Web**.

**Nome dell'autore e componenti:**
- Alessandro Benelli (1983399)

**Indirizzo repository github:**
- https://github.com/Tiltaments/Benelli.Alessandro.Palestra_648.git

**Descrizione dell'applicazione:** L'applicazione è un sistema informativo ed e-commerce orientato alla gestione di abbonamenti, corsi sportivi, lezioni con personal trainer e servizi per il benessere fisico.

---

## 🚀 Caratteristiche Principali
- **Architettura ibrida (Relazionale + XML):** 
  - **MySQL:** Gestione anagrafiche, ruoli e autenticazione sicura con password hashate (BCRYPT).
  - **XML + DTD:** Gestione della logica di business dinamica (Catalogo offerte, transazioni/portafoglio crediti, post della community e archivio FAQ).
- **Controllo Accessi basato su Ruoli (RBAC):** Visitatore, Cliente, Gestore e Amministratore.
- **Sistema di Crediti Palestra:** Ricariche con approvazione amministrativa (1 Credito = 1 Euro convenzionalmente ) e checkout atomico dal carrello.
- **Algoritmo di Reputazione Dinamica:** Calcolo ponderato basato su acquisti effettivi, voti del gestore (peso 3x) e reputazione dei votanti.
- **Moderazione e FAQ Ufficiali:** Inserimento manuale ed "elevazione" dei post della community a FAQ ufficiali.

---

## 🧠 Principi e Soluzioni Architetturali
Lo sviluppo ha richiesto di superare sfide tecniche specifiche, affrontate con le seguenti soluzioni:
- **Architettura Ibrida Sicura:** MySQL gestisce unicamente l'anagrafica e l'autenticazione tramite PDO (disabilitando l'emulazione nativa per prevenire SQL Injection). Tutte le password sono criptate con algoritmo BCRYPT.
- **Motore XML con DOM e XPath:** I file XML fungono da database applicativo. Tramite `DOMDocument` si alterano i nodi (`createElement`), mentre `DOMXPath` esegue query mirate per abbattere i cicli di ricerca (es. isolando nodi condizionali tramite attributi).
- **Reputazione "On-the-fly":** Per evitare desincronizzazioni, il punteggio utente non è storicizzato. Viene calcolato in tempo reale iterando i voti nell'XML e applicando moltiplicatori matematici incrociati.
- **Atomicità del Checkout:** Il carrello memorizza i corsi in sessione PHP. Al pagamento, il sistema scala i crediti e crea il nodo XML dell'ordine contemporaneamente, garantendo la coerenza strutturale dei dati.

---

## 📁 Organizzazione del Progetto
Il codice segue un pattern procedurale modulare, suddiviso per responsabilità:
- **Core e Sicurezza:** I file di configurazione gestiscono le connessioni al database e il Role-Based Access Control (RBAC) verificando le sessioni attive.
- **Servizi XML:** I moduli di servizio elaborano le logiche di business (come gli sconti calcolati in base ai timestamp odierni) per restituire array puliti al front-end.
- **Operatività Backend:** I pannelli gestionali permettono la manipolazione complessa dell'albero XML, implementando funzioni come l'"Elevazione" (trasferimento logico di nodi testuali da un file all'altro).

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
