<?php
// admin_utenti.php - Pannello di Gestione Utenti per la Palestra 648 con moderazione, ban e gestione ruoli
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

// SICUREZZA: Solo l'amministratore può accedere a questa pagina
richiediRuolo(['admin']);

$messaggio = '';

// Identifichiamo l'Admin Supremo: è l'unico account con username 'admin',
// creato automaticamente da install.php. Ha poteri speciali rispetto agli altri admin
// (ad es. può nominare nuovi admin e modificare altri account admin)
$isSupremo = ($_SESSION['username'] === 'admin');

// 1. GESTIONE AZIONI (BAN / SBLOCCO / ELEVAZIONE RUOLO)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idDaModificare = $_POST['id_utente'] ?? null;
    $azione = $_POST['azione'] ?? '';
    $nuovoRuolo = $_POST['nuovo_ruolo'] ?? '';

    if ($idDaModificare) {
        // Recuperiamo i dati dell'utente bersaglio per i controlli di sicurezza
        $stmtTarget = $pdo->prepare("SELECT username, ruolo FROM utenti WHERE id = ?");
        $stmtTarget->execute([$idDaModificare]);
        $target = $stmtTarget->fetch();

        if ($target) {
            // Misura di sicurezza 1: Nessuno può auto-bannarsi o auto-degradarsi
            // (confrontiamo l'ID bersaglio con l'ID della sessione corrente)
            if ($idDaModificare == $_SESSION['user_id']) {
                $messaggio = "<div class='alert-danger'>Errore: Non puoi modificare lo stato o il ruolo del tuo stesso account.</div>";
            }
            // Misura di sicurezza 2: Un admin normale NON può toccare un altro admin.
            // Solo il Supremo ha questo privilegio per evitare conflitti tra amministratori
            elseif ($target['ruolo'] === 'admin' && !$isSupremo) {
                $messaggio = "<div class='alert-danger'>Accesso Negato: Solo l'Admin Supremo può modificare un altro Amministratore.</div>";
            } else {
                // A) GESTIONE BAN E SBLOCCO
                // Aggiorniamo il campo 'stato' dell'utente nel database
                if (in_array($azione, ['banna', 'sblocca'])) {
                    $nuovoStato = ($azione === 'banna') ? 'bannato' : 'attivo';
                    $updateStmt = $pdo->prepare("UPDATE utenti SET stato = ? WHERE id = ?");
                    if ($updateStmt->execute([$nuovoStato, $idDaModificare])) {
                        $messaggio = "<div class='alert-success'>Stato dell'utente <strong>{$target['username']}</strong> aggiornato a: " . strtoupper($nuovoStato) . ".</div>";
                    }
                }
                // B) GESTIONE CAMBIO RUOLO (Elevazione o Degradazione)
                elseif ($azione === 'cambia_ruolo' && in_array($nuovoRuolo, ['cliente', 'gestore', 'admin'])) {
                    // Solo il Supremo può nominare un nuovo admin
                    if ($nuovoRuolo === 'admin' && !$isSupremo) {
                        $messaggio = "<div class='alert-danger'>Accesso Negato: Solo l'Admin Supremo può nominare nuovi Amministratori.</div>";
                    } else {
                        $updateStmt = $pdo->prepare("UPDATE utenti SET ruolo = ? WHERE id = ?");
                        if ($updateStmt->execute([$nuovoRuolo, $idDaModificare])) {
                            $messaggio = "<div class='alert-success'>Ruolo dell'utente <strong>{$target['username']}</strong> aggiornato a: " . strtoupper($nuovoRuolo) . ".</div>";
                        }
                    }
                }
            }
        }
    }
}

// 2. LETTURA DI TUTTI GLI UTENTI
$stmt = $pdo->query("SELECT id, username, nome, cognome, email, ruolo, stato, data_registrazione FROM utenti ORDER BY ruolo ASC, data_registrazione DESC");
$utenti = $stmt->fetchAll();
?>

<div class="card" style="border-bottom: 4px solid #dc3545;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <div>
            <h1>🛡️ Pannello Amministratore</h1>
            <p style="color: #666; margin-top: 5px;">Modera gli account degli iscritti ed eleva i clienti a Gestori o Admin.</p>
        </div>
        <?php if ($isSupremo): ?>
            <div style="background-color: #343a40; color: #ffc107; padding: 10px 15px; border-radius: 4px; font-weight: bold; font-size: 14px;">
                👑 Sei loggato come Admin Supremo
            </div>
        <?php endif; ?>
    </div>
