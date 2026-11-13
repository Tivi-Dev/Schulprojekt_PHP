<?php

function eingabe($frage)
{
    echo $frage;
    $text = fgets(STDIN);
    if ($text === false) {
        exit("\nEingabe beendet. Es erfolgt kein automatisches Speichern.\n");
    }
    return trim($text);
}

function bestaetigen($frage)
{
    while (true) {
        $antwort = strtolower(eingabe($frage . " [j/n]: "));
        if ($antwort === "j" || $antwort === "ja") {
            return true;
        }
        if ($antwort === "n" || $antwort === "nein") {
            return false;
        }
        echo "Bitte j oder n eingeben.\n";
    }
}

function zeigeUeberschrift($text)
{
    echo "\n=== $text ===\n";
}

function pflichtText($frage)
{
    do {
        $text = eingabe($frage);
        if ($text === "") {
            echo "Dieses Feld darf nicht leer sein.\n";
        }
    } while ($text === "");
    return $text;
}

function istGueltigerBestand($text)
{
    if (!is_string($text) || $text === "" || !ctype_digit($text)) {
        return false;
    }
    $zahl = ltrim($text, "0");
    $maximum = (string) PHP_INT_MAX;
    return strlen($zahl) < strlen($maximum)
        || (strlen($zahl) === strlen($maximum) && strcmp($zahl, $maximum) <= 0);
}

function istGueltigeId($text)
{
    return istGueltigerBestand($text) && (int) $text > 0;
}

function findeIndexNachId($liste, $id)
{
    foreach ($liste as $index => $eintrag) {
        if ($eintrag["id"] === $id) {
            return $index;
        }
    }
    return -1;
}

function naechsteId($liste)
{
    $maximum = 0;
    foreach ($liste as $eintrag) {
        $maximum = max($maximum, $eintrag["id"]);
    }
    if ($maximum === PHP_INT_MAX) {
        throw new RuntimeException("Der verfügbare ID-Bereich ist ausgeschöpft.");
    }
    return $maximum + 1;
}

function berechneBestand($geraete)
{
    $summe = 0;
    foreach ($geraete as $geraet) {
        $summe += $geraet["bestand"];
    }
    return $summe;
}

function istGueltigeDateikennung($kennung)
{
    return preg_match('/^[a-z0-9_]+$/D', $kennung) === 1
        && $kennung !== "geraetegruppen";
}

function istGueltigerDateiname($name)
{
    return str_ends_with($name, ".csv")
        && istGueltigeDateikennung(substr($name, 0, -4));
}

function istGueltigerFeldname($name)
{
    return preg_match('/^[a-z_]+$/D', $name) === 1
        && !in_array($name, ["id", "bezeichnung", "bestand", "standort"], true);
}

function gruppennameVergeben($gruppen, $name, $ausnahmeId = 0)
{
    foreach ($gruppen as $gruppe) {
        if ($gruppe["id"] !== $ausnahmeId
            && strtolower(trim($gruppe["name"])) === strtolower(trim($name))) {
            return true;
        }
    }
    return false;
}

function gruppennameEingeben($gruppen, $bisher = "", $id = 0)
{
    while (true) {
        $frage = $bisher === "" ? "Gruppenname: " : "Gruppenname [$bisher] (Enter = behalten): ";
        $name = eingabe($frage);
        if ($name === "" && $bisher !== "") {
            return $bisher;
        }
        if ($name === "") {
            echo "Der Gruppenname darf nicht leer sein.\n";
        } elseif (gruppennameVergeben($gruppen, $name, $id)) {
            echo "Dieser Gruppenname ist bereits vergeben.\n";
        } else {
            return $name;
        }
    }
}

function zusatzfelderEingeben()
{
    echo "Bis zu drei Zusatzfelder; eine leere Eingabe beendet die Definition.\n";
    $felder = [];
    while (count($felder) < 3) {
        $nummer = count($felder) + 1;
        $feld = eingabe("Zusatzfeld $nummer: ");
        if ($feld === "") {
            break;
        }
        if (!istGueltigerFeldname($feld)) {
            echo "Erlaubt: a-z und _. id, bezeichnung, bestand und standort sind reserviert.\n";
        } elseif (in_array($feld, $felder, true)) {
            echo "Dieses Zusatzfeld wurde bereits angegeben.\n";
        } else {
            $felder[] = $feld;
        }
    }
    return $felder;
}

