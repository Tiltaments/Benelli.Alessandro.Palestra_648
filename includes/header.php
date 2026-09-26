<?php
// includes/header.php - Intestazione comune per tutte le pagine del portale Palestra 648
require_once __DIR__ . '/auth.php';

$ruolo = getRuolo();
$nomeUtente = $_SESSION['nome_completo'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Palestra 648 - Portale Servizi</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        /* Barra di navigazione */
        .navbar {
            background-color: #1a1a2e;
            color: #fff;
            padding: 12px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-size: 20px;
            font-weight: bold;
            color: #e94560;
            text-decoration: none;
        }

        .navbar-nav {
            list-style: none;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .navbar-nav a {
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            padding: 6px 10px;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .navbar-nav a:hover {
            background-color: #16213e;
        }

        .navbar-nav a.btn-logout {
            background-color: #e94560;
        }

        .navbar-nav a.btn-logout:hover {
            background-color: #c82333;
        }

        /* Badge Ruolo */
        .badge-ruolo {
            background-color: #0f3460;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Contenitore principale */
        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .card {
            background: #fff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        /* Stili per il catalogo */

        .catalogo-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filtri-form select {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        /* Griglia corsi */
        .grid-offerte {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .offerta-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .offerta-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .badge-categoria {
            display: inline-block;
            background: #eef2f7;
            color: #334e68;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 3px 8px;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .badge-promo {
            background: #e63946;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }

        .badge-bonus {
            background: #2a9d8f;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }

        .prezzo-container {
            margin: 15px 0;
            font-size: 18px;
        }

        .prezzo-vecchio {
            text-decoration: line-through;
            color: #888;
            font-size: 14px;
            margin-right: 8px;
        }

        .prezzo-scontato {
            color: #e63946;
            font-weight: bold;
            font-size: 22px;
        }

        .prezzo-normale {
            color: #1a1a2e;
            font-weight: bold;
            font-size: 22px;
        }

        .btn-dettaglio {
            display: block;
            text-align: center;
            background: #007bff;
            color: white;
            text-decoration: none;
            padding: 10px;
            border-radius: 4px;
            font-weight: bold;
            transition: background 0.3s;
        }

        .btn-dettaglio:hover {
            background: #0056b3;
        }

        /* Stili per richiesta crediti */


        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
        }

        @media (max-width: 768px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .btn-submit {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }

        .btn-submit:hover {
            background-color: #218838;
        }

        .tabella-storico {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .tabella-storico th,
        .tabella-storico td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 14px;
        }

        .tabella-storico th {
            background-color: #f1f1f1;
        }

        .stato-in_attesa {
            background-color: #ffc107;
            color: #000;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
        }

        .stato-approvata {
            background-color: #28a745;
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
        }

        .stato-respinta {
            background-color: #dc3545;
            color: #fff;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
        }

        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        /* Stili per carrello */


        .cart-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .cart-container {
                grid-template-columns: 1fr;
            }
        }

        .tabella-carrello {
            width: 100%;
            border-collapse: collapse;
        }

        .tabella-carrello th,
        .tabella-carrello td {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        .tabella-carrello th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .btn-rimuovi {
            color: #dc3545;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .btn-rimuovi:hover {
            text-decoration: underline;
        }

        .riepilogo-box {
            background-color: #f4f4f9;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .riepilogo-totale {
            font-size: 24px;
            font-weight: bold;
            color: #1a1a2e;
            margin: 15px 0;
            border-top: 1px solid #ccc;
            padding-top: 15px;
        }

        .btn-checkout {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-checkout:hover {
            background-color: #0056b3;
        }

        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }


        /* Stile per profilo */


        .profilo-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .profilo-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-box {
            background-color: #f4f4f9;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }

        .stat-valore {
            font-size: 36px;
            font-weight: bold;
            color: #1a1a2e;
            margin: 10px 0;
        }

        .stat-etichetta {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            font-weight: bold;
        }

        .tabella-ordini {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .tabella-ordini th,
        .tabella-ordini td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .tabella-ordini th {
            background-color: #343a40;
            color: white;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-weight: bold;
        }


        /* Stili per gestione catalogo */


        .grid-catalogo {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 25px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .grid-catalogo {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .btn-submit {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            font-size: 16px;
        }

        .btn-submit:hover {
            background-color: #218838;
        }

        .tabella-offerte {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .tabella-offerte th,
        .tabella-offerte td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 14px;
        }

        .tabella-offerte th {
            background-color: #343a40;
            color: white;
        }

        .badge-cat {
            background-color: #17a2b8;
            color: white;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .btn-azione {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 12px;
            text-decoration: none;
            color: white;
        }

        .btn-modifica {
            background-color: #ffc107;
            color: black;
        }

        .btn-elimina {
            background-color: #dc3545;
        }

        .form-inline {
            display: inline-block;
            margin: 0;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        /* Stili per gestione sconti */


        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .btn-submit {
            background-color: #17a2b8;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            font-size: 16px;
        }

        .btn-submit:hover {
            background-color: #138496;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }


        /* Stili per moderazione */


        .tabella-moderazione {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .tabella-moderazione th,
        .tabella-moderazione td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 14px;
        }

        .tabella-moderazione th {
            background-color: #343a40;
            color: white;
        }

        .badge-tipo {
            padding: 3px 6px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            background: #17a2b8;
            color: white;
        }

        .badge-verificato {
            background: #28a745;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }

        .btn-elimina {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 12px;
        }

        .btn-elimina:hover {
            background-color: #c82333;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }


        /* Stili per admin crediti */


        .tabella-admin {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .tabella-admin th,
        .tabella-admin td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .tabella-admin th {
            background-color: #343a40;
            color: white;
        }

        .btn-approva {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-approva:hover {
            background-color: #218838;
        }

        .btn-rifiuta {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-rifiuta:hover {
            background-color: #c82333;
        }

        .form-inline {
            display: inline-block;
            margin-right: 5px;
        }

        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }


        /* Stili per admin utenti */


        .tabella-utenti {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .tabella-utenti th,
        .tabella-utenti td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
            font-size: 14px;
        }

        .tabella-utenti th {
            background-color: #343a40;
            color: white;
        }

        .badge-ruolo {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            text-transform: uppercase;
            background-color: #17a2b8;
            color: white;
        }

        .badge-admin {
            background-color: #dc3545;
        }

        .badge-gestore {
            background-color: #ffc107;
            color: black;
        }

        .stato-attivo {
            color: #28a745;
            font-weight: bold;
        }

        .stato-bannato {
            color: #dc3545;
            font-weight: bold;
            text-decoration: line-through;
        }

        .btn-azione {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            color: white;
        }

        .btn-banna {
            background-color: #dc3545;
        }

        .btn-banna:hover {
            background-color: #c82333;
        }

        .btn-sblocca {
            background-color: #28a745;
        }

        .btn-sblocca:hover {
            background-color: #218838;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .form-inline {
            display: inline-block;
        }


        /* Stili per dettaglio corso */


        .corso-dettaglio {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-top: 20px;
        }

        .corso-info {
            flex: 2;
            min-width: 300px;
        }

        .corso-sidebar {
            flex: 1;
            min-width: 250px;
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            text-align: center;
            height: fit-content;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        .badge-cat {
            background: #eef2f7;
            color: #334e68;
        }

        .badge-sconto {
            background: #e63946;
            color: white;
        }

        .prezzo-vecchio {
            text-decoration: line-through;
            color: #888;
            font-size: 18px;
            margin-right: 10px;
        }

        .prezzo-nuovo {
            color: #1a1a2e;
            font-weight: bold;
            font-size: 32px;
        }

        .prezzo-scontato {
            color: #e63946;
            font-weight: bold;
            font-size: 32px;
        }

        .btn-azione {
            display: block;
            width: 100%;
            padding: 12px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            margin-top: 20px;
            text-align: center;
            border: none;
            cursor: pointer;
        }

        .btn-acquista {
            background-color: #28a745;
            color: white;
        }

        .btn-acquista:hover {
            background-color: #218838;
        }

        .btn-login {
            background-color: #6c757d;
            color: white;
        }

        .community-box {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .post-card {
            background: #fdfdfd;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .badge-verificato {
            background: #28a745;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }


        /* Stili per admin faq */


        .grid-faq {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        @media (max-width: 768px) {
            .grid-faq {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: inherit;
        }

        .btn-submit {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
        }

        .btn-submit:hover {
            background-color: #0056b3;
        }

        .tabella-faq {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .tabella-faq th,
        .tabella-faq td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .tabella-faq th {
            background-color: #343a40;
            color: white;
        }

        .badge-prov {
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }

        .badge-manuale {
            background-color: #6c757d;
            color: white;
        }

        .badge-elevata {
            background-color: #ffc107;
            color: black;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .alert-wip {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <a href="index.php" class="navbar-brand">🏋️ Palestra 648</a>
        <ul class="navbar-nav">
            <!-- Voci comuni a tutti (Visitatore, Cliente, Gestore, Admin) -->
            <li><a href="index.php">Home / Corsi</a></li>
            <li><a href="forum_generale.php">Forum</a></li>
            <li><a href="faq.php">FAQ</a></li>

            <?php if ($ruolo === 'visitatore'): ?>
                <!-- Menu Visitatore -->
                <li><a href="login.php">Accedi</a></li>
                <li><a href="register.php">Registrati</a></li>

            <?php elseif ($ruolo === 'cliente'): ?>
                <!-- Menu Cliente -->
                <li><a href="richiesta_crediti.php">Richiedi Crediti</a></li>
                <li><a href="carrello.php">Carrello</a></li>
                <li><a href="profilo.php">Area Personale</a></li>

            <?php elseif ($ruolo === 'gestore'): ?>
                <!-- Menu Gestore -->
                <li><a href="gestione_catalogo.php">Gestione Corsi</a></li>
                <li><a href="gestione_sconti.php">Sconti & Bonus</a></li>
                <li><a href="moderazione.php">Moderazione</a></li>

            <?php elseif ($ruolo === 'admin'): ?>
                <!-- Menu Amministratore -->
                <li><a href="admin_crediti.php">Approvazione Crediti</a></li>
                <li><a href="admin_utenti.php">Gestione Utenti</a></li>
                <li><a href="admin_faq.php">Gestione FAQ</a></li>
            <?php endif; ?>

            <?php if (isLoggedIn()): ?>
                <!-- Info Sessione e Logout -->
                <li><span class="badge-ruolo"><?= htmlspecialchars($ruolo) ?></span></li>
                <li><a href="logout.php" class="btn-logout">Esci (<?= htmlspecialchars($nomeUtente) ?>)</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="container">