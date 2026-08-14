---
title: "So nutzt du den E-Mail-Template-Builder in Easy Form Builder: 13 Blöcke, 6 Vorlagen und dynamische Shortcodes"
slug: "easy-form-builder-email-template-builder-guide-de"
meta_description: "Lerne den E-Mail-Template-Builder von Easy Form Builder Schritt für Schritt kennen: wo du ihn findest, alle 13 Blocktypen, die 6 vorgefertigten Templates, die 5 dynamischen Shortcodes, globale Schrift- und Farbeinstellungen, das Speicherlimit von 50.000 Zeichen und auf welche E-Mails dein Design genau angewendet wird."
focus_keyphrase: "WordPress Formular E-Mail-Template"
secondary_keyphrases:
  - "Benachrichtigungs-E-Mail für Formular gestalten"
  - "Easy Form Builder E-Mail-Shortcode"
  - "HTML-E-Mail-Template WordPress erstellen"
  - "Easy Form Builder E-Mail-Blöcke"
  - "WordPress Registrierungsbestätigung E-Mail-Template"
search_intent: "Anleitungs- und Konfigurationsleitfaden"
audience: "WordPress-Website-Betreiber und Administratoren, die Easy Form Builder nutzen"
product_version: "Easy Form Builder 4.1.2 und höher"
last_reviewed: "2026-08-13"
---

# So nutzt du den E-Mail-Template-Builder in Easy Form Builder

**Schlüsselwörter:** WordPress Formular E-Mail-Template, Benachrichtigungs-E-Mail für Formular gestalten, Easy Form Builder E-Mail-Shortcode, HTML-E-Mail-Template WordPress erstellen, Easy Form Builder E-Mail-Blöcke, WordPress Registrierungsbestätigung E-Mail-Template.

**Pfad:** Easy Form Builder Dokumentation › E-Mail-Template-Builder › Kompletter Leitfaden · Sprachen: [فارسی](EFB-Email-Template-Builder-Complete-Guide.fa.md) | [English](EFB-Email-Template-Builder-Complete-Guide.en.md) | Deutsch

Jede E-Mail, die Easy Form Builder versendet — eine Benachrichtigung über eine neue Nachricht, eine Registrierungs-Willkommens-E-Mail, ein Zurücksetzen des Passworts — durchläuft ein gemeinsames Template. Im **E-Mail-Template-Builder** gestaltest du, wie dieses gemeinsame Template aussieht, indem du Blöcke per Drag-and-drop anordnest, ganz ohne eine Zeile Code zu schreiben.

Diese Anleitung beschreibt genau das, was im Code des Plugins tatsächlich implementiert ist: welche Optionen jeder Block hat, wo jeder Shortcode ersetzt wird, wo die Grenzen liegen und auf welche E-Mails dein Design wirklich angewendet wird.

> **Umfang dieser Dokumentation:** Jeder Block, jedes Template, jeder Shortcode, jede Zahl und jedes Verhalten in diesem Leitfaden wurde direkt aus dem aktuellen Code des Plugins gelesen — einschließlich eines Abgleichs jeder Zahl sowohl clientseitig (JavaScript) als auch serverseitig (PHP), um Übereinstimmung sicherzustellen. Hier steht nichts geraten.

## Kurzantwort