function zeigeGruppen($gruppen)
{
    zeigeUeberschrift("Gerätegruppen");
    if ($gruppen === []) {
        echo "Noch keine Gerätegruppen vorhanden.\n";
    }
    foreach ($gruppen as $gruppe) {
        $felder = $gruppe["felder"] === [] ? "keine" : implode(", ", $gruppe["felder"]);
        $bestand = berechneBestand($gruppe["geraete"]);
        $anzahl = count($gruppe["geraete"]);
        echo "ID {$gruppe['id']}: {$gruppe['name']}\n";
        echo "  CSV: {$gruppe['datei']} | Zusatzfelder: $felder\n";
        echo "  Gesamtbestand: $bestand | Datensätze: $anzahl\n";
    }
}

function gruppeAnlegen($gruppen)
{
    $id = naechsteId($gruppen);
    $name = gruppennameEingeben($gruppen);
    while (true) {
        $kennung = eingabe("Dateikennung (ohne .csv): ");
        if (!istGueltigeDateikennung($kennung)) {
            echo "Erlaubt: a-z, 0-9 und _. geraetegruppen ist reserviert.\n";
        } elseif (in_array($kennung . ".csv", array_column($gruppen, "datei"), true)) {
            echo "Diese Dateikennung ist bereits vergeben.\n";
        } else {
            break;
        }
    }
    $gruppen[] = [
        "id" => $id,
        "name" => $name,
        "datei" => $kennung . ".csv",
        "felder" => zusatzfelderEingeben(),
        "geraete" => [],
    ];
    echo "Gruppe mit ID $id angelegt.\n";
    return $gruppen;
}

function gruppeBearbeiten($gruppen, $id)
{
    $index = findeIndexNachId($gruppen, $id);
    if ($index === -1) {
        echo "Gruppe nicht gefunden.\n";
        return $gruppen;
    }
    $gruppen[$index]["name"] = gruppennameEingeben($gruppen, $gruppen[$index]["name"], $id);
    echo "Datei bleibt: {$gruppen[$index]['datei']}\n";
    if ($gruppen[$index]["geraete"] !== []) {
        echo "Zusatzfelder können nicht geändert werden: Die Gruppe enthält Gerätedatensätze.\n";
    } elseif (bestaetigen("Zusatzfelder vollständig neu festlegen?")) {
        $gruppen[$index]["felder"] = zusatzfelderEingeben();
    }
    return $gruppen;
}

function gruppeLoeschen($gruppen, $id)
{
    $index = findeIndexNachId($gruppen, $id);
    if ($index === -1) {
        echo "Gruppe nicht gefunden.\n";
    } elseif ($gruppen[$index]["geraete"] !== []) {
        echo "Löschen nicht möglich: Zuerst alle Gerätedatensätze entfernen, auch solche mit Bestand 0.\n";
    } elseif (bestaetigen("Gruppe {$gruppen[$index]['name']} (ID $id) löschen?")) {
        unset($gruppen[$index]);
        $gruppen = array_values($gruppen);
        echo "Gruppe entfernt. Die CSV-Datei wird erst beim Speichern gelöscht.\n";
    }
    return $gruppen;
}

function idAuswaehlen($liste, $bezeichnung)
{
    if ($liste === []) {
        echo "Keine Einträge zur Auswahl vorhanden.\n";
        return 0;
    }
    while (true) {
        $text = eingabe("$bezeichnung-ID (0 = zurück): ");
        if ($text === "0") {
            return 0;
        }
        if (istGueltigeId($text) && findeIndexNachId($liste, (int) $text) !== -1) {
            return (int) $text;
        }
        echo "Bitte eine vorhandene positive ID oder 0 eingeben.\n";
    }
}

function zeigeGeraet($gruppe, $geraet)
{
    echo "ID {$geraet['id']}: {$geraet['bezeichnung']}\n";
    echo "  Bestand: {$geraet['bestand']} | Standort: {$geraet['standort']}\n";
    foreach ($gruppe["felder"] as $feld) {
        echo "  $feld: {$geraet[$feld]}\n";
    }
}

