---
title: "Vollständige Anleitung: E-Mail-Benachrichtigungen für Formulare in Easy Form Builder einrichten"
slug: "easy-form-builder-email-notifications-guide"
meta_description: "Richte in Easy Form Builder E-Mail-Benachrichtigungen für Formulare ein — für den Admin und für die Person, die das Formular ausfüllt —, teste deinen E-Mail-Server und passe das E-Mail-Template an."
focus_keyphrase: "WordPress Formular E-Mail-Benachrichtigung"
secondary_keyphrases:
  - "Easy Form Builder E-Mail-Einstellungen"
  - "WordPress Formular Benachrichtigungs-E-Mail"
  - "WordPress E-Mail-Server testen"
  - "WordPress Formular E-Mail-Template"
search_intent: "Informativer und konfigurationsbezogener Leitfaden"
audience: "WordPress-Website-Betreiber und Administratoren, die Easy Form Builder nutzen"
product_version: "Easy Form Builder 4.1.2 und höher"
last_reviewed: "2026-08-13"
---

# Vollständige Anleitung: E-Mail-Benachrichtigungen für Formulare in Easy Form Builder einrichten

**Schlüsselwörter:** WordPress Formular E-Mail-Benachrichtigung, Easy Form Builder E-Mail-Einstellungen, WordPress Formular Benachrichtigungs-E-Mail, WordPress E-Mail-Server testen, WordPress Formular E-Mail-Template, WordPress Kontaktformular sendet keine E-Mail.

**Breadcrumb:** Easy Form Builder Dokumentation › E-Mail-Benachrichtigungen › Kompletter Leitfaden · Sprachen: [فارسی](EFB-Email-Notifications-Complete-Guide.fa.md) | [English](EFB-Email-Notifications-Complete-Guide.en.md) | [العربية](EFB-Email-Notifications-Complete-Guide.ar.md) | Deutsch

> **Umfang dieser Dokumentation:** Alle Beschriftungen, Menüpfade und Verhaltensweisen in diesem Leitfaden wurden direkt aus dem aktuellen Code des Plugins (`includes/functions.php`, `includes/admin/assets/js/list_form-efb.js`, `val-efb.js`, `class-Emsfb-admin.php`, `class-Emsfb-public.php`) und der offiziellen deutschen Übersetzungsdatei (`languages/de.json`) übernommen — nicht erraten und nicht aus einer älteren Version dieses Leitfadens kopiert. Falls du eine ältere Version von Easy Form Builder verwendest, können einige Beschriftungen leicht abweichen.

## Kurzantwort

- Die E-Mail-Einstellungen findest du unter **Easy Form Builder → Bedienfeld → Einstellungen → E-Mail-Einstellungen**.
- Trage dort deine Admin-**E-Mail** und die **Absenderadresse** ein und klicke auf **E-Mail-Server prüfen**, um sicherzugehen, dass der Versand wirklich funktioniert.
- Ob ein *bestimmtes* Formular seinen Admin per E-Mail benachrichtigt, wird getrennt davon, in den **Formulareinstellungen** dieses Formulars, konfiguriert; du kannst mehrere Admin-Adressen durch Kommas getrennt eintragen.
- Damit auch die Person, die das Formular ausfüllt, benachrichtigt wird, fügst du dem Formular ein **E-Mail**-Feld hinzu und aktivierst dessen eigenen Schalter **E-Mail-Benachrichtigung aktivieren**.
- Solange der Schalter **Diese Website kann E-Mails versenden** in den E-Mail-Einstellungen nicht aktiviert ist, wird keine einzige E-Mail versendet — weder an den Admin noch an die absendende Person.
- Erzielt der E-Mail-Server-Test weniger als 70 Punkte oder kommt keine E-Mail an, ist die Installation eines SMTP-Plugins wie WP Mail SMTP die übliche Lösung.
- Das optische Design der E-Mails (Farben, Logo, Blöcke) wird getrennt davon, im Tab **E-Mail-Template**, angepasst — dieses Design ist unabhängig von den oben genannten Einstellungen und hat keinen Einfluss auf das Ergebnis des Server-Tests.

