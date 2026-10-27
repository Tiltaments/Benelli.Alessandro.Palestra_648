<?php
// includes/catalogo_service.php - Servizio per la gestione del catalogo corsi e promozioni
require_once __DIR__ . '/../config/db.php';


//  Calcola la reputazione totale di un utente basandosi sui voti ricevuti nella community

function calcolaReputazioneUtente($userId)
{
    if (empty($userId)) return 0;
    $xmlCommunity = __DIR__ . '/../data/community.xml';
    if (!file_exists($xmlCommunity)) return 0;

    $domComm = new DOMDocument();
    $domComm->preserveWhiteSpace = false;
    if (!@$domComm->load($xmlCommunity)) return 0;

    $xpathComm = new DOMXPath($domComm);
    $reputazioneTotale = 0;

    // 1. Post principali scritti dall'utente
    $mieiPost = $xpathComm->query("//contributo[id_autore='$userId']");
    if ($mieiPost !== false) {
        foreach ($mieiPost as $post) {
            if ($post instanceof DOMElement) {
                $acquistoVerificato = $post->getAttribute('acquisto_verificato') === 'true';
                $moltiplicatoreAcquisto = $acquistoVerificato ? 1.5 : 1.0;

                $valutazioni = $post->getElementsByTagName('valutazione');
                foreach ($valutazioni as $voto) {
                    if ($voto instanceof DOMElement) {
                        $supporto = (int) ($voto->getElementsByTagName('supporto')->item(0)->nodeValue ?? 0);
                        $utilita = (int) ($voto->getElementsByTagName('utilita')->item(0)->nodeValue ?? 0);
                        $ruoloVotante = $voto->getAttribute('ruolo_votante');
                        $moltiplicatoreRuolo = ($ruoloVotante === 'gestore') ? 3.0 : 1.0;
                        $reputazioneTotale += ($supporto + $utilita) * $moltiplicatoreAcquisto * $moltiplicatoreRuolo;
                    }
                }
            }
        }
    }

    // 2. Risposte nei thread scritte dall'utente
    $mieRisposte = $xpathComm->query("//risposta[id_autore='$userId']");
    if ($mieRisposte !== false) {
        foreach ($mieRisposte as $risposta) {
            if ($risposta instanceof DOMElement) {
                $nodoRisposte = $risposta->parentNode;
                $nodoContributo = $nodoRisposte ? $nodoRisposte->parentNode : null;
                $moltiplicatoreAcquisto = ($nodoContributo instanceof DOMElement && $nodoContributo->getAttribute('acquisto_verificato') === 'true') ? 1.5 : 1.0;

                $valutazioni = $risposta->getElementsByTagName('valutazione');
                foreach ($valutazioni as $voto) {
                    if ($voto instanceof DOMElement) {
                        $supporto = (int) ($voto->getElementsByTagName('supporto')->item(0)->nodeValue ?? 0);
                        $utilita = (int) ($voto->getElementsByTagName('utilita')->item(0)->nodeValue ?? 0);
                        $ruoloVotante = $voto->getAttribute('ruolo_votante');
                        $moltiplicatoreRuolo = ($ruoloVotante === 'gestore') ? 3.0 : 1.0;
                        $reputazioneTotale += ($supporto + $utilita) * $moltiplicatoreAcquisto * $moltiplicatoreRuolo;
                    }
                }
            }
        }
    }

    return (int)round($reputazioneTotale);
}

// Verifica se un cliente soddisfa i requisiti del criterio promozionale