- Pfad: **Bedienfeld → oberes Menü, Einstellungen → Tab E-Mail-Template** (5. von 8 Tabs). Ausführliche Navigationsdetails findest du im [Leitfaden zur Response Box](../responsebox/EFB-Response-Box-Complete-Guide.en.md#where-the-settings-are), da beide Tabs auf derselben Seite liegen.
- Auch diese Einstellung ist **global**: ein Template für alle Formulare der Website.
- **13 Blocktypen** in vier Kategorien: Layout, Inhalt, Shortcode, Erweitert.
- **6 vorgefertigte Templates** als Ausgangspunkt: Leer, Professionell (Standard), Modern Dunkel, Minimalistisch und klar, Elegant, Bunt.
- **5 dynamische Shortcodes**; nur `shortcode_message` ist Pflicht.
- Kein Bild-Upload — die Blöcke Bild und Logo akzeptieren nur eine **URL**.
- Speichergrenze: maximal **50.000 Zeichen**, und `shortcode_message` muss vorhanden sein.
- Öffnest du diesen Tab nie, nutzen die E-Mails deiner Website weiterhin das ursprüngliche, eingebaute Design des Plugins — kein leeres Template.
- Dein Design erscheint nur in **tatsächlich ausgehenden E-Mails**; das Werkzeug „E-Mail-Server prüfen" im Tab E-Mail-Einstellungen verwendet dein Template nicht.

## Inhaltsverzeichnis

- [Wo sich der Builder befindet und wie er sich öffnet](#wo-sich-der-builder-befindet-und-wie-er-sich-öffnet)
- [Der Aufbau des Builders](#der-aufbau-des-builders)
- [Die 13 Blocktypen](#die-13-blocktypen)
- [Die 6 vorgefertigten Templates](#die-6-vorgefertigten-templates)
- [Die 5 dynamischen Shortcodes](#die-5-dynamischen-shortcodes)
- [Globale Einstellungen](#globale-einstellungen)
- [Symbolleiste und Tastenkombinationen](#symbolleiste-und-tastenkombinationen)
- [Grenzen und Validierung beim Speichern](#grenzen-und-validierung-beim-speichern)
- [Auf welche E-Mails dieses Design genau angewendet wird](#auf-welche-e-mails-dieses-design-genau-angewendet-wird)
- [Wenn du diesen Tab nie öffnest](#wenn-du-diesen-tab-nie-öffnest)
- [Sicherheit: was entfernt wird](#sicherheit-was-entfernt-wird)
- [Problembehandlung](#problembehandlung)
- [Häufig gestellte Fragen](#häufig-gestellte-fragen)

## Wo sich der Builder befindet und wie er sich öffnet

Pfad: **Bedienfeld → oberes Menü des Bedienfelds, Einstellungen → Tab E-Mail-Template**.

Die vollständige Reihenfolge der acht Einstellungs-Tabs, damit du ihn schneller findest: Allgemein · Antworten & Bestätigung · Captchas · E-Mail-Einstellungen · **E-Mail-Template** · Lokalisierung · Zahlungen · SMS-Konfiguration.

Beim ersten Öffnen dieses Tabs ist die Arbeitsfläche nicht leer — der Builder lädt automatisch das Template **Professionell**, damit du nicht vor einer leeren Seite sitzt. Das ist aber nur ein Startpunkt im Editor, **kein automatisches Speichern**; der Abschnitt [Wenn du diesen Tab nie öffnest](#wenn-du-diesen-tab-nie-öffnest) erklärt weiter unten, warum dieser Unterschied wichtig ist.

Wie bei jedem anderen Einstellungs-Tab ist der **Speichern**-Button am unteren Seitenrand für alle Tabs gemeinsam; Änderungen auf diesem Tab sind erst nach dem Klick darauf dauerhaft.

## Der Aufbau des Builders

Der Builder ist ein dreispaltiger Arbeitsbereich, ähnlich modernen Seiten-Builder-Editoren:

- **Obere Symbolleiste:** Rückgängig machen, Wiederholen, Vorschau, Export, Rohcode-Editor für HTML und Zurücksetzen.
- **Linke Spalte:** drei interne Tabs — **Blöcke** (die ziehbare Blockpalette), **Templates** (die 6 vorgefertigten Templates) und **Einstellungen** (globale Farb- und Schrifteinstellungen).
- **Mittlere Spalte (Arbeitsfläche):** die E-Mail, die du gerade gestaltest; ziehe Blöcke von der linken Spalte hierher oder klicke auf einen Block, um ihn am Ende hinzuzufügen.
- **Rechte Spalte:** das Eigenschaften-Panel — wählst du einen Block auf der Arbeitsfläche aus, erscheinen hier seine bearbeitbaren Felder.

Jeder Block auf der Arbeitsfläche hat vier kleine Buttons: nach oben, nach unten, duplizieren, löschen. Das dreispaltige Layout ist responsiv bis etwa 900px Breite; für ernsthafte Template-Arbeit solltest du ein Browserfenster in Desktop-Breite verwenden.

## Die 13 Blocktypen

Die Blockpalette gruppiert die Blöcke in vier Kategorien:

### Layout (5 Blöcke)

| Block | Zweck | Bearbeitbare Eigenschaften |
| --- | --- | --- |
| **Header** | Der Container oben in der E-Mail; enthält standardmäßig ein Logo und einen Titel | Hintergrundfarbe, benutzerdefinierter CSS-Hintergrund (für Farbverläufe), Innenabstand, Ausrichtung; die enthaltenen Elemente (Logo/Titel) lassen sich vom selben Panel aus bearbeiten |
| **Trenner** | Eine dünne horizontale Linie | Farbe, Dicke (1–10px), Breite (10–100%), Innenabstand |
| **Abstandshalter** | Leerer vertikaler Raum | Höhe (5–100px), Hintergrundfarbe |
| **Zwei Spalten** | Linker und rechter Text nebeneinander | Inhalt jeder Spalte (mit Shortcodes), Textfarbe je Spalte, Schriftart, Schriftgröße, Abstand zwischen den Spalten, Hintergrund, Innenabstand |
| **Fußzeile** | Der Text am unteren Ende der E-Mail | Text (mit Shortcodes), Textfarbe, Hintergrund, Schriftart, Größe, Ausrichtung, Innenabstand |

> **RTL-Hinweis:** Der Block „Zwei Spalten" hat immer eine feste „linke Spalte" und „rechte Spalte" — er spiegelt sich für persische/arabische Websites nicht automatisch. Für eine Leserichtung von rechts nach links musst du den Inhalt der beiden Spalten selbst vertauschen.

### Inhalt (6 Blöcke)

| Block | Zweck | Bearbeitbare Eigenschaften |
| --- | --- | --- |
| **Logo** | Das Logo-Bild deiner Website | Bild-URL, Breite in px, Alt-Text, Ausrichtung |
| **Titel** | Die große Überschrift der E-Mail | Text (mit Shortcodes), Farbe, Schriftart, Größe, Schriftstärke (300–800), Ausrichtung |
| **Text-Block** | Ein freier Absatztext | Text (mit Shortcodes), Farbe, Schriftart, Größe, Zeilenhöhe (1–3), Ausrichtung, Innenabstand |
| **Button** | Ein Call-to-Action | Text, Link-URL (beide mit Shortcodes), Hintergrundfarbe, Textfarbe, Randradius (0–50px), innerer und äußerer Innenabstand, Schriftart, Größe, Ausrichtung |
| **Bild** | Ein beliebiges Bild | Bild-URL, Alt-Text, Breite (% oder px), Ausrichtung, Innenabstand, optionale Link-URL |
| **Social Links** | Eine Reihe von Social-Media-Icons | Ausrichtung, Icon-Farbe, Icon-Größe (16–48px), Innenabstand; pro Link: Auswahl aus **21 integrierten Icons** (Facebook, X, Instagram, LinkedIn, YouTube, TikTok, WhatsApp, Telegram, Pinterest, Snapchat, GitHub, Dribbble, Reddit, Discord, Twitch, Medium, Spotify, Behance, Vimeo, Website, E-Mail) oder ein **benutzerdefinierter SVG**-Code, plus die Link-URL |

### Shortcode (1 Block, Pflicht)

| Block | Zweck |
| --- | --- |
| **Inhalt der Nachricht \*** | Gibt `shortcode_message` aus — die tatsächlich übermittelten Formulardaten. Dieser Block trägt das Pflicht-Sternchen; ohne ihn funktionieren weder die Vorschau noch das Speichern |

### Erweitert (1 Block)

| Block | Zweck |
| --- | --- |
| **Individuelles HTML** | Dein eigener HTML-Code für alles, was die eingebauten Blöcke nicht abdecken; `<script>`-Tags sind darin nicht erlaubt |

Ein Klick auf einen Block in der Palette fügt ihn ebenfalls am Ende der Arbeitsfläche hinzu — Ziehen ist nicht zwingend nötig.

**Zu Bild-URLs:** Die Blöcke Logo und Bild haben keinen „Hochladen"-Button und keine Verbindung zur WordPress-Mediathek — nur ein Textfeld für eine URL. Um ein Bild zu verwenden, lade es zuerst irgendwo hoch (zum Beispiel in die WordPress-Mediathek), kopiere die Adresse und füge sie in das Feld ein.

## Die 6 vorgefertigten Templates

Der Tab **Templates** in der linken Spalte bietet sechs vorgefertigte Startpunkte. Ein Klick auf eines ersetzt die aktuelle Arbeitsfläche (diese Aktion wird ebenfalls im Verlauf für „Rückgängig machen" erfasst, sodass Strg+Z einen versehentlichen Klick rückgängig macht):

| Template | Anzahl Blöcke | Look and Feel |
| --- | --- | --- |
| **Leer** | 1 Block | Nur der Block „Inhalt der Nachricht"; Start von null |
| **Professionell** | 5 Blöcke | Lila-blauer Header mit Logo und Titel, Nachricht, heller Footer mit Website-Name und Admin-E-Mail; **Standard beim ersten Öffnen** |
| **Modern Dunkel** | 5 Blöcke | Durchgehend dunkler Hintergrund, dunkelgrauer Header, lila „Website besuchen"-Button, dunklerer Footer |
| **Minimalistisch und klar** | 8 Blöcke | Heller Hintergrund, kein farbiger Header — nur ein kleines Logo, ein Titel, ein dünner farbiger Trenner, dann die Nachricht und ein abgerundeter Button |
| **Elegant** | 6 Blöcke | Marineblauer Header mit dünnem Titel (Schriftstärke 300), ein Button mit scharfen Ecken und „→"-Pfeil, nirgends ein Randradius |
| **Bunt** | 6 Blöcke | Kräftiger lila Header, hellvioletter Hintergrund für die Nachricht, ein vollständig abgerundeter Button, eine Reihe von Social-Icons (standardmäßig Facebook/X/Instagram) |

Die Wahl eines Templates ändert nur den Startpunkt; danach kannst du jeden Block einzeln bearbeiten, verschieben, duplizieren oder löschen — Templates sind nicht gesperrt.

## Die 5 dynamischen Shortcodes

Shortcodes sind die Stellen, an denen echte Daten (die Nachricht des Formulars, der Titel, der Website-Name …) in dein Design gelangen. Im Eigenschaften-Panel jedes Textfeld-Blocks lässt dich eine Reihe kleiner Buttons unter dem Textfeld jeden Shortcode sofort einfügen; die Blockpalette enthält zusätzlich einen Abschnitt „Shortcode-Referenz" mit allen Shortcodes samt Einfüge- und Kopier-Buttons.

| Shortcode | Pflicht? | Wird ersetzt durch |
| --- | --- | --- |
| `shortcode_message` | **Ja** | Die tatsächlich übermittelten Formulardaten (Felder und ihre Werte) |
| `shortcode_title` | Nein | Der Titel der E-Mail (z. B. „Neue Nachricht" oder „Willkommen!" bei einer Registrierungs-E-Mail) |
| `shortcode_website_name` | Nein | Der Name deiner Website |
| `shortcode_website_url` | Nein | Die URL der Startseite deiner Website |
| `shortcode_admin_email` | Nein | Die E-Mail-Adresse des Haupt-Administrators der Website (Benutzer-ID 1) |

Fügst du einen Shortcode in ein Textfeld ein, erscheint er als nicht bearbeitbarer **Chip**, nicht als Rohtext — so kann ein Tippfehler in der Nähe nicht versehentlich einen Buchstaben mitten in `shortcode_message` löschen oder verändern und ihn stillschweigend kaputt machen.

Entfernst du den Block „Inhalt der Nachricht" (der `shortcode_message` enthält) von der Arbeitsfläche oder löschst du den Shortcode-Text aus einem anderen Block, in dem er stand, werden sowohl der Vorschau-Button als auch das eigentliche Speichern mit einer Fehlermeldung blockiert, bis du ihn wieder hinzufügst.

## Globale Einstellungen

Der dritte Tab in der linken Spalte (**Einstellungen**) wirkt sich auf die gesamte E-Mail aus, nicht nur auf einen Block:

| Einstellung | Standard | Was sie bewirkt |
| --- | --- | --- |
| E-Mail-Hintergrund | `#f8f9fa` | Die Farbe außerhalb der Hauptkarte (der umgebende Bereich im E-Mail-Programm) |
| Inhalts-Hintergrund | `#ffffff` | Die Farbe der Hauptkarte der E-Mail selbst |
| Inhaltsbreite (px) | `600` | Die Breite der Hauptkarte |
| Randradius (px) | `8px` | Die Rundung der Ecken der Hauptkarte |
| Standardschrift | Segoe UI und deren Ersatzschriften | Die Schriftart, die jeder Block ohne eigene Schrifteinstellung verwendet |
| Richtung | abhängig von der Website-Sprache | `ltr` oder `rtl`; ändert nur die grundlegende Textrichtung, kippt aber nicht automatisch die Ausrichtungseinstellung jedes einzelnen Blocks |
| Button-Hintergrund | `#202a8d` | Die Standardfarbe neu erstellter Buttons; dieselbe Farbe färbt auch den Bestätigungslink-Button im Standarddesign des Plugins (wenn kein individuelles Template existiert) |
| Button-Textfarbe | `#ffffff` | Die Textfarbe auf diesen Buttons |

**Nur E-Mail-sichere Schriftarten:** Anders als der Farb-Builder der Response Box, der herunterladbare Google Fonts anbietet, ist diese Schriftliste bewusst auf **15 standardmäßige, E-Mail-sichere Schriftarten** begrenzt (Segoe UI, Arial, Helvetica, Verdana, Tahoma, Trebuchet MS, Lucida Sans, Georgia, Times New Roman, Palatino, Courier New, Lucida Console, Comic Sans MS, Impact, Tahoma für RTL) — Schriftarten, die praktisch jedes E-Mail-Programm (Gmail, Outlook, Apple Mail und so weiter) bereits auf dem System des Nutzers hat, sodass zur Darstellung nichts heruntergeladen werden muss. Eine eigene oder herunterladbare Schriftart gibt es im E-Mail-Template-Builder nicht.

**20 vordefinierte Farbfelder:** Neben jedem Farbwähler (sowohl in den globalen Einstellungen als auch im Eigenschaften-Panel jedes Blocks) erscheinen 20 vordefinierte Farben als kleine anklickbare Quadrate, um schneller zur Markenfarbe zu finden; das sind nur Abkürzungen — der freie Farbwähler und das Hex-Textfeld stehen immer zusätzlich zur Verfügung.

## Symbolleiste und Tastenkombinationen

| Button | Was er bewirkt |
| --- | --- |
| **Rückgängig machen** | Macht eine Änderung rückgängig; behält bis zu **30 Schritte** Verlauf |
| **Wiederholen** | Stellt einen rückgängig gemachten Schritt wieder her |
| **Vorschau** | Öffnet ein Fenster mit Beispieldaten („John Doe", eine Beispiel-E-Mail-Adresse, Beispieltext), die anstelle der Shortcodes stehen; fehlt `shortcode_message` auf der Arbeitsfläche, verweigert die Vorschau die Ausführung |
| **Export** | Lädt eine Datei `email-template.html` mit dem endgültigen HTML herunter |
| **HTML** | Öffnet den HTML-Quellcode-Editor; bearbeite das Markup direkt und klicke auf Übernehmen, um die gesamte Arbeitsfläche damit zu ersetzen (`<script>`-Tags werden abgelehnt) |
| **Zurücksetzen** | Ersetzt nach einer Bestätigungsabfrage im Browser die Arbeitsfläche durch das Template **Professionell** — nicht durch eine vollständig leere Fläche |

Tastenkombinationen (wenn der Fokus nicht in einem Eingabefeld liegt): **Strg+Z** rückgängig, **Strg+Y** oder **Strg+Umschalt+Z** wiederholen, **Entf** entfernt den ausgewählten Block, **Pfeiltasten hoch/runter** ordnen den ausgewählten Block auf der Arbeitsfläche neu an.

## Grenzen und Validierung beim Speichern

Klickst du auf den Speichern-Button der Einstellungsseite, werden diese Regeln **sowohl im Browser als auch erneut serverseitig** geprüft (sodass selbst eine manipulierte Anfrage vom Server abgefangen wird):

- Der gespeicherte Inhalt muss `shortcode_message` enthalten, sonst wird das Speichern mit einer Fehlermeldung abgelehnt.
- Die maximale Länge beträgt **50.000 Zeichen**.
- Ein leerer oder nur wenige Zeichen langer Inhalt wird wie „kein individuelles Template" behandelt.

Zur Einordnung: 50.000 Zeichen reichen in der Regel selbst für ein recht detailliertes Design mit mehreren Dutzend Blöcken und etwas zusätzlichem individuellem HTML mehr als aus.

## Auf welche E-Mails dieses Design genau angewendet wird

Ein Detail, das nicht sofort auffällt: Dein Design verändert nur das äußere Erscheinungsbild (Header, Farben, Schriftarten, Button, Footer) und wird auf **jede Art von E-Mail angewendet, die aus Formular- oder Konto-Aktivität entsteht** — nicht nur auf die „Neue Nachricht"-E-Mail:

- Die Benachrichtigungs-E-Mail über eine neue Formularübermittlung (mit oder ohne Tracking-Link-Button)
- Die E-Mail zur Passwort-Wiederherstellung
- Die E-Mail zur Registrierung/Begrüßung neuer Benutzer

Bei den letzten beiden ist das, was im Nachrichtenbereich landet, keine „Formulardaten" mehr — das Plugin setzt stattdessen seinen eigenen passenden Inhalt (einen Link zum Zurücksetzen des Passworts, einen Bestätigungslink für die Registrierung) in denselben `shortcode_message`-Platzhalter ein, aber die von dir gestalteten Farben, Schriftarten, der Header und der Footer bleiben exakt gleich.

**Hinweis:** Das Werkzeug „E-Mail-Server prüfen" im Tab **E-Mail-Einstellungen** ist eine separate Diagnosefunktion. Es sendet über einen eigenen Codepfad eine feste, einfache Nachricht (rein zum Testen der Zustellbarkeit) und verwendet dein individuelles Template überhaupt nicht — wie ausgefeilt dein Design auch ist, die Nachricht dieses Buttons bleibt schlicht. Um dein tatsächliches Design zu sehen, nutze den **Vorschau**-Button im Builder oder warte auf eine echte E-Mail (zum Beispiel, indem du eines deiner Formulare testweise absendest).

## Wenn du diesen Tab nie öffnest

Hast du auf diesem Tab noch nie etwas gespeichert, laufen die E-Mails deiner Website vollständig über das **eingebaute Standarddesign** des Plugins: ein marineblauer Verlaufs-Header mit dem Easy-Form-Builder-Logo und einem Titel, der Nachrichtentext und ein Footer „Gesendet von [Website-Name]". Dieses eingebaute Design hat nichts mit den Blöcken des Builders zu tun — es ist vollständig im PHP-Code des Plugins selbst festgelegt.

Eine Feinheit, die du kennen solltest: Beim ersten Öffnen des Tabs E-Mail-Template wird das Template „Professionell" in die Arbeitsfläche geladen, damit die Seite nicht leer ist — das ist aber nur eine Vorschau im Editor. An der tatsächlich ausgehenden E-Mail deiner Website ändert sich nichts, bis du den **Speichern**-Button der Einstellungsseite klickst; bis dahin bleibt das eingebaute Standarddesign aktiv.

## Sicherheit: was entfernt wird

Da dieses Werkzeug die Eingabe von rohem HTML/CSS erlaubt (im Block „Individuelles HTML" und im Code-Editor), bereinigt das Plugin Inhalte in mehreren Schichten — einmal während du im Browser tippst, und erneut serverseitig beim Speichern:

- Tags wie `<script>`, `<iframe>`, `<object>`, `<embed>`, `<form>`, `<input>`, `<svg>` und ähnliche werden entfernt.
- Event-Handler-Attribute wie `onclick`/`onerror` werden gelöscht.
- `javascript:`-, `vbscript:`- und `data:text/html`-URLs werden blockiert.
- Ältere CSS-Tricks wie `expression()` und `-moz-binding` werden entfernt.

Diese Bereinigung ist speziell für ein vollständiges HTML-E-Mail-Dokument gebaut (nicht für den üblichen Beitragsinhalts-Filter des Plugins), sodass strukturelle Tags, die eine echte E-Mail braucht — `<html>`, `<head>`, `<style>` — erhalten bleiben; nur die Angriffsvektoren werden entfernt.

## Problembehandlung

**Der Vorschau-Button funktioniert nicht und zeigt einen Fehler.**
Auf deiner Arbeitsfläche fehlt `shortcode_message`. Füge entweder den Block **Inhalt der Nachricht** wieder hinzu, oder stelle, falls du ihn im Block „Individuelles HTML" verwendet hast, sicher, dass der exakte Text `shortcode_message` noch vorhanden ist.

**Das Speichern wird mit einem Hinweis abgelehnt, den Nachrichten-Shortcode hinzuzufügen.**
Dieselbe Regel wird serverseitig erneut geprüft. Das verhindert, dass versehentlich ein Template gespeichert wird, das die echten Formulardaten nie anzeigt.

**Das Logo-Bild wird nicht angezeigt.**
Das Feld für die Bild-URL erwartet einen Link, keine Datei. Lade das Bild zuerst irgendwo hoch (zum Beispiel in die WordPress-Mediathek), kopiere die vollständige öffentliche Adresse und füge sie in das Feld **Bild-URL** ein.

**Ich habe auf Vorschau geklickt, und die echte E-Mail sah anders aus.**
Die Vorschau verwendet Beispieldaten, keine echten Daten. Stimmen Layout und Farben, unterscheidet sich aber der Inhalt, ist das normal — echte Formulardaten füllen nur den `shortcode_message`-Platzhalter in einer tatsächlich ausgehenden E-Mail.

**Ich habe „E-Mail-Server prüfen" verwendet, und mein Design wurde nicht angezeigt.**
Das ist korrekt; dieses Werkzeug misst die Zustellbarkeit von E-Mails, es ist keine Template-Vorschau. Nutze den **Vorschau**-Button im Builder oder sende ein Test-Formular ab, um dein tatsächliches Design zu sehen.

**Ich habe meine Änderungen gespeichert, aber die nächste E-Mail zeigt weiterhin das alte/Standarddesign.**
Prüfe, ob das Speichern tatsächlich abgelehnt wurde (ein fehlendes `shortcode_message` oder eine Überschreitung des Zeichenlimits blockieren das Speichern stillschweigend, wenn du die Fehlermeldung übersiehst). Stelle außerdem sicher, dass du den **Speichern**-Button der Einstellungsseite geklickt hast, nicht nur die Buttons innerhalb des Builders.

**Der Block „Zwei Spalten" wirkt auf meiner persischsprachigen Website vertauscht.**
Dieser Block hat immer eine feste „linke" und „rechte" Spalte — er kippt nicht automatisch mit der Richtung der Website. Ordne den Inhalt der beiden Spalten selbst neu an; beachte außerdem, dass die Einstellung **Richtung** in den globalen Einstellungen nur die grundlegende Textrichtung kippt, nicht das Spaltenlayout.

## Häufig gestellte Fragen

**Gelten diese Einstellungen pro Formular oder global?**
Global. Du gestaltest das Template einmal, und es gilt für die E-Mails aller Formulare der Website — genau wie bei den Einstellungen der Response Box.

**Brauche ich die Pro-Version?**
Nein. Der E-Mail-Template-Builder selbst — alle 13 Blöcke, alle 6 vorgefertigten Templates, die globalen Einstellungen und die Shortcodes — ist vollständig in der kostenlosen Version verfügbar.

**Warum sehe ich beim Öffnen des Tabs schon ein fertiges Design, obwohl ich noch nie etwas gespeichert habe?**
Der Builder lädt das Template „Professionell" als praktischen Startpunkt in den Editor. Bis du speicherst, nutzt die tatsächliche E-Mail deiner Website weiterhin das eingebaute Standarddesign des Plugins, nicht diesen Startpunkt.

**Kann ich einen vollständig eigenen Block „Individuelles HTML" bauen?**
Ja, solange er kein `<script>`-Tag enthält und innerhalb des gesamten Zeichenlimits von 50.000 für das Template bleibt. Anderer gefährlicher Code (on*-Event-Handler, javascript:-URLs und ähnliches) wird automatisch entfernt.

**Kann ich hier eine eigene Marken-Schriftart hinzufügen?**
Nicht im E-Mail-Template-Builder — die Schriftliste ist bewusst auf standardmäßige, E-Mail-sichere Schriftarten begrenzt, damit sie in jedem E-Mail-Programm korrekt dargestellt wird. (Das unterscheidet sich vom Farb-Builder der Response Box, der eine herunterladbare eigene Schriftart akzeptiert, weil es sich dort um eine normale Webseite handelt, nicht um eine E-Mail.)

**Was bewirkt eine Änderung von „Button-Hintergrund" in den globalen Einstellungen?**
Jeden neu erstellten Button auf der Arbeitsfläche — und auch, selbst wenn du nie einen Button-Block hinzufügst, den Bestätigungs-/Tracking-Link-Button, den das Plugin von sich aus entweder in dein individuelles Template oder in das Standarddesign einfügt.

**Was passiert, wenn ich den Block „Inhalt der Nachricht" lösche?**
Sowohl Vorschau als auch Speichern sind blockiert, bis du ihn wieder hinzufügst, weil sonst keine E-Mail jemals die tatsächlichen Formulardaten enthalten würde.