## Inhaltsverzeichnis

- [1. Grundlegende E-Mail-Einstellungen im Bedienfeld](#1-grundlegende-e-mail-einstellungen-im-bedienfeld)
- [2. Benachrichtigung an den Formular-Admin senden](#2-benachrichtigung-an-den-formular-admin-senden)
- [3. Benachrichtigung an die Person senden, die das Formular ausgefüllt hat](#3-benachrichtigung-an-die-person-senden-die-das-formular-ausgefüllt-hat)
- [4. Deinen E-Mail-Server testen](#4-deinen-e-mail-server-testen)
- [5. Das E-Mail-Template anpassen](#5-das-e-mail-template-anpassen)
- [Häufige Fehler und wie du sie behebst](#häufige-fehler-und-wie-du-sie-behebst)
- [Checkliste](#checkliste)
- [Häufig gestellte Fragen](#häufig-gestellte-fragen)

## 1. Grundlegende E-Mail-Einstellungen im Bedienfeld

Melde dich zunächst in deinem WordPress-Dashboard an, öffne **Easy Form Builder** in der linken Seitenleiste, klicke auf **Bedienfeld**, öffne dann oben im Bedienfeld den Tab **Einstellungen** und wähle den Tab **E-Mail-Einstellungen** aus (den 4. von 8 Einstellungs-Tabs).

Dieser Tab beginnt mit dem Bereich **Benachrichtigungs-E-Mail**, beschrieben als: „Wenn über ein Formular von Easy Form Builder eine neue Nachricht eingeht, erhält der Website-Administrator eine Benachrichtigungs-E-Mail." Die zwei Hauptfelder dieses Bereichs:

- **E-Mail** — die Adresse, an die Testberichte des E-Mail-Servers und allgemeine Hinweise des Plugins gesendet werden. Der Feldhinweis lautet: „Gib die Admin-E-Mail-Adresse ein, um E-Mail-Benachrichtigungen zu erhalten."
- **Absenderadresse** — die Adresse, von der aus deine ausgehenden E-Mails versendet werden. Im Free-Tarif ist dieses Feld auf die Pro-Version beschränkt (ein Klick zeigt einen Upgrade-Hinweis). Sie muss genau mit der Absenderadresse übereinstimmen, die in deinem SMTP-Plugin konfiguriert ist, falls du eines nutzt — sonst kommen E-Mails möglicherweise auch bei korrekt eingerichtetem SMTP nicht an.

Unter diesen beiden Feldern folgt der Bereich **E-Mail-Server**, beschrieben als „Verwende diesen Test, um zu überprüfen, ob dein Server E-Mails ordnungsgemäß versenden kann", mit dem Button **E-Mail-Server prüfen** (ausführlich in [Abschnitt 4](#4-deinen-e-mail-server-testen) erklärt).

Drei weitere Schalter befinden sich auf demselben Tab:

- **Diese Website kann E-Mails versenden** — aktiviere diesen Schalter nach einem erfolgreichen Server-Test und speichere. Solange er deaktiviert ist, versendet Easy Form Builder überhaupt keine E-Mail — weder an den Admin noch an die Person, die ein Formular ausfüllt.
- **Wöchentlicher E-Mail-Zustands- und Formularaktivitätsbericht** — eine Funktion ab Free Plus, die dem Website-Admin wöchentlich eine Zusammenfassung zum E-Mail-Zustand und zur Formularaktivität zusendet.
- **Statistik zu E-Mail-Auslieferung erfassen** — liefert die Daten für das Widget zu E-Mail-Zustellstatistiken im Dashboard.

Klicke nach jeder Änderung auf diesem Tab auf den **Speichern**-Button am unteren Seitenrand — er gilt für alle Einstellungs-Tabs gemeinsam, und Änderungen bleiben erst nach dem Klick erhalten.

## 2. Benachrichtigung an den Formular-Admin senden

Abschnitt 1 bereitet nur den E-Mail-Server vor. Ob ein *bestimmtes* Formular nach dem Absenden eine E-Mail an einen Admin schickt, wird separat, pro Formular, festgelegt:

1. Klicke im Formular-Editor auf das Symbol **Formulareinstellungen**, um das Einstellungsfeld dieses Formulars zu öffnen.
2. Fülle **Gib eine E-Mail-Adresse ein, die Benachrichtigungen erhalten soll.** mit der E-Mail-Adresse des Admins aus. Um mehr als eine Person zu benachrichtigen, trenne mehrere Adressen durch ein Komma (,) — Easy Form Builder trennt die Liste auf und sendet an alle.
3. Lege den Betreff der ausgehenden E-Mail im Feld **E-Mail-Betreff** fest.
4. Bei normalen Formularen und Zahlungsformularen erscheint das Dropdown **Inhalt der E-Mail-Benachrichtigung auswählen** mit drei Optionen:
   - **E-Mail nur mit Bestätigungscode und Link senden** — sendet nur einen Nachverfolgungscode und einen Link zur Ansicht der Nachricht im Bedienfeld, nicht den eigentlichen Forminhalt.
   - **E-Mail nur mit übermitteltem Formularinhalt und Link senden** — sendet sowohl den Forminhalt als auch den Link zum Bedienfeld.
   - **E-Mail nur mit übermitteltem Formularinhalt senden** — sendet nur den Forminhalt, ohne Link.
5. Speichere das Formular.

## 3. Benachrichtigung an die Person senden, die das Formular ausgefüllt hat

Damit nicht nur der Admin, sondern auch die absendende Person nach dem Abschicken eine E-Mail erhält, wird das am E-Mail-Feld des Formulars selbst eingestellt, nicht in den allgemeinen Formulareinstellungen:

1. Füge dem Formular ein Feld vom Typ **E-Mail** hinzu (dort trägt die besuchende Person ihre eigene Adresse ein).
2. Klicke auf dieses Feld, um sein Einstellungsfeld zu öffnen.
3. Aktiviere **E-Mail-Benachrichtigung aktivieren**.
4. Speichere das Formular.

Ab diesem Zeitpunkt sendet Easy Form Builder bei jeder Übermittlung mit einer gültigen Adresse in diesem Feld auch eine Kopie der Nachricht an diese Adresse. Um zu antworten, öffnet der Admin die erhaltene Nachricht im Bedienfeld von Easy Form Builder und antwortet dort direkt; diese Antwort wird als neue E-Mail an die Person gesendet, die das Formular ausgefüllt hat.

## 4. Deinen E-Mail-Server testen

Bevor du dich auf eine der obigen Einstellungen verlässt, solltest du mit dem integrierten Test bestätigen, dass dein Server tatsächlich Mails ausliefern kann. WordPress meldet oft, dass eine E-Mail „gesendet" wurde, obwohl sie nie ankommt; die einzige verlässliche Methode ist, eine echte E-Mail zu senden und die Zustellung von einem unabhängigen Server bestätigen zu lassen — genau das macht dieser Test.

**Vor dem Testlauf:** Die **Absenderadresse** in den E-Mail-Einstellungen muss exakt mit der Absenderadresse übereinstimmen, die in deinem SMTP-Plugin konfiguriert ist, falls du eines nutzt — sonst kommen E-Mails auch bei korrekt eingerichtetem SMTP nicht an.

**So führst du den Test aus:**

1. Melde dich in deinem WordPress-Dashboard an.
2. Öffne **Easy Form Builder** in der linken Seitenleiste.
3. Klicke auf **Bedienfeld**.
4. Öffne oben den Tab **Einstellungen**.
5. Öffne den Tab **E-Mail-Einstellungen**.
6. Stelle sicher, dass das Feld **E-Mail** eine gültige Adresse enthält — dorthin geht der vollständige Bericht.
7. Klicke auf den Button **E-Mail-Server prüfen**.

Das Testfeld öffnet sich automatisch und durchläuft nacheinander fünf Schritte; jeder Schritt zeigt ein Statussymbol in Echtzeit: ⏳ läuft, ✅ erfolgreich, ⚠️ Warnung, ❌ fehlgeschlagen.

| Schritt | Titel | Beschreibung |
|---|---|---|
| 1 | Test vorbereiten | Verbinden mit WhiteStudio, um eine eindeutige Test-E-Mail-Adresse zu erzeugen. |
| 2 | Test-E-Mail senden | WordPress sendet eine tatsächliche E-Mail, um zu prüfen, ob dein Server E-Mails ausliefern kann. |
| 3 | Warten auf Auslieferung | Es wird geprüft, ob die Test-E-Mail auf unserem Server eingegangen ist (dauert in der Regel einige Sekunden). |
| 4 | Schnellergebnis | Das erste Zustellungsergebnis wird angezeigt – du siehst sofort, ob die E-Mail funktioniert. |
| 5 | Vollständiger Bericht | Ein ausführlicher HTML-Bericht mit vollständiger Diagnose wird derzeit erstellt und dir per E-Mail zugesandt. |

**Das Ergebnis lesen:**

- Kommt die Nachricht an und liegt das Ergebnis über **70 von 100**, zeigt der **Status des E-Mail-Servers** einen Erfolg; du kannst **Diese Website kann E-Mails versenden** aktivieren und speichern.
- Liegt das Ergebnis unter 70, kommt die E-Mail zwar an, aber die Konfiguration hat vermutlich ein Authentifizierungsproblem — unvollständiges SPF, fehlendes DKIM, fehlendes oder ungeeignetes DMARC, eine nicht übereinstimmende Absenderdomain oder inkonsistente Header.
- Erscheint **Die Auslieferung von E-Mails funktioniert nicht**, kann deine Website keine E-Mails zuverlässig versenden — meist, weil der Hoster die Standard-Funktion `mail()` von PHP blockiert oder Nachrichten im Spam landen; die Lösung ist ein SMTP-Plugin.
- Erscheint **Die Auslieferung dauert länger als erwartet**, warte einige Minuten und führe den Test erneut aus; hält sich die Verzögerung, solltest du das mit deinem Hoster klären.

Die Tabelle **Auslieferungsdetails** hilft dir, genau zu erkennen, wo das Problem liegt:

| Feld | Bedeutung |
|---|---|
| Test gesendet an | Die Einmal-Adresse, an die die Test-E-Mail gesendet wurde |
| Absender | Die Absenderadresse, die deine Website verwendet hat |
| E-Mail erhalten | Ob die Nachricht tatsächlich beim Zielserver angekommen ist |
| Betreff stimmt überein | Ob der Betreff unverändert angekommen ist |
| Eindeutiger Code stimmt überein | Ob der eindeutige Code im Nachrichtentext ebenfalls unverändert angekommen ist |
| Wartezeit | Wie viele Sekunden die Zustellung gedauert hat |
| Maximale Wartezeit | Die Zeitgrenze, bevor der Test abläuft |
| Fehlerursache | Die genaue technische Ursache einer fehlgeschlagenen Zustellung, falls vorhanden |

Schlägt der Test fehl oder ist das Ergebnis niedrig, ist die zuverlässigste Lösung die Installation von **WP Mail SMTP** (oder einem anderen vertrauenswürdigen SMTP-Plugin) und die Verbindung mit einem echten Versanddienst — Gmail/Google Workspace, SendGrid, Brevo, Amazon SES oder der SMTP-Dienst deines Hosters funktionieren alle. Gehe nach der SMTP-Konfiguration zurück zu den E-Mail-Einstellungen von Easy Form Builder, gleiche die **Absenderadresse** exakt mit deinem SMTP-Absender ab und klicke erneut auf **E-Mail-Server prüfen**.

## 5. Das E-Mail-Template anpassen

Das optische Design aller E-Mails, die Easy Form Builder versendet (Benachrichtigungen, Registrierungsbestätigungen, Passwort-Zurücksetzungen), nutzt ein gemeinsames Template, das unabhängig von den oben genannten Einstellungen im eigenen Tab **E-Mail-Template** gestaltet wird (Bedienfeld → Einstellungen → E-Mail-Template, der 5. von 8 Einstellungs-Tabs).

Dieser Template-Builder ist eine dreispaltige Drag-and-drop-Umgebung mit:

- **13 Blocktypen** in vier Kategorien — Layout, Inhalt, Shortcode und Erweitert — darunter Kopfzeile, Button, Bild, Zwei-Spalten- und benutzerdefinierte HTML-Blöcke.
- **6 vorgefertigten Templates** für einen schnellen Einstieg: Leer, Professionell, Modern Dunkel, Minimalistisch, Elegant, Bunt.
- **5 dynamischen Shortcodes**, die beim Versand durch echte Daten ersetzt werden; nur der Shortcode für den Nachrichteninhalt ist Pflicht — ohne ihn lässt sich das Template nicht speichern.
- Einer Speichergrenze von **50.000 Zeichen** für das gesamte Template.

Dies ist eine globale Einstellung — ein Template für alle Formulare der Website, nicht ein eigenes pro Formular. Wichtig: Dieses Design wird nur auf **echte, ausgehende E-Mails** angewendet. Das in [Abschnitt 4](#4-deinen-e-mail-server-testen) beschriebene Werkzeug **E-Mail-Server prüfen** verschickt eine feste Diagnosenachricht und verwendet dein benutzerdefiniertes Template nie — eine Änderung am E-Mail-Template hat also keinen Einfluss auf das Ergebnis des Server-Tests.

Vollständige Details zu jedem Block, jedem vorgefertigten Template und jedem Shortcode findest du im [vollständigen Leitfaden zum E-Mail-Template-Builder](../email-template/EFB-Email-Template-Builder-Complete-Guide.en.md).

## Häufige Fehler und wie du sie behebst

| Symptom | Wahrscheinliche Ursache | Lösung |
|---|---|---|
| Keine E-Mail kommt an — weder beim Admin noch bei der absendenden Person | **Diese Website kann E-Mails versenden** ist deaktiviert | Führe den E-Mail-Server-Test aus; liegt das Ergebnis über 70, aktiviere den Schalter und speichere |
| Der Admin erhält eine E-Mail, die absendende Person aber nicht | Am E-Mail-Feld des Formulars ist **E-Mail-Benachrichtigung aktivieren** nicht eingeschaltet | Klicke auf das E-Mail-Feld des Formulars und aktiviere diesen Schalter |
| Nur einer von mehreren Admins wird benachrichtigt | Die Adressen im Admin-Feld des Formulars sind nicht durch Kommas getrennt | Trenne jede Adresse mit einem Komma (,) |
| Der Server-Test liegt unter 70 Punkten oder läuft ab | Der Hoster blockiert `mail()`, oder SPF/DKIM ist nicht eingerichtet | Installiere ein SMTP-Plugin und gleiche die **Absenderadresse** damit ab |
| Auch nach SMTP-Einrichtung kommt keine E-Mail an | Die Absenderadresse in Easy Form Builder stimmt nicht mit dem SMTP-Absender überein | Gleiche beide Adressen exakt ab und teste erneut |
| Anpassungen am E-Mail-Template wirken sich nicht auf das Ergebnis von „E-Mail-Server prüfen" aus | Das ist beabsichtigt — der Test ist unabhängig vom benutzerdefinierten Template | Erwartetes Verhalten; sende ein echtes Formular ab, um dein Template zu sehen |

## Checkliste

- [ ] **E-Mail** und **Absenderadresse** in den E-Mail-Einstellungen enthalten echte Adressen deiner eigenen Domain.
- [ ] **E-Mail-Server prüfen** wurde ausgeführt und hat mehr als 70 Punkte erzielt.
- [ ] **Diese Website kann E-Mails versenden** ist aktiviert und gespeichert.
- [ ] In den Einstellungen jedes Formulars sind Admin-E-Mail und Betreff ausgefüllt.
- [ ] Der Inhaltstyp der Admin-E-Mail (Bestätigungscode / Forminhalt / beides) ist nach Bedarf gewählt.
- [ ] Soll auch der absendenden Person geantwortet werden können, hat das Formular ein E-Mail-Feld mit aktiviertem **E-Mail-Benachrichtigung aktivieren**.
- [ ] Das E-Mail-Template wurde bei Bedarf im Tab **E-Mail-Template** angepasst.
- [ ] Ein echtes Formular wurde testweise abgeschickt, und die tatsächlich erhaltene E-Mail — nicht nur der Server-Test — wurde geprüft.

## Häufig gestellte Fragen

**Warum speichert mein Formular die Nachricht, aber es kommt keine E-Mail an?**
Das bedeutet meist, dass dein Hosting-Server keine E-Mails versenden kann, nicht dass das Formular fehlerhaft ist. Öffne den Tab E-Mail-Einstellungen und klicke auf **E-Mail-Server prüfen**, um die genaue Ursache herauszufinden.

**Wie kann ich mehrere Personen gleichzeitig über ein Formular benachrichtigen?**
Trage im Admin-E-Mail-Feld dieses Formulars (in den **Formulareinstellungen**) mehrere Adressen durch Komma getrennt ein; Easy Form Builder sendet an alle.

**Was ist der Unterschied zwischen den drei Optionen bei „Inhalt der E-Mail-Benachrichtigung auswählen"?**
Die erste sendet nur einen Bestätigungscode und einen Link zur Ansicht der Nachricht im Bedienfeld; die zweite sendet sowohl den Forminhalt als auch den Link; die dritte sendet nur den Forminhalt, ohne Link.

**Kann auch die Person, die das Formular ausgefüllt hat, eine Bestätigungs-E-Mail erhalten?**
Ja — füge dem Formular ein E-Mail-Feld hinzu und aktiviere dessen Schalter **E-Mail-Benachrichtigung aktivieren**.

**Wie antworte ich, nachdem ich eine Nachricht erhalten habe?**
Antworte direkt im Bedienfeld von Easy Form Builder auf die erhaltene Nachricht; deine Antwort wird als neue E-Mail an die Person gesendet, die das Formular ausgefüllt hat.

**Der Server-Test liegt über 70, aber E-Mails landen trotzdem im Spam — warum?**
Ein hohes Ergebnis bedeutet, dass dein Server E-Mails versenden kann; die Landung im Spam ist ein separates Problem, meist bedingt durch den E-Mail-Inhalt oder unvollständige DNS-Einträge (SPF/DKIM/DMARC). Prüfe den vollständigen HTML-Bericht, der an die Admin-Adresse gesendet wird.

**Muss ich unbedingt ein SMTP-Plugin installieren?**
Nicht immer — liegt der Server-Test über 70, funktioniert dein aktueller Server. SMTP wird notwendig, wenn der Test fehlschlägt oder niedrig liegt.

**Wirkt sich die Anpassung des E-Mail-Templates auf das Ergebnis von „E-Mail-Server prüfen" aus?**
Nein. Der E-Mail-Server-Test verwendet eine feste Diagnosenachricht und ist vollständig unabhängig von deinem benutzerdefinierten Template. Dein Design erscheint nur in echten, ausgehenden E-Mails.

**Kann ich den E-Mail-Server-Test mehrmals ausführen?**
Ja — führe ihn nach jeder Änderung an den E-Mail-Einstellungen, an SMTP oder am Hosting erneut aus, um die Behebung zu bestätigen.

<!--
FAQPage JSON-LD — muss exakt mit dem sichtbaren FAQ-Abschnitt übereinstimmen.
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Warum speichert mein Formular die Nachricht, aber es kommt keine E-Mail an?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Das bedeutet meist, dass dein Hosting-Server keine E-Mails versenden kann, nicht dass das Formular fehlerhaft ist. Öffne den Tab E-Mail-Einstellungen und klicke auf E-Mail-Server prüfen, um die genaue Ursache herauszufinden."
      }
    },
    {
      "@type": "Question",
      "name": "Wie kann ich mehrere Personen gleichzeitig über ein Formular benachrichtigen?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Trage im Admin-E-Mail-Feld dieses Formulars (in den Formulareinstellungen) mehrere Adressen durch Komma getrennt ein; Easy Form Builder sendet an alle."
      }
    },
    {
      "@type": "Question",
      "name": "Was ist der Unterschied zwischen den drei Optionen bei „Inhalt der E-Mail-Benachrichtigung auswählen\"?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Die erste sendet nur einen Bestätigungscode und einen Link zur Ansicht der Nachricht im Bedienfeld; die zweite sendet sowohl den Forminhalt als auch den Link; die dritte sendet nur den Forminhalt, ohne Link."
      }
    },
    {
      "@type": "Question",
      "name": "Kann auch die Person, die das Formular ausgefüllt hat, eine Bestätigungs-E-Mail erhalten?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Ja — füge dem Formular ein E-Mail-Feld hinzu und aktiviere dessen Schalter E-Mail-Benachrichtigung aktivieren."
      }
    },
    {
      "@type": "Question",
      "name": "Wie antworte ich, nachdem ich eine Nachricht erhalten habe?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Antworte direkt im Bedienfeld von Easy Form Builder auf die erhaltene Nachricht; deine Antwort wird als neue E-Mail an die Person gesendet, die das Formular ausgefüllt hat."
      }
    },
    {
      "@type": "Question",
      "name": "Der Server-Test liegt über 70, aber E-Mails landen trotzdem im Spam — warum?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Ein hohes Ergebnis bedeutet, dass dein Server E-Mails versenden kann; die Landung im Spam ist ein separates Problem, meist bedingt durch den E-Mail-Inhalt oder unvollständige DNS-Einträge (SPF/DKIM/DMARC). Prüfe den vollständigen HTML-Bericht, der an die Admin-Adresse gesendet wird."
      }
    },
    {
      "@type": "Question",
      "name": "Muss ich unbedingt ein SMTP-Plugin installieren?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Nicht immer — liegt der Server-Test über 70, funktioniert dein aktueller Server. SMTP wird notwendig, wenn der Test fehlschlägt oder niedrig liegt."
      }
    },
    {
      "@type": "Question",
      "name": "Wirkt sich die Anpassung des E-Mail-Templates auf das Ergebnis von „E-Mail-Server prüfen\" aus?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Nein. Der E-Mail-Server-Test verwendet eine feste Diagnosenachricht und ist vollständig unabhängig von deinem benutzerdefinierten Template. Dein Design erscheint nur in echten, ausgehenden E-Mails."
      }
    },
    {
      "@type": "Question",
      "name": "Kann ich den E-Mail-Server-Test mehrmals ausführen?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Ja — führe ihn nach jeder Änderung an den E-Mail-Einstellungen, an SMTP oder am Hosting erneut aus, um die Behebung zu bestätigen."
      }
    }
  ]
}
</script>
-->