</div>

<?= $messaggio ?>

<div class="card">
    <h2 style="margin-bottom: 15px;">Elenco Iscritti a Sistema</h2>

    <?php if (empty($utenti)): ?>
        <p>Nessun utente trovato nel database.</p>
    <?php else: ?>
        <table class="tabella-utenti">
            <thead>
                <tr>
                    <th>Utente</th>
                    <th>Email & Anagrafica</th>
                    <th>Stato Account</th>
                    <th>Ruolo Attuale</th>
                    <th style="width: 250px;">Azioni (Gestione Ruolo e Moderazione)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utenti as $u): ?>
                    <?php
                    // Un admin normale non può agire visivamente su un altro admin (nascondiamo i bottoni)
                    $puoModificare = true;
                    if ($u['id'] == $_SESSION['user_id']) $puoModificare = false;
                    if ($u['ruolo'] === 'admin' && !$isSupremo) $puoModificare = false;
                    ?>

                    <tr style="<?= $u['stato'] === 'bannato' ? 'background-color: #f8d7da; opacity: 0.8;' : '' ?>">
                        <td>
                            <strong><?= htmlspecialchars($u['username']) ?></strong><br>
                            <span style="font-size: 11px; color: #666;">ID: <?= htmlspecialchars($u['id']) ?></span>
                        </td>
                        <td>
                            <?= htmlspecialchars($u['nome'] . ' ' . $u['cognome']) ?><br>
                            <span style="font-size: 12px; color: #007bff;"><?= htmlspecialchars($u['email']) ?></span>
                        </td>
                        <td class="<?= 'stato-' . htmlspecialchars($u['stato']) ?>">
                            <?= strtoupper(htmlspecialchars($u['stato'])) ?>
                        </td>
                        <td>
                            <span class="badge-ruolo <?= 'badge-' . htmlspecialchars($u['ruolo']) ?>">
                                <?= htmlspecialchars($u['ruolo']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($puoModificare): ?>
                                <!-- Form per Cambio Ruolo -->
                                <form method="POST" action="admin_utenti.php" style="margin-bottom: 5px; display: flex; gap: 5px;">
                                    <input type="hidden" name="id_utente" value="<?= htmlspecialchars($u['id']) ?>">
                                    <input type="hidden" name="azione" value="cambia_ruolo">
                                    <select name="nuovo_ruolo" style="padding: 4px; border-radius: 3px; border: 1px solid #ccc; font-size: 12px;" required>
                                        <option value="" disabled selected>Cambia in...</option>
                                        <?php if ($u['ruolo'] !== 'cliente'): ?><option value="cliente">Cliente</option><?php endif; ?>
                                        <?php if ($u['ruolo'] !== 'gestore'): ?><option value="gestore">Gestore</option><?php endif; ?>
                                        <?php if ($u['ruolo'] !== 'admin' && $isSupremo): ?><option value="admin">Admin</option><?php endif; ?>
                                    </select>
                                    <button type="submit" class="btn-azione" style="background-color: #17a2b8; padding: 4px 8px;">Applica</button>
                                </form>

                                <!-- Form per Ban/Sblocco -->
                                <form method="POST" action="admin_utenti.php" class="form-inline">
                                    <input type="hidden" name="id_utente" value="<?= htmlspecialchars($u['id']) ?>">
                                    <?php if ($u['stato'] === 'attivo'): ?>
                                        <input type="hidden" name="azione" value="banna">
                                        <button type="submit" class="btn-azione btn-banna" style="width: 100%; padding: 4px;" onclick="return confirm('Sei sicuro di voler BANNARE l\'utente <?= htmlspecialchars($u['username']) ?>?');">🚫 Banna Utente</button>
                                    <?php else: ?>
                                        <input type="hidden" name="azione" value="sblocca">
                                        <button type="submit" class="btn-azione btn-sblocca" style="width: 100%; padding: 4px;">✅ Sblocca Utente</button>
                                    <?php endif; ?>
                                </form>
                            <?php elseif ($u['id'] == $_SESSION['user_id']): ?>
                                <span style="color: #28a745; font-size: 12px; font-weight: bold;">(Tuo Account)</span>
                            <?php else: ?>
                                <span style="color: #dc3545; font-size: 12px; font-weight: bold;">(Intoccabile)</span>
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