function zeigeGeraete($gruppe)
{
    zeigeUeberschrift("Geräte: " . $gruppe["name"]);
    if ($gruppe["geraete"] === []) {
        echo "Noch keine Gerätedatensätze vorhanden.\n";
    }
    foreach ($gruppe["geraete"] as $geraet) {
        zeigeGeraet($gruppe, $geraet);
    }
    echo "Gesamtbestand: " . berechneBestand($gruppe["geraete"]) . "\n";
}

function bestandEingeben($bisher = null)
{
    while (true) {
        $frage = $bisher === null ? "Bestand: " : "Bestand [$bisher] (Enter = behalten): ";
        $text = eingabe($frage);
        if ($text === "" && $bisher !== null) {
            return $bisher;
        }
        if (istGueltigerBestand($text)) {
            return (int) $text;
        }
        echo "Bitte eine nichtnegative ganze Zahl bis " . PHP_INT_MAX . " eingeben.\n";
    }
}

function geraetAnlegen($gruppe)
{
    $geraet = [
        "id" => naechsteId($gruppe["geraete"]),
        "bezeichnung" => pflichtText("Bezeichnung: "),
        "bestand" => bestandEingeben(),
        "standort" => pflichtText("Standort: "),
    ];
    foreach ($gruppe["felder"] as $feld) {
        $geraet[$feld] = eingabe("$feld (optional): ");
    }
    $gruppe["geraete"][] = $geraet;
    echo "Gerät mit ID {$geraet['id']} angelegt.\n";
    return $gruppe;
}

function geraetBearbeiten($gruppe, $id)
{
    $index = findeIndexNachId($gruppe["geraete"], $id);
    if ($index === -1) {
        echo "Gerät nicht gefunden.\n";
        return $gruppe;
    }
    $geraet = $gruppe["geraete"][$index];
    zeigeGeraet($gruppe, $geraet);
    echo "Enter übernimmt den bisherigen Wert; 0 ist ein gültiger Bestand.\n";
    $text = eingabe("Bezeichnung [{$geraet['bezeichnung']}]: ");
    if ($text !== "") {
        $geraet["bezeichnung"] = $text;
    }
    $geraet["bestand"] = bestandEingeben($geraet["bestand"]);
    $text = eingabe("Standort [{$geraet['standort']}]: ");
    if ($text !== "") {
        $geraet["standort"] = $text;
    }
    foreach ($gruppe["felder"] as $feld) {
        $text = eingabe("$feld [{$geraet[$feld]}]: ");
        if ($text !== "") {
            $geraet[$feld] = $text;
        }
    }
    $gruppe["geraete"][$index] = $geraet;
    return $gruppe;
}

function geraetLoeschen($gruppe, $id)
{
    $index = findeIndexNachId($gruppe["geraete"], $id);
    if ($index === -1) {
        echo "Gerät nicht gefunden.\n";
        return $gruppe;
    }
    zeigeGeraet($gruppe, $gruppe["geraete"][$index]);
    if (bestaetigen("Diesen Gerätedatensatz löschen?")) {
        unset($gruppe["geraete"][$index]);
        $gruppe["geraete"] = array_values($gruppe["geraete"]);
        echo "Gerät entfernt. Neuer Gesamtbestand: " . berechneBestand($gruppe["geraete"]) . "\n";
    }
    return $gruppe;
}

function gruppenKopf()
{
    return ["id", "name", "datei", "extra1", "extra2", "extra3"];
}

function geraeteKopf($gruppe)
{
    return array_merge(["id", "bezeichnung", "bestand", "standort"], $gruppe["felder"]);
}

