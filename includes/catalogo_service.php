<?php

// Questo modulo si occupa di leggere e manipolare l'albero XML del catalogo corsi

function caricaOfferteCatalogo($filtroOrdinamento = 'default')
{
    $xmlPath = __DIR__ . '/../data/catalogo.xml';

    if (!file_exists($xmlPath)) {
        return [];
    }

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->load($xmlPath);

    $offerteNodi = $dom->getElementsByTagName('offerta');
    $offerte = [];

    $dataOggi = date('Y-m-d');

    foreach ($offerteNodi as $nodo) {
        $id = $nodo->getAttribute('id');
        $categoria = $nodo->getAttribute('categoria');

        $titolo = $nodo->getElementsByTagName('titolo')->item(0)->nodeValue;
        $descrizione = $nodo->getElementsByTagName('descrizione')->item(0)->nodeValue;
        $trainer = $nodo->getElementsByTagName('trainer')->item(0)->nodeValue;
        $intensita = $nodo->getElementsByTagName('intensita')->item(0)->nodeValue;
        $prezzoBase = (int)$nodo->getElementsByTagName('prezzo_crediti')->item(0)->nodeValue;
        $disponibile = $nodo->getElementsByTagName('disponibile')->item(0)->nodeValue === 'true';

        // Calcolo di eventuali promozioni attive (sconto o bonus)
        $promozioniNodi = $nodo->getElementsByTagName('promozione');
        $scontoPercentuale = 0;
        $bonusCrediti = 0;

        foreach ($promozioniNodi as $promoNodo) {
            $tipoPromo = $promoNodo->getElementsByTagName('tipo')->item(0)->nodeValue;
            $valore = (float)$promoNodo->getElementsByTagName('valore')->item(0)->nodeValue;
            $dataInizio = $promoNodo->getElementsByTagName('data_inizio')->item(0)->nodeValue;
            $dataFine = $promoNodo->getElementsByTagName('data_fine')->item(0)->nodeValue;

            // Verifica validità temporale
            if ($dataOggi >= $dataInizio && $dataOggi <= $dataFine) {
                if ($tipoPromo === 'sconto') {
                    $scontoPercentuale = $valore;
                } elseif ($tipoPromo === 'bonus') {
                    $bonusCrediti = $valore;
                }
            }
        }

        // Calcolo prezzo finale scontato
        $prezzoFinale = $prezzoBase;
        if ($scontoPercentuale > 0) {
            $prezzoFinale = round($prezzoBase - ($prezzoBase * ($scontoPercentuale / 100)), 2);
        }

        $offerte[] = [
            'id' => $id,
            'categoria' => $categoria,
            'titolo' => $titolo,
            'descrizione' => $descrizione,
            'trainer' => $trainer,
            'intensita' => $intensita,
            'prezzo_base' => $prezzoBase,
            'prezzo_finale' => $prezzoFinale,
            'sconto_percentuale' => $scontoPercentuale,
            'bonus_crediti' => $bonusCrediti,
            'disponibile' => $disponibile
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
            // Ordine personalizzato: bassa -> media -> alta
            $pesiIntensita = ['bassa' => 1, 'media' => 2, 'alta' => 3];
            usort($offerte, fn($a, $b) => ($pesiIntensita[$a['intensita']] ?? 0) <=> ($pesiIntensita[$b['intensita']] ?? 0));
            break;
        default:
            // Nessun ordinamento specifico (ordine del file XML)
            break;
    }

    return $offerte;
}
