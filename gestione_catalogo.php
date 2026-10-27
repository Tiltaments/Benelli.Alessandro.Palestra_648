<?php
// gestione_catalogo.php - Pannello di Gestione Catalogo Corsi per Gestori e Admin
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';

richiediRuolo(['gestore', 'admin']);
$messaggio = '';

$xmlCatalogo = __DIR__ . '/data/catalogo.xml';

// --- GESTIONE AZIONI POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['azione'])) {
        $azione = $_POST['azione'];

        // A & B) NUOVA OFFERTA e MODIFICA OFFERTA
        if ($azione === 'nuova_offerta' || $azione === 'salva_modifica') {
            $titolo       = trim($_POST['titolo'] ?? '');
            $categoria    = $_POST['categoria'] ?? 'corso';
            $descrizione  = trim($_POST['descrizione'] ?? '');
            $immagine     = trim($_POST['immagine'] ?? '');
            $intensita    = $_POST['intensita'] ?? 'media';
            $prezzo       = (int)($_POST['prezzo_crediti'] ?? 0);

            // Riceviamo gli array direttamente dai campi multipli HTML (name="trainer[]")
            $trainersList = $_POST['trainer'] ?? [];
            $giorniList   = $_POST['giorni'] ?? [];
            $orariList    = $_POST['orario'] ?? [];

            if (!empty($titolo) && $prezzo > 0) {
                $dom = new DOMDocument();
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = true;

                if (file_exists($xmlCatalogo)) {
                    $dom->load($xmlCatalogo);
                    $root = $dom->documentElement;
                } else {
                    $root = $dom->createElement('catalogo');
                    $dom->appendChild($root);
                }

                if ($azione === 'nuova_offerta') {
                    // Creazione NUOVO corso
                    $corso = $dom->createElement('corso');
                    $corso->setAttribute('id', 'CRS' . time());
                    $corso->setAttribute('categoria', $categoria);

                    $corso->appendChild($dom->createElement('titolo', htmlspecialchars($titolo)));
                    $corso->appendChild($dom->createElement('descrizione', htmlspecialchars($descrizione)));
                    $corso->appendChild($dom->createElement('immagine', htmlspecialchars($immagine)));
                    $corso->appendChild($dom->createElement('intensita', htmlspecialchars($intensita)));
                    $corso->appendChild($dom->createElement('prezzo_crediti', (string)$prezzo));
                    $corso->appendChild($dom->createElement('disponibile', 'true'));
                    $root->appendChild($corso);
                    $messaggio = "<div class='alert-success'>Corso inserito con successo!</div>";
                } else {
                    // MODIFICA corso esistente
                    $idCorso = $_POST['id_corso'] ?? '';
                    $xpath = new DOMXPath($dom);
                    $corso = $xpath->query("//corso[@id='$idCorso']")->item(0);

                    if (isset($corso)) {
                        $corso->setAttribute('categoria', $categoria);
                        $corso->getElementsByTagName('titolo')->item(0)->nodeValue = htmlspecialchars($titolo);
                        $corso->getElementsByTagName('descrizione')->item(0)->nodeValue = htmlspecialchars($descrizione);
                        $corso->getElementsByTagName('immagine')->item(0)->nodeValue = htmlspecialchars($immagine);
                        $corso->getElementsByTagName('intensita')->item(0)->nodeValue = htmlspecialchars($intensita);
                        $corso->getElementsByTagName('prezzo_crediti')->item(0)->nodeValue = (string)$prezzo;

                        // Rimuoviamo le vecchie edizioni per ricrearle aggiornate
                        $vecchieEdizioni = $corso->getElementsByTagName('edizioni')->item(0);
                        if ($vecchieEdizioni) {
                            $corso->removeChild($vecchieEdizioni);
                        }
                        $messaggio = "<div class='alert-success'>Corso modificato con successo!</div>";
                    }
                }

                if (isset($corso)) {
                    // Creazione edizioni scorrendo i 3 slot (Valido sia per nuovo che per modifica)
                    $edizioniNode = $dom->createElement('edizioni');
                    foreach ($trainersList as $index => $nomeTrainer) {
                        $nomePulito = trim($nomeTrainer);
                        // Creiamo il nodo solo se il nome del trainer per questo slot non è vuoto
                        if (!empty($nomePulito)) {
                            $giornoTurno = trim($giorniList[$index] ?? '');
                            $orarioTurno = trim($orariList[$index] ?? '');

                            $ed = $dom->createElement('edizione');
                            $ed->setAttribute('id_edizione', 'ED_' . time() . '_' . $index);
                            $ed->setAttribute('trainer', htmlspecialchars($nomePulito));
                            $ed->setAttribute('giorni', htmlspecialchars($giornoTurno));
                            $ed->setAttribute('orario', htmlspecialchars($orarioTurno));
                            $ed->setAttribute('disponibile', 'true');
                            $edizioniNode->appendChild($ed);
                        }
                    }
                    $corso->appendChild($edizioniNode);
                    $dom->save($xmlCatalogo);
                }
            } else {
                $messaggio = "<div class='alert-danger'>Compila tutti i campi obbligatori.</div>";
            }
        }
        // C) DISATTIVA (Soft Delete)
        elseif ($azione === 'disattiva') {
            $idCorso = $_POST['id_offerta'] ?? '';
            if (!empty($idCorso) && file_exists($xmlCatalogo)) {
                $dom = new DOMDocument();
                $dom->preserveWhiteSpace = false;
                if ($dom->load($xmlCatalogo)) {
                    $xpath = new DOMXPath($dom);
                    $nodi = $xpath->query("//corso[@id='$idCorso']/disponibile");
                    if ($nodi->length > 0) {
                        $nodi->item(0)->nodeValue = 'false';
                        $dom->save($xmlCatalogo);
                        $messaggio = "<div class='alert-success'>Corso archiviato!</div>";
                    }
                }
            }
        }
        // D) RIPRISTINA (Soft Restore)
        elseif ($azione === 'ripristina') {
            $idCorso = $_POST['id_offerta'] ?? '';
            if (!empty($idCorso) && file_exists($xmlCatalogo)) {
                $dom = new DOMDocument();
                $dom->preserveWhiteSpace = false;
                if ($dom->load($xmlCatalogo)) {
                    $xpath = new DOMXPath($dom);
                    $nodi = $xpath->query("//corso[@id='$idCorso']/disponibile");
                    if ($nodi->length > 0) {
                        $nodi->item(0)->nodeValue = 'true';
                        $dom->save($xmlCatalogo);
                        $messaggio = "<div class='alert-success'>Corso ripristinato!</div>";
                    }
                }
            }
        }
    }
}