function pruefeGruppen($gruppen)
{
    $ids = [];
    $namen = [];
    $dateien = [];
    foreach ($gruppen as $gruppe) {
        if (!is_int($gruppe["id"]) || $gruppe["id"] <= 0 || in_array($gruppe["id"], $ids, true)) {
            throw new RuntimeException("Ungültige oder doppelte Gruppen-ID.");
        }
        $ids[] = $gruppe["id"];
        $name = strtolower(trim($gruppe["name"]));
        if ($name === "" || in_array($name, $namen, true)) {
            throw new RuntimeException("Leerer oder doppelter Gruppenname: {$gruppe['name']}");
        }
        $namen[] = $name;
        if (!istGueltigerDateiname($gruppe["datei"]) || in_array($gruppe["datei"], $dateien, true)) {
            throw new RuntimeException("Ungültiger oder doppelter Dateiname: {$gruppe['datei']}");
        }
        $dateien[] = $gruppe["datei"];
        if (count($gruppe["felder"]) > 3) {
            throw new RuntimeException("Höchstens drei Zusatzfelder pro Gruppe erlaubt.");
        }
        $felder = [];
        foreach ($gruppe["felder"] as $feld) {
            if (!istGueltigerFeldname($feld) || in_array($feld, $felder, true)) {
                throw new RuntimeException("Ungültiges oder doppeltes Zusatzfeld: $feld");
            }
            $felder[] = $feld;
        }
        $geraeteIds = [];
        foreach ($gruppe["geraete"] as $geraet) {
            if (!is_int($geraet["id"]) || $geraet["id"] <= 0 || in_array($geraet["id"], $geraeteIds, true)) {
                throw new RuntimeException("Ungültige oder doppelte Geräte-ID in {$gruppe['name']}.");
            }
            $geraeteIds[] = $geraet["id"];
            if (!is_int($geraet["bestand"]) || $geraet["bestand"] < 0) {
                throw new RuntimeException("Ungültiger Bestand in {$gruppe['name']}.");
            }
            if (trim($geraet["bezeichnung"]) === "" || trim($geraet["standort"]) === "") {
                throw new RuntimeException("Bezeichnung und Standort dürfen nicht leer sein.");
            }
        }
    }
}

function csvLesen($pfad, $kopf)
{
    $datei = @fopen($pfad, "rb");
    if ($datei === false) {
        throw new RuntimeException("Datei fehlt oder ist nicht lesbar: $pfad");
    }
    try {
        $zeilen = [];
        $kopfGelesen = false;
        $nummer = 0;
        while (($zeile = fgetcsv($datei, 0, ";", '"', "")) !== false) {
            $nummer++;
            if (count($zeile) === 1 && trim((string) $zeile[0]) === "") {
                continue;
            }
            if (!$kopfGelesen) {
                if ($zeile !== $kopf) {
                    throw new RuntimeException("Falsche Kopfzeile in $pfad.");
                }
                $kopfGelesen = true;
            } else {
                if (count($zeile) !== count($kopf)) {
                    throw new RuntimeException("Falsche Spaltenanzahl in $pfad, CSV-Eintrag $nummer.");
                }
                $zeilen[] = array_combine($kopf, $zeile);
            }
        }
        if (!feof($datei)) {
            throw new RuntimeException("Lesefehler in $pfad.");
        }
        if (!$kopfGelesen) {
            throw new RuntimeException("Kopfzeile fehlt in $pfad.");
        }
        return $zeilen;
    } finally {
        fclose($datei);
    }
}

function ladeAlles($ordner)
{
    try {
        $pfad = $ordner . "/geraetegruppen.csv";
        if (!file_exists($pfad)) {
            return [];
        }
        $gruppen = [];
        foreach (csvLesen($pfad, gruppenKopf()) as $zeile) {
            if (!istGueltigeId($zeile["id"])) {
                throw new RuntimeException("Ungültige Gruppen-ID in $pfad.");
            }
            $felder = [];
            $leeresFeld = false;
            foreach (["extra1", "extra2", "extra3"] as $spalte) {
                if ($zeile[$spalte] === "") {
                    $leeresFeld = true;
                } elseif ($leeresFeld) {
                    throw new RuntimeException("Zusatzfelder müssen lückenlos von extra1 an belegt sein.");
                } else {
                    $felder[] = $zeile[$spalte];
                }
            }
            $gruppen[] = [
                "id" => (int) $zeile["id"],
                "name" => trim($zeile["name"]),
                "datei" => $zeile["datei"],
                "felder" => $felder,
                "geraete" => [],
            ];
        }
        pruefeGruppen($gruppen);
        foreach ($gruppen as $index => $gruppe) {
            $pfad = $ordner . "/" . $gruppe["datei"];
            foreach (csvLesen($pfad, geraeteKopf($gruppe)) as $geraet) {
                if (!istGueltigeId($geraet["id"]) || !istGueltigerBestand($geraet["bestand"])) {
                    throw new RuntimeException("Ungültige Geräte-ID oder ungültiger Bestand in $pfad.");
                }
                $geraet["id"] = (int) $geraet["id"];
                $geraet["bestand"] = (int) $geraet["bestand"];
                $gruppen[$index]["geraete"][] = $geraet;
            }
        }
        pruefeGruppen($gruppen);
        return $gruppen;
    } catch (RuntimeException $fehler) {
        echo "Ladefehler: " . $fehler->getMessage() . "\n";
        return false;
    }
}