function utenteSoddisfaCriterioPromo($tipoCriterio, $soglia, $userId = null)
{
    // Il criterio indiscriminato è valido per tutti (anche visitatori)
    if ($tipoCriterio === 'indiscriminato' || empty($tipoCriterio)) {
        return true;
    }

    // Per i criteri specifici è richiesto l'accesso
    if (empty($userId)) {
        return false;
    }

    global $pdo;

    // 1. Criterio Anzianità (mesi di registrazione sul database MySQL)
    if ($tipoCriterio === 'anzianita') {
        if (!isset($pdo)) {
            require_once __DIR__ . '/../config/db.php';
        }
        $stmt = $pdo->prepare("SELECT data_registrazione FROM utenti WHERE id = ?");
        $stmt->execute([$userId]);
        $dataRegStr = $stmt->fetchColumn();

        if (!$dataRegStr) return false;

        $dataReg = new DateTime($dataRegStr);
        $oggi = new DateTime();
        $diff = $dataReg->diff($oggi);
        $mesiTotali = ($diff->y * 12) + $diff->m;

        return $mesiTotali >= (int)$soglia;
    }

    // 2. Criterio Spesa Pregressa (totale crediti spesi in transazioni.xml)
    if ($tipoCriterio === 'spesa_pregressa') {
        $xmlTransazioni = __DIR__ . '/../data/transazioni.xml';
        if (!file_exists($xmlTransazioni)) return false;

        $domT = new DOMDocument();
        $domT->preserveWhiteSpace = false;
        if (!@$domT->load($xmlTransazioni)) return false;

        $xpathT = new DOMXPath($domT);
        $nodoTotaleSpeso = $xpathT->query("//conto[@id_cliente='$userId']/totale_speso")->item(0);

        if ($nodoTotaleSpeso !== null) {
            $totaleSpeso = (float)$nodoTotaleSpeso->nodeValue;
            return $totaleSpeso >= (float)$soglia;
        }
        return false;
    }

    // 3. Criterio Reputazione Community
    if ($tipoCriterio === 'reputazione') {
        $rep = calcolaReputazioneUtente($userId);
        return $rep >= (int)$soglia;
    }

    return false;
}