// --- LETTURA DATI PER LA TABELLA ---
$corsiAttivi = [];
$corsiArchiviati = [];

if (file_exists($xmlCatalogo)) {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    if ($dom->load($xmlCatalogo)) {
        $nodi = $dom->getElementsByTagName('corso');
        foreach ($nodi as $nodo) {
            $id = $nodo->getAttribute('id');
            $titolo = $nodo->getElementsByTagName('titolo')->item(0)->nodeValue ?? 'Senza Titolo';
            $descrizione = $nodo->getElementsByTagName('descrizione')->item(0)->nodeValue ?? '';
            $immagine = $nodo->getElementsByTagName('immagine')->item(0)->nodeValue ?? '';
            $intensita = $nodo->getElementsByTagName('intensita')->item(0)->nodeValue ?? 'media';
            $prezzo = $nodo->getElementsByTagName('prezzo_crediti')->item(0)->nodeValue ?? '0';
            $categoria = $nodo->getAttribute('categoria');
            $disponibile = $nodo->getElementsByTagName('disponibile')->item(0)->nodeValue ?? 'false';

            // Leggiamo le edizioni di questo corso
            $edizioni = [];
            $nodiEd = $nodo->getElementsByTagName('edizione');
            foreach ($nodiEd as $ed) {
                $edizioni[] = [
                    'trainer' => $ed->getAttribute('trainer'),
                    'giorni'  => $ed->getAttribute('giorni'),
                    'orario'  => $ed->getAttribute('orario')
                ];
            }

            $corso = [
                'id' => $id,
                'titolo' => $titolo,
                'descrizione' => $descrizione,
                'immagine' => $immagine,
                'intensita' => $intensita,
                'prezzo_base' => $prezzo,
                'categoria' => $categoria,
                'edizioni' => $edizioni
            ];

            if ($disponibile === 'true') {
                $corsiAttivi[] = $corso;
            } else {
                $corsiArchiviati[] = $corso;
            }
        }
    }
}

// --- CARICAMENTO DATI PER LA MODIFICA ---
$datiModifica = null;
if (isset($_GET['edit'])) {
    $idEdit = $_GET['edit'];
    foreach (array_merge($corsiAttivi, $corsiArchiviati) as $c) {
        if ($c['id'] === $idEdit) {
            $datiModifica = $c;
            break;
        }
    }
}
?>