function csvVorbereiten($ordner, $kopf, $zeilen)
{
    $temp = @tempnam($ordner, ".csv_");
    if ($temp === false || realpath(dirname($temp)) !== realpath($ordner)) {
        if ($temp !== false) {
            @unlink($temp);
        }
        throw new RuntimeException("Keine temporäre Datei im Datenverzeichnis möglich.");
    }
    $datei = @fopen($temp, "wb");
    if ($datei === false) {
        @unlink($temp);
        throw new RuntimeException("Temporäre Datei lässt sich nicht öffnen.");
    }
    try {
        if (@fputcsv($datei, $kopf, ";", '"', "") === false) {
            throw new RuntimeException("CSV-Kopfzeile konnte nicht geschrieben werden.");
        }
        foreach ($zeilen as $zeile) {
            if (@fputcsv($datei, $zeile, ";", '"', "") === false) {
                throw new RuntimeException("CSV-Daten konnten nicht geschrieben werden.");
            }
        }
        if (!@fflush($datei)) {
            throw new RuntimeException("CSV-Daten konnten nicht vollständig geschrieben werden.");
        }
    } catch (RuntimeException $fehler) {
        fclose($datei);
        @unlink($temp);
        throw $fehler;
    }
    if (!@fclose($datei)) {
        @unlink($temp);
        throw new RuntimeException("Fehler beim Schließen der CSV-Datei.");
    }
    return $temp;
}

function speichereAlles($ordner, $gruppen, $dateienZumLoeschen = [])
{
    $vorbereitet = [];
    try {
        pruefeGruppen($gruppen);
        foreach ($dateienZumLoeschen as $name) {
            if (!istGueltigerDateiname($name)) {
                throw new RuntimeException("Ungültiger Dateiname in der Löschliste.");
            }
        }
        if (!is_dir($ordner) && !@mkdir($ordner, 0777, true) && !is_dir($ordner)) {
            throw new RuntimeException("Datenverzeichnis kann nicht angelegt werden.");
        }
        $gruppenZeilen = [];
        foreach ($gruppen as $gruppe) {
            $felder = array_pad($gruppe["felder"], 3, "");
            $gruppenZeilen[] = [$gruppe["id"], $gruppe["name"], $gruppe["datei"], $felder[0], $felder[1], $felder[2]];
            $kopf = geraeteKopf($gruppe);
            $zeilen = [];
            foreach ($gruppe["geraete"] as $geraet) {
                $zeile = [];
                foreach ($kopf as $feld) {
                    $zeile[] = $geraet[$feld];
                }
                $zeilen[] = $zeile;
            }
            $vorbereitet[$gruppe["datei"]] = csvVorbereiten($ordner, $kopf, $zeilen);
        }
        $vorbereitet["geraetegruppen.csv"] = csvVorbereiten($ordner, gruppenKopf(), $gruppenZeilen);
        foreach ($vorbereitet as $name => $temp) {
            if (!@rename($temp, $ordner . "/" . $name)) {
                throw new RuntimeException("Datei konnte nicht ersetzt werden: $name");
            }
        }
        $aktiveDateien = array_column($gruppen, "datei");
        foreach (array_unique($dateienZumLoeschen) as $name) {
            $pfad = $ordner . "/" . $name;
            if (!in_array($name, $aktiveDateien, true) && file_exists($pfad) && !@unlink($pfad)) {
                throw new RuntimeException("Alte Gerätedatei konnte nicht gelöscht werden: $name");
            }
        }
        return true;
    } catch (RuntimeException $fehler) {
        echo "Speicherfehler: " . $fehler->getMessage() . "\n";
        echo "Nicht vollständig gespeichert. Bei einem Teilerfolg Dateien vor erneutem Laden prüfen.\n";
        return false;
    } finally {
        foreach ($vorbereitet as $temp) {
            if (file_exists($temp)) {
                @unlink($temp);
            }
        }
    }
}
