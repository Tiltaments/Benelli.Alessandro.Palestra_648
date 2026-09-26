<?php
// includes/catalogo_service.php - Servizio per la gestione del catalogo corsi e promozioni
function caricaOfferteCatalogo($filtroOrdinamento = 'default')
{
    $xmlCatalogo = __DIR__ . '/../data/catalogo.xml';
    $xmlPromozioni = __DIR__ . '/../data/promozioni.xml';

    if (!file_exists($xmlCatalogo)) return [];

    $domCat = new DOMDocument();
    $domCat->preserveWhiteSpace = false;
    $domCat->load($xmlCatalogo);
    $corsiNodi = $domCat->getElementsByTagName('corso');

    // 1. Leggiamo tutte le promozioni attive
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

            if ($dataOggi >= $dataInizio && $dataOggi <= $dataFine) {
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

    // 2. Costruiamo l'array dei corsi
    $offerte = [];
    foreach ($corsiNodi as $nodo) {
        // Ignoriamo i corsi disattivati (Soft Delete)
        if ($nodo->getElementsByTagName('disponibile')->item(0)->nodeValue !== 'true') {
            continue;
        }

        $id = $nodo->getAttribute('id');
        $prezzoBase = (int)$nodo->getElementsByTagName('prezzo_crediti')->item(0)->nodeValue;

        // Estrazione multipla dei trainer
        $trainers = [];
        $edizioni = $nodo->getElementsByTagName('edizione');
        foreach ($edizioni as $ed) {
            $trainers[] = $ed->getAttribute('trainer');
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
            $prezzoFinale = round($prezzoBase - ($prezzoBase * ($scontoPercentuale / 100)));
        }

        $offerte[] = [
            'id' => $id,
            'categoria' => $nodo->getAttribute('categoria'),
            'titolo' => $nodo->getElementsByTagName('titolo')->item(0)->nodeValue,
            'descrizione' => $nodo->getElementsByTagName('descrizione')->item(0)->nodeValue,
            'immagine' => $nodo->getElementsByTagName('immagine')->item(0)->nodeValue,
            'trainers' => implode(", ", $trainers),
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
