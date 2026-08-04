---
title: "Der komplette Leitfaden zu Auto-Populate in Easy Form Builder"
slug: "easy-form-builder-auto-populate"
meta_description: "Schritt-für-Schritt-Anleitung zur Aktivierung und Nutzung von Auto-Populate (Automatisch ausfüllen) in Easy Form Builder: WordPress-Formularfelder automatisch aus einem Datensatz, früheren Einreichungen oder einer externen API ausfüllen."
focus_keyphrase: "Easy Form Builder Auto-Populate"
secondary_keyphrases:
  - "WordPress Formular automatisch ausfüllen"
  - "Datensatz automatisch füllen"
  - "Integrationen automatisch ausfüllen"
  - "WordPress-Formular mit externer API verbinden"
  - "WordPress Formularbuilder Add-on"
search_intent: "Informations- und Einrichtungsanleitung"
audience: "WordPress-Website-Betreiber und -Administratoren mit Easy Form Builder Pro"
product_version: "Easy Form Builder Version 4.0 oder höher; Add-on zum automatischen Ausfüllen (Auto-Populate)"
last_reviewed: "2026-07-28"
---

# Der komplette Leitfaden zur Aktivierung, Einrichtung und Nutzung von Auto-Populate in Easy Form Builder

**Schlüsselwörter:** Easy Form Builder Auto-Populate, WordPress Formular automatisch ausfüllen, Datensatz automatisch füllen, Integrationen automatisch ausfüllen, WordPress-Formular mit externer API verbinden, WordPress Formularbuilder Add-on, Formularfelder vorausfüllen, intelligentes WordPress-Formular.

**Breadcrumb:** Easy Form Builder Dokumentation › Add-ons › Automatisch ausfüllen (Auto-Populate) › Kompletter Leitfaden · Sprachen: [فارسی](EFB-Auto-Populate-Complete-Guide.fa.md) | [English](EFB-Auto-Populate-Complete-Guide.en.md) | [العربية](EFB-Auto-Populate-Complete-Guide.ar.md) | Deutsch

Wenn Ihre WordPress-Formulare Besucher dazu zwingen, jedes Mal Informationen einzutippen, die Sie bereits besitzen, löst das Add-on **Auto-Populate (Automatisch ausfüllen)** in **Easy Form Builder** genau dieses Problem. Es füllt Formularfelder automatisch aus, ohne dass der Besucher eingreifen muss, und zwar aus drei verschiedenen Quellen: einem **Datensatz** (einer von Ihnen hochgeladenen CSV-Datei), den **eigenen früheren Einreichungen des Formulars** oder einer **externen API**.

> **Umfang dieser Dokumentation:** Dieser Leitfaden wurde anhand einer direkten Prüfung des tatsächlichen Codes des Auto-Populate-Add-ons und der offiziellen deutschen Übersetzungsdateien des Plugins (Ordner `languages/`) erstellt. Nichts wurde geraten. Begriffe wie „Installieren", „Datensatz" und „Add-ons" sind genau das, was in Ihrem deutschsprachigen Dashboard angezeigt wird. Weicht eine Bezeichnung in Ihrer Installation leicht ab, liegt das an einer aktualisierten Plugin- oder Übersetzungsdatei-Version.

## Kurzantwort (für Suchmaschinen und KI-Assistenten)

- Auto-Populate ist ein **reines Pro-Add-on** für Easy Form Builder, das Formularfelder automatisch ausfüllt.
- Es unterstützt drei Datenquellen: einen **Datensatz** (hochgeladene CSV-Datei), die **eigenen früheren Einreichungen des Formulars** und eine **externe REST-API**.
- Aktiviert wird es unter **Easy Form Builder → Add-ons** mit einem einzigen Klick auf den Button „**Installieren**“ — ein separater „Speichern“-Schritt ist nicht nötig.
- Nach der Aktivierung erscheinen zwei neue Einstellungsseiten: **Datensatz automatisch füllen** und **Integrationen automatisch ausfüllen**.
- Für den externen API-Modus erfolgt die Aktivierung auf einem bestimmten Formular vollständig im Assistenten „Integrationen automatisch ausfüllen" (Auswahl des Zielformulars in Schritt 3 + Speichern) – es gibt keinen separaten Schalter dafür im Formularbuilder selbst.
- Die Funktion setzt eine aktive **Pro**-Lizenz voraus; ohne sie bleiben die Einstellungsseiten gesperrt.

