<?php
if (PHP_SAPI !== "cli") {
    exit("Diese Anwendung wird in der Konsole mit php verwaltung.php gestartet.\n");
}

require_once __DIR__ . "/funktionen.php";

$ordner = __DIR__ . "/daten";
$gruppen = ladeAlles($ordner);
if ($gruppen === false) {
    fwrite(STDERR, "Start abgebrochen. CSV-Dateien korrigieren und erneut starten; es wurde nichts gespeichert.\n");
    exit(1);
}
$geaendert = false;
$speichernErlaubt = true;
$dateienZumLoeschen = [];
$menue = "haupt";
$gruppenId = 0;

echo "Geräteverwaltung – PHP und CSV\n";
echo count($gruppen) . " Gruppe(n) geladen. Änderungen werden erst mit 'Alles speichern' gespeichert.\n";

while (true) {
    $vorher = $gruppen;
    $neuGeladen = false;
    try {
        if ($menue === "haupt") {
            zeigeUeberschrift("Hauptmenü");
            echo $geaendert ? "Status: ungespeicherte Änderungen\n" : "Status: keine ungespeicherten Änderungen\n";
            if (!$speichernErlaubt) {
                echo "Speichern gesperrt: Erst CSV-Fehler beheben und erfolgreich neu laden.\n";
            }
            echo "1 Gerätegruppen verwalten\n2 Geräte einer Gruppe verwalten\n3 Alles speichern\n4 Gespeicherten Stand neu laden\n0 Beenden\n";
            $wahl = eingabe("Auswahl: ");
            switch ($wahl) {
                case "1":
                    $menue = "gruppen";
                    break;
                case "2":
                    zeigeGruppen($gruppen);
                    $gruppenId = idAuswaehlen($gruppen, "Gruppen");
                    if ($gruppenId !== 0) {
                        $menue = "geraete";
                    }
                    break;
                case "3":
                    if (!$speichernErlaubt) {
                        echo "Speichern nach Ladefehler gesperrt. Vorhandene Dateien bleiben geschützt.\n";
                    } elseif (speichereAlles($ordner, $gruppen, $dateienZumLoeschen)) {
                        $geaendert = false;
                        $dateienZumLoeschen = [];
                        echo "Alle Gruppen und Geräte erfolgreich gespeichert.\n";
                    }
                    break;
                case "4":
                    if ($geaendert && !bestaetigen("Ungespeicherte Änderungen verwerfen und neu laden?")) {
                        break;
                    }
                    $geladen = ladeAlles($ordner);
                    if ($geladen === false) {
                        $speichernErlaubt = false;
                        echo "Arbeitsspeicher unverändert. Speichern bleibt bis zum erfolgreichen Laden gesperrt.\n";
                    } else {
                        $gruppen = $geladen;
                        $geaendert = false;
                        $dateienZumLoeschen = [];
                        $speichernErlaubt = true;
                        $neuGeladen = true;
                        echo "Gespeicherten Stand vollständig geladen.\n";
                    }
                    break;
                case "0":
                    if (!$geaendert) {
                        exit("Auf Wiedersehen!\n");
                    }
                    echo "1 Speichern und beenden\n2 Ohne Speichern beenden\n0 Abbrechen\n";
                    $ende = eingabe("Auswahl: ");
                    if ($ende === "2") {
                        exit("Ohne Speichern beendet.\n");
                    }
                    if ($ende === "1") {
                        if (!$speichernErlaubt) {
                            echo "Speichern nach Ladefehler gesperrt. Bitte erst erfolgreich neu laden.\n";
                        } elseif (speichereAlles($ordner, $gruppen, $dateienZumLoeschen)) {
                            exit("Alles gespeichert. Auf Wiedersehen!\n");
                        }
                    } elseif ($ende !== "0") {
                        echo "Ungültige Auswahl. Zurück zum Hauptmenü.\n";
                    }
                    break;
                default:
                    echo "Ungültige Auswahl. Bitte einen Menüpunkt wählen.\n";
            }
        } elseif ($menue === "gruppen") {
            zeigeUeberschrift("Gruppenverwaltung");
            echo "1 Gruppen auflisten\n2 Gruppe hinzufügen\n3 Gruppe bearbeiten\n4 Gruppe löschen\n0 Zurück\n";
            $wahl = eingabe("Auswahl: ");
            switch ($wahl) {
                case "1":
                    zeigeGruppen($gruppen);
                    break;
                case "2":
                    $gruppen = gruppeAnlegen($gruppen);
                    break;
                case "3":
                case "4":
                    zeigeGruppen($gruppen);
                    $id = idAuswaehlen($gruppen, "Gruppen");
                    if ($id !== 0) {
                        if ($wahl === "3") {
                            $gruppen = gruppeBearbeiten($gruppen, $id);
                        } else {
                            $index = findeIndexNachId($gruppen, $id);
                            $datei = $gruppen[$index]["datei"];
                            $gruppen = gruppeLoeschen($gruppen, $id);
                            if (findeIndexNachId($gruppen, $id) === -1) {
                                $dateienZumLoeschen[] = $datei;
                            }
                        }
                    }
                    break;
                case "0":
                    $menue = "haupt";
                    break;
                default:
                    echo "Ungültige Auswahl. Bitte einen Menüpunkt wählen.\n";
            }
        } else {
            $index = findeIndexNachId($gruppen, $gruppenId);
            zeigeUeberschrift("Geräteverwaltung: " . $gruppen[$index]["name"]);
            echo "1 Geräte auflisten\n2 Gerät hinzufügen\n3 Gerät bearbeiten\n4 Gerät löschen\n0 Zurück\n";
            $wahl = eingabe("Auswahl: ");
            switch ($wahl) {
                case "1":
                    zeigeGeraete($gruppen[$index]);
                    break;
                case "2":
                    $gruppen[$index] = geraetAnlegen($gruppen[$index]);
                    break;
                case "3":
                case "4":
                    zeigeGeraete($gruppen[$index]);
                    $id = idAuswaehlen($gruppen[$index]["geraete"], "Geräte");
                    if ($id !== 0) {
                        $gruppen[$index] = $wahl === "3"
                            ? geraetBearbeiten($gruppen[$index], $id)
                            : geraetLoeschen($gruppen[$index], $id);
                    }
                    break;
                case "0":
                    $menue = "haupt";
                    break;
                default:
                    echo "Ungültige Auswahl. Bitte einen Menüpunkt wählen.\n";
            }
        }
    } catch (RuntimeException $fehler) {
        echo "Vorgang abgebrochen: " . $fehler->getMessage() . "\n";
    }
    if ($gruppen !== $vorher && !$neuGeladen) {
        $geaendert = true;
    }
}
