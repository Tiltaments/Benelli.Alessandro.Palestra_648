<?php
// admin_utenti.php - Gestione Utenti e Moderazione (CU11)
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

// SICUREZZA: Solo l'amministratore può accedere a questa pagina
richiediRuolo(['admin']);

$messaggio = '';

// 1. GESTIONE AZIONI (BAN / SBLOCCO)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idDaModificare = $_POST['id_utente'] ?? null;
    $azione = $_POST['azione'] ?? '';

    // Misura di sicurezza extra: impediamo all'admin di bannare se stesso
    if ($idDaModificare && $idDaModificare == $_SESSION['user_id']) {
        $messaggio = "<div class='alert-danger'>Errore: Non puoi modificare lo stato del tuo stesso account amministratore.</div>";
    } elseif ($idDaModificare && in_array($azione, ['banna', 'sblocca'])) {
        $nuovoStato = ($azione === 'banna') ? 'bannato' : 'attivo';

        $updateStmt = $pdo->prepare("UPDATE utenti SET stato = ? WHERE id = ?");
        $esito = $updateStmt->execute([$nuovoStato, $idDaModificare]);

        if ($esito) {
            $messaggio = "<div class='alert-success'>Stato dell'utente aggiornato con successo a: <strong>" . strtoupper($nuovoStato) . "</strong>.</div>";
        } else {
            $messaggio = "<div class='alert-danger'>Errore durante l'aggiornamento dello stato.</div>";
        }
    }
}

// 2. LETTURA DI TUTTI GLI UTENTI
$stmt = $pdo->query("SELECT id, username, nome, cognome, email, ruolo, stato, data_registrazione FROM utenti ORDER BY data_registrazione DESC");
$utenti = $stmt->fetchAll();
?>



<div class="card">
    <h1>🛡️ Pannello Amministratore: Gestione Utenti</h1>
    <p style="color: #666;">Da questa schermata puoi visualizzare tutti gli iscritti, controllare i loro ruoli e moderare (bannare) gli account che violano le regole.</p>
</div>

<?= $messaggio ?>

<div class="card">
    <h2>Elenco Iscritti</h2>

    <?php if (empty($utenti)): ?>
        <p>Nessun utente trovato nel database.</p>
    <?php else: ?>
        <table class="tabella-utenti">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Utente</th>
                    <th>Anagrafica</th>
                    <th>Email</th>
                    <th>Ruolo</th>
                    <th>Stato</th>
                    <th>Azioni di Moderazione</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utenti as $u): ?>
                    <tr>
                        <td><code><?= htmlspecialchars($u['id']) ?></code></td>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td><?= htmlspecialchars($u['nome'] . ' ' . $u['cognome']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="badge-ruolo <?= 'badge-' . htmlspecialchars($u['ruolo']) ?>">
                                <?= htmlspecialchars($u['ruolo']) ?>
                            </span>
                        </td>
                        <td class="<?= 'stato-' . htmlspecialchars($u['stato']) ?>">
                            <?= strtoupper(htmlspecialchars($u['stato'])) ?>
                        </td>
                        <td>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" action="admin_utenti.php" class="form-inline">
                                    <input type="hidden" name="id_utente" value="<?= htmlspecialchars($u['id']) ?>">
                                    <?php if ($u['stato'] === 'attivo'): ?>
                                        <input type="hidden" name="azione" value="banna">
                                        <button type="submit" class="btn-azione btn-banna" onclick="return confirm('Sei sicuro di voler BANNARE questo utente?');">🚫 Banna</button>
                                    <?php else: ?>
                                        <input type="hidden" name="azione" value="sblocca">
                                        <button type="submit" class="btn-azione btn-sblocca">✅ Sblocca</button>
                                    <?php endif; ?>
                                </form>
                            <?php else: ?>
                                <span style="color: #888; font-size: 12px;">(Tuo Account)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</div>
</body>

</html>