## Inhaltsverzeichnis

- [Was ist Auto-Populate und wer sollte es nutzen?](#was-ist-auto-populate-und-wer-sollte-es-nutzen)
- [Wie funktioniert Auto-Populate? (drei Modi)](#wie-funktioniert-auto-populate-drei-modi)
- [Was sind die Voraussetzungen?](#was-sind-die-voraussetzungen)
- [Wie installiere und aktiviere ich Auto-Populate?](#wie-installiere-und-aktiviere-ich-auto-populate)
- [Wie erstelle und verwalte ich einen Datensatz (CSV-Datei)?](#wie-erstelle-und-verwalte-ich-einen-datensatz-csv-datei)
- [Wie aktiviere ich Auto-Populate mit einem Datensatz auf einem Formular?](#wie-aktiviere-ich-auto-populate-mit-einem-datensatz-auf-einem-formular)
- [Wie funktioniert das Ausfüllen aus früheren Einreichungen des Formulars?](#wie-funktioniert-das-ausfüllen-aus-früheren-einreichungen-des-formulars)
- [Wie erstelle ich eine externe API-Verbindung? (Integrationen automatisch ausfüllen)](#wie-erstelle-ich-eine-externe-api-verbindung-integrationen-automatisch-ausfüllen)
- [Wie wird die API-Verbindung auf einem Formular aktiviert?](#wie-wird-die-api-verbindung-auf-einem-formular-aktiviert)
- [Wie verhält sich Auto-Populate im Frontend, und was bewirkt das Caching?](#wie-verhält-sich-auto-populate-im-frontend-und-was-bewirkt-das-caching)
- [Sicherheitshinweise und Best Practices](#sicherheitshinweise-und-best-practices)
- [Häufige Fehler und ihre Lösungen](#häufige-fehler-und-ihre-lösungen)
- [Checkliste vor der Veröffentlichung](#checkliste-vor-der-veröffentlichung)
- [Häufig gestellte Fragen](#häufig-gestellte-fragen)

## Was ist Auto-Populate und wer sollte es nutzen?

> „Mit dem Add-on zum automatischen Ausfüllen können Sie Formularfelder automatisch aus Datensätzen, früher übermittelten Formularen oder externen APIs befüllen lassen."

Statt einen Besucher Informationen erneut eintippen zu lassen, die Sie bereits besitzen — in einer CSV-Datei, in der Datenbank des Formulars selbst oder in einem externen System wie einem CRM —, muss er nur einen einzigen identifizierenden Wert eingeben (Personalausweisnummer, Kundencode, Bestellnummer oder E-Mail-Adresse); die übrigen Felder werden automatisch ausgefüllt.

Dieses Add-on eignet sich für:

- Websites mit einer bereits vorhandenen Liste von Kunden, Mitarbeitern oder Rabattcodes, aus der ein Formular per CSV befüllt werden soll.
- Formulare mit wiederkehrenden Besuchern, die bereits übermittelte Informationen nicht erneut eintippen sollen.
- Websites, deren benötigte Formulardaten in einem externen System (CRM, ERP oder einer beliebigen REST-API) vorliegen.

## Wie funktioniert Auto-Populate? (drei Modi)

| Methode | Datenquelle | Typisches Anwendungsbeispiel |
|---|---|---|
| **Datensatz automatisch füllen** | Eine von Ihnen hochgeladene CSV-Datei | Mitarbeiterlisten, Kunden, Rabattcodes, Schüler |
| **Ausfüllen aus früheren Einreichungen** | Frühere Einreichungen desselben Formulars in der WordPress-Datenbank | Ein wiederkehrender Kunde gibt seine E-Mail-Adresse ein, und seine übrigen Daten erscheinen erneut |
| **Integrationen automatisch ausfüllen** | Eine externe API (REST) | Eine Live-Abfrage bei einem externen Dienst, z. B. dem CRM Ihres Unternehmens |

Jedes Formular verwendet jeweils nur einen dieser drei Modi; der Modus wird in den allgemeinen Einstellungen des jeweiligen Formulars festgelegt.

## Was sind die Voraussetzungen?

- Eine aktive, gültige **Pro**-Lizenz für Easy Form Builder. Ohne aktive oder nicht abgelaufene Pro-Lizenz zeigen beide Einstellungsseiten zum automatischen Ausfüllen die Meldung „Pro Version Required" (bzw. einen Ablaufhinweis) an und lassen sich nicht öffnen.
- Die Plugin-Version muss **Version 4.0 oder höher** sein.
- Für die Datensatz-Methode: eine **UTF-8-kodierte** CSV-Datei mit Spaltenüberschriften in der ersten Zeile.
- Für die API-Methode: eine echte Endpunkt-URL, die JSON zurückgibt, sowie bei Bedarf die zugehörigen Authentifizierungsdaten.

## Wie installiere und aktiviere ich Auto-Populate?

Wie andere Add-ons von Easy Form Builder ist das automatische Ausfüllen **standardmäßig deaktiviert**:

1. Gehen Sie im WordPress-Menü zu **Easy Form Builder → Add-ons**.
2. Suchen Sie die Karte „**Add-on zum automatischen Ausfüllen**" (ihre Beschreibung entspricht genau der oben genannten: automatisches Ausfüllen aus Datensätzen, früheren Einreichungen oder externen APIs).
3. **Klicken Sie auf den Button „Installieren“.** Dadurch werden die benötigten Add-on-Dateien heruntergeladen und gleichzeitig aktiviert — ein separater Schritt „Einstellungen speichern" ist nicht erforderlich.
4. Nach der Aktivierung wird aus demselben Button „**Entfernen!**"; klicken Sie erneut darauf, um das Add-on zu deaktivieren.

<blockquote>
<strong>Hinweis:</strong> Ist Ihre Easy-Form-Builder-Version älter als die für dieses Add-on erforderliche Mindestversion, zeigt ein Klick auf „Installieren" statt der Aktivierung eine Plugin-Update-Meldung an. Je nach Ihrem Pro-Tarif kann die Aktivierung dieses Add-ons zudem ein Upgrade des Tarifs erfordern; in diesem Fall zeigt der Lizenzserver nach dem Installationsversuch die entsprechende Meldung an.
</blockquote>

## Wie erstelle und verwalte ich einen Datensatz (CSV-Datei)?

1. Gehen Sie zu **Easy Form Builder → Datensatz automatisch füllen**.
2. Wählen Sie über den Button „**CSV-Datei hochladen**“ eine CSV-Datei aus (UTF-8, mit Spaltenüberschriften in der ersten Zeile) und laden Sie sie hoch.
3. Der neu erstellte Datensatz erscheint in der Tabelle „**Datensätze**".

Jeden Datensatz können Sie auf derselben Seite verwalten:

- **Umbenennen**.
- **Duplikat** — erstellt eine neue Kopie mit dem Suffix `_copy`.
- **Löschen**.
- **Werte direkt in der Tabelle bearbeiten** — klicken Sie auf einen beliebigen Wert, um ihn direkt in der Tabelle zu bearbeiten; Änderungen gelten sofort für neue Formulareinreichungen, ein erneuter CSV-Upload ist nicht nötig.

> Die Spalten eines Datensatzes entsprechen genau den Spaltenüberschriften Ihrer CSV-Datei und werden bei der Feldzuordnung im Formularbuilder unter denselben Namen angezeigt.

## Wie aktiviere ich Auto-Populate mit einem Datensatz auf einem Formular?

1. Öffnen Sie das gewünschte Formular im Formularbuilder.
2. Aktivieren Sie in den **allgemeinen Formulareinstellungen** (nicht bei einem einzelnen Feld) die Option „**Automatisch ausfüllen ermöglichen**".
3. Daraufhin erscheint ein Dropdown-Menü. Seine erste Option ist bereits mit dem Text „**Automatisches Ausfüllen anhand zuvor übermittelter Formulare**" ausgewählt — lassen Sie diese Option unverändert, wird der Modus „frühere Einreichungen" aktiviert (siehe nächster Abschnitt). Um stattdessen einen Datensatz zu verwenden, wählen Sie im selben Menü eine der Optionen „**Datensatz: Datensatzname**" aus.
4. Nach Auswahl eines Datensatzes erscheint ein Bereich zur Definition der Suchbedingung:
   - Wählen Sie ein Formularfeld als „Suchfeld" aus (z. B. ein Feld, in das der Besucher eine Mitarbeiter- oder Ausweisnummer einträgt). Auswählbar sind nur die Feldtypen Text, Datum, E-Mail, Zahl, Telefon, URL, Passwort, Auswählen (Select), Checkbox und Optionsfeld (Radio) sowie Mobilnummer.
   - Wählen Sie daneben die Datensatz-Spalte aus, mit der der eingegebene Wert abgeglichen werden soll.
   - Über den Button „+" können Sie mehr als eine Suchbedingung hinzufügen (z. B. gleichzeitiger Abgleich von Ausweisnummer und Geburtsdatum).
5. Öffnen Sie für jedes Feld, das automatisch ausgefüllt werden soll, dessen Feldeinstellungen, aktivieren Sie „**Automatisches Ausfüllen aktivieren, um dieses Feld automatisch auszufüllen**", und wählen Sie die zugehörige Datensatz-Spalte aus.
6. Speichern Sie das Formular.

## Wie funktioniert das Ausfüllen aus früheren Einreichungen des Formulars?

Dieser Modus wird aktiv, wenn Sie „Automatisch ausfüllen ermöglichen" in den Formulareinstellungen aktivieren, aber das Dropdown-Menü auf seiner Standardoption — „**Automatisches Ausfüllen anhand zuvor übermittelter Formulare**" — belassen, ohne einen Datensatz auszuwählen.

In diesem Modus durchsucht das Add-on statt einer CSV-Datei die **zuvor übermittelten Einreichungen desselben Formulars**, die in Ihrer WordPress-Datenbank gespeichert sind. Stimmt der Wert des Suchfelds (z. B. E-Mail-Adresse oder Tracking-Code) mit einer früheren Einreichung überein, werden die übrigen Felder mit aktiviertem automatischem Ausfüllen aus genau dieser früheren Einreichung befüllt.

**Typischer Anwendungsfall:** wiederkehrende Besucher eines Formulars (ein Serviceanfrage- oder Mitgliedschaftsformular, zum Beispiel), die bereits übermittelte Informationen nicht erneut von Grund auf eintippen sollen.

> Wesentlicher Unterschied zum Datensatz-Modus: Hier ist keine Datei und keine externe Datenquelle nötig — die Datenquelle ist das „Gedächtnis" des Formulars selbst.

## Wie erstelle ich eine externe API-Verbindung? (Integrationen automatisch ausfüllen)

Diese Methode ist für den Fall gedacht, dass die benötigten Daten weder in einer CSV-Datei noch in früheren Einreichungen des Formulars vorliegen, sondern in einem externen System (einem CRM, einem ERP oder einer beliebigen anderen REST-API) gespeichert sind und in Echtzeit abgerufen werden müssen.

Gehen Sie zu **Easy Form Builder → Integrationen automatisch ausfüllen** und klicken Sie auf „**Neue API-Verbindung hinzufügen**". Der Assistent umfasst 4 Schritte:

**Schritt 1 — Allgemeine Infos:**

- **Verbindungsname**: ein frei wählbarer Name (z. B. „Kundenabfrage")
- **HTTP-Methode**: GET, POST, PUT oder PATCH
- **URL des API-Endpunkts**: die Adresse der API; Sie können den Platzhalter `{{field_id}}` direkt in der URL verwenden, damit der Wert eines Formularfelds dort eingesetzt wird (z. B. `https://example.com/api/users/{{national_code}}`)
- **Vorlage für den Request-Body (JSON)**: nur für POST/PUT/PATCH; eine JSON-Vorlage, in der Sie ebenfalls `{{field_id}}` verwenden können

**Schritt 2 — Authentifizierung:**

Verfügbare **Authentifizierungstyp**-Optionen: **Keine Authentifizierung**, **API Key**, **Bearer Token**, **Basic Auth** (im Format `username:password`) oder **Individueller Header**. Zusätzlich können Sie beliebige individuelle Header (Schlüssel/Wert) hinzufügen.

**Schritt 3 — Feldzuordnung:**

- **Zielformular**: das Formular, auf das diese Verbindung angewendet wird.
- **Suchfelder (Triggerfelder)**: das/die Formularfeld(er), deren Werte an die API gesendet werden; für jedes können Sie einen benutzerdefinierten **API Parameter**-Namen festlegen (bleibt das Feld leer, wird standardmäßig der Feldname selbst als Parametername verwendet).
- **Antwortdatenpfad**: Liegen die benötigten Daten in einem verschachtelten Schlüssel der JSON-Antwort (z. B. `data.results`), geben Sie den Pfad in Punktnotation an. Bleibt das Feld leer, wird die gesamte Antwort als Quelle betrachtet.
- **Feldzuordnungen**: Verknüpfen Sie jedes Feld der API-Antwort mit einem Formularfeld.
- **Cache-Dauer (Minuten)**: Dauer der Zwischenspeicherung der Antwort (0 bedeutet kein Caching).

**Schritt 4 — Testen & Sparen:**

- Geben Sie einen Beispielwert für das/die Suchfeld(er) ein und klicken Sie auf **Verbindung prüfen**, um die tatsächliche API-Antwort und die Feldzuordnung vor der Veröffentlichung zu prüfen.
- Bei Erfolg speichern Sie die Verbindung mit **Speichern**.

## Wie wird die API-Verbindung auf einem Formular aktiviert?

Anders als beim Datensatz-Modus gibt es hier **keinen separaten Schalter oder ein eigenes Dropdown-Menü im Formularbuilder** zur Aktivierung des API-Modus. Die Aktivierung erfolgt vollständig im Assistenten „Integrationen automatisch ausfüllen" selbst:

1. Wählen Sie in Schritt 3 des Assistenten (Feldzuordnung) das gewünschte Formular aus dem Menü **Zielformular** aus.
2. Legen Sie Suchfelder und Feldzuordnungen im selben Schritt fest (siehe vorherigen Abschnitt).
3. Mit einem Klick auf **Speichern** in Schritt 4 schreibt das Add-on automatisch die nötigen Einstellungen (aktivierter API-Modus, Verbindungs-ID, Suchfelder und Zielfelder) direkt in die Struktur dieses Formulars.
4. Öffnen Sie nun dasselbe Formular im Formularbuilder, erscheint einmalig auf Ebene der Formulareinstellungen (nicht bei jedem einzelnen Feld) eine große Karte mit der Meldung **"API AutoFill Integration is Active"**, mit einem direkten Link zur Integrationen-Seite. Diese Karte ist lediglich eine **Bestätigung bereits gespeicherter Einstellungen**, kein veränderbarer Schalter.
5. Felder, die im Rahmen der Feldzuordnungen dieser Verbindung als Ziel definiert sind, erhalten im Formularbuilder ein kleines Abzeichen mit dem Text „**Automatisch ausgefüllt über externe API**", damit erkennbar ist, welche Felder automatisch ausgefüllt werden.

> Sprachhinweis: Der Text der Bestätigungskarte ("API AutoFill Integration is Active") und die Beschreibung darunter fehlen derzeit in der deutschen Übersetzungsdatei; deshalb erscheinen sie selbst im deutschsprachigen Dashboard genau in dieser englischen Form.

## Wie verhält sich Auto-Populate im Frontend, und was bewirkt das Caching?

Das Verhalten auf Besucherseite ist in allen drei Modi (Datensatz, frühere Einreichungen und externe API) identisch:

1. Gibt ein Besucher einen Wert in das „Suchfeld" ein und verlässt es — per **Tab**, **Enter** oder durch Klicken außerhalb des Feldes (**Blur**) —, sendet der Browser eine Anfrage an den internen REST-Endpunkt `wp-json/Emsfb/v1/autofill/get`.
2. Je nach gewähltem Modus durchsucht der Server den Datensatz, die früheren Einreichungen oder (durch tatsächlichen Aufruf der API) die Antwort des externen Dienstes nach einem passenden Wert.
3. Wird eine Übereinstimmung gefunden, werden die zugeordneten Felder sofort (ohne Neuladen der Seite) ausgefüllt, wobei kurz ein Ladeindikator im Feld selbst angezeigt wird.
4. Gibt es keine Übereinstimmung, zeigt das Formular eine passende Meldung an, und die Felder bleiben leer.

Im Modus **externe API** wird die Antwort, sofern die Cache-Dauer größer als null eingestellt ist, für diesen Zeitraum zwischengespeichert — das beschleunigt sowohl das Laden des Formulars als auch die Vermeidung des Rate-Limits des externen Dienstes.

## Sicherheitshinweise und Best Practices

- Verwenden Sie für den API-Endpunkt immer **HTTPS**-Adressen, damit die Daten bei der Übertragung verschlüsselt sind.
- Halten Sie API-Schlüssel und Tokens vertraulich; diese Werte werden in Ihren WordPress-Einstellungen gespeichert.
- Setzen Sie für häufig abgerufene Daten die **Cache-Dauer** auf einen Wert größer als null, um sowohl die Anzahl der API-Aufrufe zu reduzieren als auch das Formular schneller laden zu lassen.
- Beachten Sie, dass Anfragen an die externe API mit einem festen Timeout von 30 Sekunden ausgeführt werden, der sich im Assistenten nicht einstellen lässt; stellen Sie sicher, dass Ihre API innerhalb dieser Zeit antwortet, sonst schlägt die Anfrage fehl.
- Befüllen Sie in sensiblen Formularen (z. B. Quiz- oder Umfrageformularen) niemals eine richtige Antwort oder vertrauliche Informationen automatisch in ein für den Besucher sichtbares Feld.

## Häufige Fehler und ihre Lösungen

| Fehler | Wahrscheinliche Ursache | Lösung |
|---|---|---|
| „Pro Version Required" beim Öffnen der Seiten zum automatischen Ausfüllen | Pro-Lizenz ist nicht aktiv oder abgelaufen | Aktivieren oder verlängern Sie die Pro-Lizenz |
| Die Schaltfläche Installieren zeigt nur eine Update-Meldung | Die Easy-Form-Builder-Version ist älter als die für dieses Add-on erforderliche Mindestversion | Aktualisieren Sie das Plugin auf die neueste Version |
| Felder werden nicht ausgefüllt, Meldung „keine Daten gefunden" erscheint | Der eingegebene Wert stimmt mit keiner Datensatz-Zeile, früheren Einreichung oder API-Antwort überein | Prüfen Sie Testwert und Suchbedingung; kontrollieren Sie im Datensatz-Modus erneut den ausgewählten Spaltennamen |
| Verbindungsfehler (Code 500 oder Netzwerkfehler) im API-Modus | Die Endpunkt-URL ist ungültig, oder der Server ist nicht erreichbar | Prüfen Sie die URL und testen Sie erneut über den Button „Verbindung prüfen“ |
| HTTP-Statuscode 400 oder höher von der API | Der externe Dienst selbst hat einen Fehler zurückgegeben (z. B. 404 oder 401) | Überprüfen Sie Endpunkt, Parameter und Authentifizierungsdaten |
| Parse-Fehler (ungültige Antwort) | Die API hat eine Nicht-JSON-Antwort zurückgegeben (z. B. HTML) | Stellen Sie sicher, dass der Endpunkt tatsächlich JSON zurückgibt |
| API-Aufruf erfolgreich, aber zugeordnete Felder bleiben leer | Antwortdatenpfad ist falsch eingestellt, oder Feldzuordnungen stimmen nicht mit den tatsächlichen Antwortschlüsseln überein | Vergleichen Sie den Antwortpfad und die genauen Feldzuordnungs-Schlüssel mit der Rohantwort der API |

## Checkliste vor der Veröffentlichung

- [ ] Easy Form Builder ist Version 4.0 oder höher.
- [ ] Die Pro-Lizenz ist aktiv und gültig.
- [ ] Das Add-on „Automatisch ausfüllen" ist über die Add-ons-Seite installiert und aktiv.
- [ ] Für den Datensatz-Modus: Die CSV-Datei ist UTF-8-kodiert mit korrekten Spaltenüberschriften, und Beispielwerte wurden geprüft.
- [ ] Für den API-Modus: Die Verbindung wurde mit einem echten Wert über Verbindung prüfen getestet.
- [ ] Suchfeld(er) und Zielfelder sind im Formularbuilder korrekt zugeordnet.
- [ ] Das veröffentlichte Formular wurde im Frontend mit einem Testwert geprüft, und das automatische Ausfüllen der Felder wurde bestätigt.
- [ ] Für den API-Modus: Die Cache-Dauer ist entsprechend dem tatsächlichen Bedarf eingestellt, und Sie haben bestätigt, dass Ihre API innerhalb des festen 30-Sekunden-Timeouts des Assistenten antwortet.

## Häufig gestellte Fragen

### Ist Auto-Populate in der kostenlosen Version von Easy Form Builder verfügbar?

Nein. Dieses Add-on ist nur in der Pro-Version verfügbar; ohne aktive Pro-Lizenz bleiben die Einstellungsseiten gesperrt.

### Kann ich auf einem Formular gleichzeitig einen Datensatz und eine externe API verwenden?

Nein. Die Einstellung zum automatischen Ausfüllen auf Formularebene kennt nur einen Modus zu einer Zeit (Datensatz/frühere Einreichungen oder externe API); für jedes Formular muss einer dieser Modi gewählt werden.

### Erfordert das Ändern von Datensatz-Werten einen erneuten CSV-Upload?

Nein; Sie können die Werte direkt in der Tabelle „Datensätze" bearbeiten, und die Änderungen gelten sofort für neue Einreichungen.

### Welche Feldtypen können als „Suchfeld" ausgewählt werden?

Die Feldtypen Text, E-Mail, Datum, Zahl, Telefon, URL, Passwort, Auswählen (Select), Checkbox und Optionsfeld (Radio) sowie Mobilnummer.

### Unterstützen Integrationen zum automatischen ausfüllen eine Authentifizierung?

Ja; API Key, Bearer Token, Basic Auth und individuelle Header werden alle unterstützt.

### Wie wird der externe API-Modus auf einem bestimmten Formular aktiviert?

Vollständig innerhalb des Assistenten „Integrationen automatisch ausfüllen" — durch Auswahl des Zielformulars im Schritt Feldzuordnung und Klick auf Speichern. Es gibt keinen separaten Schalter oder ein eigenes Dropdown-Menü dafür im Formularbuilder; dieser zeigt nur eine Bestätigungskarte und ein Feld-Abzeichen an.

### Wie wird das Add-on selbst aktiviert — gibt es einen Ein-/Ausschalter?

Nein. Dieses Add-on wird durch einen einzigen Klick auf den Button „**Installieren**“ auf seiner Karte unter Easy Form Builder → Add-ons aktiviert. Installation und Aktivierung erfolgen gleichzeitig, ein separater „Speichern"-Schritt ist nicht nötig.

## Vorgeschlagene FAQ-strukturierte-Daten für die Veröffentlichung

Verwenden Sie diesen Block nur, wenn das SEO-Plugin Ihrer Website nicht bereits ein vergleichbares FAQ-Schema erzeugt. Die Fragen und Antworten müssen auf der veröffentlichten Seite sichtbar bleiben (entsprechend dem Abschnitt „Häufig gestellte Fragen" oben).

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Ist Auto-Populate in der kostenlosen Version von Easy Form Builder verfügbar?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Nein. Auto-Populate ist ausschließlich in der Pro-Version von Easy Form Builder verfügbar. Ohne aktive Pro-Lizenz bleiben die Einstellungsseiten gesperrt."
      }
    },
    {
      "@type": "Question",
      "name": "Kann ich auf einem Formular gleichzeitig einen Datensatz und eine externe API verwenden?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Nein. Die Einstellung zum automatischen Ausfüllen auf Formularebene unterstützt jeweils nur einen Modus: Datensatz, frühere Einreichungen oder externe API. Jedes Formular darf nur einen dieser Modi verwenden."
      }
    },
    {
      "@type": "Question",
      "name": "Erfordert das Ändern von Datensatz-Werten einen erneuten CSV-Upload?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Nein. Datensatz-Werte können direkt in der Tabelle Datensätze bearbeitet werden, und Änderungen gelten sofort für neue Formulareinreichungen."
      }
    },
    {
      "@type": "Question",
      "name": "Welche Feldtypen können als Suchfeld in Auto-Populate verwendet werden?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Die Feldtypen Text, E-Mail, Datum, Zahl, Telefon, URL, Passwort, Auswählen (Select), Checkbox und Optionsfeld (Radio) sowie Mobilnummer können alle als Suchfeld verwendet werden."
      }
    },
    {
      "@type": "Question",
      "name": "Unterstützen Integrationen automatisch ausfüllen die API-Authentifizierung?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Ja. Integrationen automatisch ausfüllen unterstützt API Key, Bearer Token, Basic Auth und individuelle Header zur Authentifizierung von Anfragen an den externen Dienst."
      }
    },
    {
      "@type": "Question",
      "name": "Wie wird der externe API-Modus auf einem bestimmten Formular aktiviert?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Vollständig innerhalb des Assistenten Integrationen automatisch ausfüllen, durch Auswahl des Zielformulars im Schritt Feldzuordnung und Klick auf Speichern. Es gibt dafür keinen separaten Schalter im Formularbuilder."
      }
    },
    {
      "@type": "Question",
      "name": "Wie wird das Auto-Populate-Add-on in Easy Form Builder aktiviert?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Klicken Sie unter Easy Form Builder, Add-ons, auf den Button „Installieren“ auf der Karte des Add-ons zum automatischen Ausfüllen. Installation und Aktivierung erfolgen gleichzeitig, ein separater Speicherschritt ist nicht erforderlich."
      }
    }
  ]
}
</script>
```

## Editorial SEO notes

- **Primary search intent:** Lernen, wie man Auto-Populate in Easy Form Builder installiert, aktiviert und nutzt.
- **Recommended title tag:** Auto-Populate in Easy Form Builder: Der komplette Leitfaden (WordPress-Formular automatisch ausfüllen)
- **Recommended URL:** `/easy-form-builder-auto-populate/`
- **Recommended excerpt:** WordPress-Formularfelder automatisch aus einem Datensatz, früheren Einreichungen oder einer externen API ausfüllen — der komplette Leitfaden zum Auto-Populate-Add-on von Easy Form Builder.
- **Suggested internal links:** Easy-Form-Builder-Installationsanleitung, Testleitfaden für Integrationen automatisch ausfüllen, Conditional-Logic-Dokumentation (für das Setzen eines Werts aus einer Datensatz-Spalte), Google-Sheet-Integrationsleitfaden.
- **Suggested image alt text:** Einstellungsbildschirm zum automatischen Ausfüllen im WordPress-Admin-Bereich von Easy Form Builder.