function caricaOfferteCatalogo($filtroOrdinamento = 'default', $userId = null)
{
    // Se non passato esplicitamente, proviamo a prendere l'ID utente dalla sessione attiva
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }

    $xmlCatalogo = __DIR__ . '/../data/catalogo.xml';
    $xmlPromozioni = __DIR__ . '/../data/promozioni.xml';

    if (!file_exists($xmlCatalogo)) return [];

    $domCat = new DOMDocument();
    $domCat->preserveWhiteSpace = false;
    $domCat->load($xmlCatalogo);
    $corsiNodi = $domCat->getElementsByTagName('corso');

    // 1. Leggiamo tutte le promozioni attive e verifichiamo i criteri
    $promozioniAttive = [];
    $dataOggi = date('Y-m-d');

    if (file_exists($xmlPromozioni)) {
        $domPromo = new DOMDocument();
        $domPromo->preserveWhiteSpace = false;
        $domPromo->load($xmlPromozioni);
        $promoNodi = $domPromo->getElementsByTagName('promozione');

        foreach ($promoNodi as $pNodo) {
            $dataInizio = $pNodo->getElementsByTagName('data_inizio')->item(0)->nodeValue;
            $dataFine = $pNodo->getElementsByTagName('data_fine')->item(0)->nodeValue;

            // Controllo finestra temporale
            if ($dataOggi >= $dataInizio && $dataOggi <= $dataFine) {
                // Controllo Criterio di Applicazione
                $criterioElem = $pNodo->getElementsByTagName('criterio_applicazione')->item(0);
                $tipoCriterio = $criterioElem ? $criterioElem->getAttribute('tipo_criterio') : 'indiscriminato';
                $soglia = $criterioElem ? (int)$criterioElem->getAttribute('soglia') : 0;

                // Verifichiamo se l'utente attuale ha diritto alla promozione
                if (utenteSoddisfaCriterioPromo($tipoCriterio, $soglia, $userId)) {
                    $idPromo = $pNodo->getAttribute('id_promo');
                    $tipo = $pNodo->getElementsByTagName('tipo')->item(0)->nodeValue;
                    $valore = (int)$pNodo->getElementsByTagName('valore')->item(0)->nodeValue;
                    $titoloPromo = $pNodo->getElementsByTagName('titolo')->item(0)->nodeValue;

                    $corsiApplicati = [];
                    $nodiCorsi = $pNodo->getElementsByTagName('id_corso');
                    foreach ($nodiCorsi as $nc) {
                        $corsiApplicati[] = $nc->nodeValue;
                    }

                    $promozioniAttive[] = [
                        'id' => $idPromo,
                        'titolo' => $titoloPromo,
                        'tipo' => $tipo,
                        'valore' => $valore,
                        'corsi' => $corsiApplicati
                    ];
                }
            }
        }
    }

    // 2. Costruiamo l'array dei corsi
    $offerte = [];
    foreach ($corsiNodi as $nodo) {
        // Ignoriamo i corsi disattivati (Soft Delete)
        if ($nodo->getElementsByTagName('disponibile')->item(0)->nodeValue !== 'true') {
            continue;
        }

        $id = $nodo->getAttribute('id');
        $prezzoBase = (int)$nodo->getElementsByTagName('prezzo_crediti')->item(0)->nodeValue;

        // Estrazione dettagliata delle edizioni
        $edizioniDettagli = [];
        $edizioni = $nodo->getElementsByTagName('edizione');
        foreach ($edizioni as $ed) {
            $edizioniDettagli[] = [
                'trainer' => $ed->getAttribute('trainer'),
                'giorni'  => $ed->getAttribute('giorni'),
                'orario'  => $ed->getAttribute('orario')
            ];
        }

        $scontoPercentuale = 0;
        $bonusCrediti = 0;
        $nomePromo = '';

        // Cerchiamo se questo corso ha una promo attiva
        foreach ($promozioniAttive as $promo) {
            if (in_array($id, $promo['corsi'])) {
                $nomePromo = $promo['titolo'];
                if ($promo['tipo'] === 'sconto') $scontoPercentuale = $promo['valore'];
                if ($promo['tipo'] === 'bonus') $bonusCrediti = $promo['valore'];
            }
        }

        $prezzoFinale = $prezzoBase;
        if ($scontoPercentuale > 0) {
            $prezzoFinale = (int)round($prezzoBase - ($prezzoBase * ($scontoPercentuale / 100)));
        }

        $offerte[] = [
            'id' => $id,
            'categoria' => $nodo->getAttribute('categoria'),
            'titolo' => $nodo->getElementsByTagName('titolo')->item(0)->nodeValue,
            'descrizione' => $nodo->getElementsByTagName('descrizione')->item(0)->nodeValue,
            'immagine' => $nodo->getElementsByTagName('immagine')->item(0)->nodeValue,
            'edizioni' => $edizioniDettagli,
            'intensita' => $nodo->getElementsByTagName('intensita')->item(0)->nodeValue,
            'prezzo_base' => $prezzoBase,
            'prezzo_finale' => $prezzoFinale,
            'sconto_percentuale' => $scontoPercentuale,
            'bonus_crediti' => $bonusCrediti,
            'nome_promo' => $nomePromo
        ];
    }

    // Ordinamento in base al parametro GET
    switch ($filtroOrdinamento) {
        case 'prezzo_crescente':
            usort($offerte, fn($a, $b) => $a['prezzo_finale'] <=> $b['prezzo_finale']);
            break;
        case 'prezzo_decrescente':
            usort($offerte, fn($a, $b) => $b['prezzo_finale'] <=> $a['prezzo_finale']);
            break;
        case 'nome_az':
            usort($offerte, fn($a, $b) => strcmp($a['titolo'], $b['titolo']));
            break;
        case 'intensita':
            $pesiIntensita = ['bassa' => 1, 'media' => 2, 'alta' => 3];
            usort($offerte, fn($a, $b) => ($pesiIntensita[$a['intensita']] ?? 0) <=> ($pesiIntensita[$b['intensita']] ?? 0));
            break;
    }
    return $offerte;
}

// Recupera i dettagli di un singolo corso tramite il suo ID
function caricaCorsoPerId($idCorso, $userId = null)
{
    $offerte = caricaOfferteCatalogo('default', $userId);
    foreach ($offerte as $offerta) {
        if ($offerta['id'] === $idCorso) {
            return $offerta;
        }
    }
    return null;
}
