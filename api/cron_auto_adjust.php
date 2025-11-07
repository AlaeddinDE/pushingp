<?php
include '/var/www/html/includes/db.php';

/*
  Automatische Guthabenverrechnung:
  - Prüft Guthaben jedes Mitglieds
  - Wenn Guthaben >= Pflichtbeitrag -> erzeugt keine neue Pflichtzahlung
  - Wenn Guthaben < Pflichtbeitrag -> bucht Differenz als 'Pflicht-Einzahlung'
*/

$mitglieder = $conn->query("SELECT id, name, pflicht_monatlich FROM mitglieder");

while ($m = $mitglieder->fetch_assoc()) {
    $id = $m['id'];
    $name = $m['name'];
    $pflicht = (float)$m['pflicht_monatlich'];

    // aktuelles Guthaben:
    $res = $conn->query("SELECT SUM(betrag) AS saldo FROM transaktionen WHERE mitglied_id=$id");
    $saldo = 0;
    if ($r = $res->fetch_assoc()) $saldo = (float)$r['saldo'];

    // Prüfung:
    if ($saldo >= $pflicht) {
        // Mitglied hat genug Guthaben → kein neuer Zahlungseintrag
        $msg = sprintf("[OK] %s hat %.2f € Guthaben (Pflicht %.2f €) – keine Zahlung nötig.\n", $name, $saldo, $pflicht);
        echo $msg;
        continue;
    }

    // Differenz berechnen und als „Pflicht-Einzahlung“ verbuchen
    $differenz = round($pflicht - $saldo, 2);

    $stmt = $conn->prepare("INSERT INTO transaktionen (typ, typ_differenziert, betrag, mitglied_id, beschreibung)
                            VALUES ('EINZAHLUNG','AUTOMATISCH', ?, ?, 'Automatische Monatszahlung')");
    $stmt->bind_param('di', $differenz, $id);
    $stmt->execute();

    echo sprintf("[NEU] %s hat %.2f € Guthaben, Pflicht %.2f € → neue Einzahlung %.2f € erzeugt.\n",
        $name, $saldo, $pflicht, $differenz);
}
?>