<div class="card">
    <h1>📋 Gestione Catalogo Corsi</h1>
    <p style="color: #666;">Aggiungi, modifica o disattiva i corsi. Le edizioni vuote verranno ignorate.</p>
</div>

<?= $messaggio ?>

<div class="grid-catalogo" style="display: grid; grid-template-columns: 4fr 7fr; gap: 25px; align-items: start;">
    <!-- Colonna Sinistra: Form Inserimento/Modifica -->
    <div class="card" <?= $datiModifica ? 'style="border: 2px solid #ffc107;"' : '' ?>>
        <h2 style="margin-bottom: 15px; font-size: 18px;">
            <?= $datiModifica ? '✏️ Modifica Corso' : '➕ Nuovo Corso' ?>
            <?php if ($datiModifica): ?>
                <a href="gestione_catalogo.php" style="float: right; font-size: 12px; color: red; text-decoration: none;">Annulla</a>
            <?php endif; ?>
        </h2>

        <form method="POST" action="gestione_catalogo.php">
            <input type="hidden" name="azione" value="<?= $datiModifica ? 'salva_modifica' : 'nuova_offerta' ?>">
            <?php if ($datiModifica): ?>
                <input type="hidden" name="id_corso" value="<?= $datiModifica['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Categoria</label>
                <select name="categoria" id="categoria" required>
                    <option value="corso" <?= ($datiModifica && $datiModifica['categoria'] === 'corso') ? 'selected' : '' ?>>Corso</option>
                    <option value="abbonamento" <?= ($datiModifica && $datiModifica['categoria'] === 'abbonamento') ? 'selected' : '' ?>>Abbonamento</option>
                    <option value="lezione_privata" <?= ($datiModifica && $datiModifica['categoria'] === 'lezione_privata') ? 'selected' : '' ?>>Lezione Privata</option>
                </select>
            </div>

            <div class="form-group">
                <label>Titolo Corso/Servizio</label>
                <input type="text" name="titolo" value="<?= $datiModifica ? htmlspecialchars($datiModifica['titolo']) : '' ?>" required>
            </div>

            <div class="form-group">
                <label>Descrizione</label>
                <textarea name="descrizione" rows="3" required><?= $datiModifica ? htmlspecialchars($datiModifica['descrizione']) : '' ?></textarea>
            </div>

            <div class="form-group">
                <label>Immagine Copertina (Percorso)</label>
                <input type="text" name="immagine" value="<?= $datiModifica ? htmlspecialchars($datiModifica['immagine']) : '' ?>" placeholder="Es. img/foto.jpg" required>
            </div>

            <!-- BLOCCO EDIZIONI MULTIPLE SENZA JS -->
            <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; border-radius: 4px; margin-bottom: 15px;">
                <label style="color: #007bff; font-size: 16px; margin-bottom: 10px; display:block;">Turni ed Edizioni</label>
                <p style="font-size: 12px; color: #666; margin-bottom: 15px;">Compila solo i turni necessari. Il primo è obbligatorio, gli altri opzionali.</p>

                <?php
                // Predisponiamo 3 slot
                for ($i = 0; $i < 3; $i++):
                    $t = $datiModifica['edizioni'][$i]['trainer'] ?? '';
                    $g = $datiModifica['edizioni'][$i]['giorni'] ?? '';
                    $o = $datiModifica['edizioni'][$i]['orario'] ?? '';
                ?>
                    <div style="border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 5px; background: #fff;">
                        <strong style="font-size: 13px;">Turno <?= $i + 1 ?> <?= $i == 0 ? '(Obbligatorio)' : '(Opzionale)' ?></strong>
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 8px;">
                            <input type="text" name="trainer[]" value="<?= htmlspecialchars($t) ?>" placeholder="Nome Trainer" <?= $i == 0 ? 'required' : '' ?> style="padding: 6px;">
                            <input type="text" name="giorni[]" value="<?= htmlspecialchars($g) ?>" placeholder="Giorni (es. Lun, Mer)" <?= $i == 0 ? 'required' : '' ?> style="padding: 6px;">
                            <input type="text" name="orario[]" value="<?= htmlspecialchars($o) ?>" placeholder="Orario (es. 18:00)" <?= $i == 0 ? 'required' : '' ?> style="padding: 6px;">
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label>Intensità</label>
                    <select name="intensita" id="intensita" required>
                        <option value="bassa" <?= ($datiModifica && $datiModifica['intensita'] === 'bassa') ? 'selected' : '' ?>>Bassa</option>
                        <option value="media" <?= ($datiModifica && (!isset($datiModifica['intensita']) || $datiModifica['intensita'] === 'media')) ? 'selected' : '' ?>>Media</option>
                        <option value="alta" <?= ($datiModifica && $datiModifica['intensita'] === 'alta') ? 'selected' : '' ?>>Alta</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Prezzo (Crediti)</label>
                    <input type="number" name="prezzo_crediti" min="1" value="<?= $datiModifica ? htmlspecialchars($datiModifica['prezzo_base']) : '' ?>" required>
                </div>
            </div>

            <button type="submit" class="btn-submit" <?= $datiModifica ? 'style="background-color: #ffc107; color: black;"' : '' ?>>
                <?= $datiModifica ? 'Salva Modifiche' : 'Salva nel Catalogo' ?>
            </button>
        </form>
    </div>

    <!-- Colonna Destra: Elenchi -->
    <div>
        <!-- Tabella Corsi Attivi -->
        <div class="card" style="margin-bottom: 25px;">
            <h2 style="margin-bottom: 15px; font-size: 18px; color: #28a745;">🟢 Corsi Disponibili</h2>
            <?php if (empty($corsiAttivi)): ?>
                <p style="color:#888; font-size:14px;">Nessun corso attivo presente nel catalogo.</p>
            <?php else: ?>
                <table class="tabella-offerte">
                    <thead>
                        <tr>
                            <th>Dettagli Corso & Edizioni</th>
                            <th>Prezzo</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($corsiAttivi as $corso): ?>
                            <tr>
                                <td>
                                    <span class="badge-cat"><?= htmlspecialchars(str_replace('_', ' ', $corso['categoria'])) ?></span><br>
                                    <strong style="font-size: 15px;"><?= htmlspecialchars($corso['titolo']) ?></strong>

                                    <!-- Visualizzazione Dettagliata delle Edizioni -->
                                    <?php if (!empty($corso['edizioni'])): ?>
                                        <div style="margin-top: 8px; background: #f9f9f9; padding: 8px; border-radius: 4px; border: 1px solid #eee;">
                                            <ul style="font-size: 12px; margin: 0; padding-left: 15px; color: #555;">
                                                <?php foreach ($corso['edizioni'] as $ed): ?>
                                                    <li>
                                                        <strong><?= htmlspecialchars($ed['trainer']) ?></strong>:
                                                        <?= htmlspecialchars($ed['giorni']) ?> (<?= htmlspecialchars($ed['orario']) ?>)
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: bold;"><?= htmlspecialchars($corso['prezzo_base']) ?> cr.</td>
                                <td>
                                    <a href="gestione_catalogo.php?edit=<?= $corso['id'] ?>" class="btn-azione" style="background-color: #ffc107; color: #000; display: block; margin-bottom: 5px; text-decoration: none; text-align: center;">Modifica</a>

                                    <form method="POST" action="gestione_catalogo.php" onsubmit="return confirm('Disattivare questo corso? Finirà in archivio.');">
                                        <input type="hidden" name="azione" value="disattiva">
                                        <input type="hidden" name="id_offerta" value="<?= htmlspecialchars($corso['id']) ?>">
                                        <button type="submit" class="btn-azione btn-elimina" style="background-color: #dc3545; width: 100%;">Disattiva</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Tabella Corsi Archiviati (Soft Delete) -->
        <div class="card" style="background-color: #fcfcfc; border: 1px dashed #ccc;">
            <h2 style="margin-bottom: 15px; font-size: 18px; color: #6c757d;">📁 Archivio (Non Disponibili)</h2>
            <?php if (empty($corsiArchiviati)): ?>
                <p style="color:#888; font-size:14px;">L'archivio è vuoto.</p>
            <?php else: ?>
                <table class="tabella-offerte">
                    <thead>
                        <tr>
                            <th style="color:#666;">Titolo Archiviato</th>
                            <th style="color:#666;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($corsiArchiviati as $corso): ?>
                            <tr style="opacity: 0.7;">
                                <td><strong><?= htmlspecialchars($corso['titolo']) ?></strong></td>
                                <td>
                                    <form method="POST" action="gestione_catalogo.php" class="form-inline">
                                        <input type="hidden" name="azione" value="ripristina">
                                        <input type="hidden" name="id_offerta" value="<?= htmlspecialchars($corso['id']) ?>">
                                        <button type="submit" class="btn-azione" style="background-color: #28a745;">Ripristina</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
</body>

</html>