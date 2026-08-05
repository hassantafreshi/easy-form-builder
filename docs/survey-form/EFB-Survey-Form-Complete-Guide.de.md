---
title: "Umfrageergebnisse-Diagramm in Easy Form Builder: Die komplette Anleitung"
slug: "easy-form-builder-survey-results-chart"
meta_description: "Zeige oder verberge das Ergebnis-Diagramm auf deinem WordPress-Umfrageformular nach dem Absenden, Feld für Feld. Eine anhand des Codes geprüfte Anleitung."
focus_keyphrase: "WordPress Umfrage Formular mit Diagramm"
secondary_keyphrases:
  - "WordPress Umfrage Plugin"
  - "Umfrageergebnisse nach dem Absenden anzeigen"
  - "WordPress Umfrage-Tool mit Balkendiagramm"
  - "NPS Umfrage WordPress"
  - "Umfrageergebnisse ausblenden WordPress Formular"
  - "WordPress Formular-Baukasten für Umfragen"
search_intent: "Informations- und Einrichtungsanleitung"
audience: "WordPress-Website-Betreiber und Administratoren, die mit Easy Form Builder Umfrage-, Abstimmungs- oder Feedback-Formulare erstellen"
product_version: "Easy Form Builder 4.1.3 und höher"
last_reviewed: "2026-08-05"
---

# Umfrageergebnisse-Diagramm in Easy Form Builder: Die komplette Anleitung

**Keywords:** WordPress Umfrage Formular mit Diagramm, WordPress Umfrage Plugin, Umfrageergebnisse nach dem Absenden anzeigen, WordPress Umfrage-Tool mit Balkendiagramm, NPS Umfrage WordPress, Umfrageergebnisse ausblenden WordPress Formular, WordPress Formular-Baukasten für Umfragen.

**Breadcrumb:** Easy Form Builder Dokumentation › Umfrage-Formulare › Komplette Anleitung · Sprachen: [فارسی](EFB-Survey-Form-Complete-Guide.fa.md) | [English](EFB-Survey-Form-Complete-Guide.en.md) | [العربية](EFB-Survey-Form-Complete-Guide.ar.md) | Deutsch

> **Umfang dieses Dokuments:** Diese Anleitung wurde anhand des tatsächlichen PHP- und JavaScript-Codes des Umfrage-Formulartyps in Easy Form Builder geschrieben — dem Formulareinstellungen-Bereich, der öffentlichen Absende-Logik und dem Skript, das das Diagramm zeichnet —, dazu anhand der mitgelieferten Übersetzungsdateien des Plugins. Nichts hier ist geraten. Falls eine Bezeichnung in deiner Installation etwas anders aussieht, liegt das meist an deiner WordPress-Admin-Sprache oder einer aktualisierten Plugin-Version.

## Kurzantwort (für Suchmaschinen und KI-Assistenten)

- Easy Form Builder kann Besuchern direkt nach dem Absenden eines Formulars vom Typ **Umfrage** ein live berechnetes, zusammengefasstes Ergebnis-Diagramm zeigen — das ist eine eingebaute Funktion, kein separates Add-on, und sie funktioniert bereits in der kostenlosen Version.
- Die Einstellung findest du im Formular-Baukasten unter **Formulareinstellungen → Fortgeschritten → Anzeige der Umfrageergebnisse**. Sie erscheint erst, wenn der **Formulartyp** des Formulars auf **Umfrage** steht.
- Es gibt genau drei Optionen: **Keine Ergebnisse anzeigen** (Standard), **Ergebnisse als Balkendiagramm anzeigen** und **Ergebnisse als Kreisdiagramm anzeigen**.
- Jedes Feld hat außerdem seinen eigenen Schalter **„Dieses Feld in den öffentlichen Umfrageergebnissen anzeigen“** — damit bestimmst du genau, welche Fragen den Besuchern gezeigt werden und welche privat bleiben.
- Das Diagramm ist zusammengefasst und anonym: Es zeigt Anzahl und Durchschnittswerte pro Frage, nie, wer was geantwortet hat.
- Der Ergebnisbereich ist für Auswahl- und Bewertungsfragen gebaut (Optionsfeld, Checkbox, Auswählen, Mehrfachauswahl, Ja/Nein, Umschalten, Bewertung, 5-Punkte-Skala, Bereich, Net Promoter Score, Datumsauswahl). Nummer-, Längerer-Text- und NPS-Tabellen-Matrix-Fragen solltest du aus den öffentlichen Ergebnissen heraushalten — siehe [Welche Feldtypen am besten funktionieren](#welche-feldtypen-im-ergebnis-diagramm-am-besten-funktionieren).
- Da das Diagramm live aus allen gespeicherten Antworten neu berechnet wird, ist die gerade abgeschickte Antwort des Besuchers selbst schon im Diagramm enthalten, das er sieht — und änderst du den Diagrammtyp später, gilt das rückwirkend für alle bisherigen Antworten.

## Inhaltsverzeichnis

- [Was ist die Anzeige der Umfrageergebnisse?](#was-ist-die-anzeige-der-umfrageergebnisse)
- [Wie erstellst du ein Umfrageformular?](#wie-erstellst-du-ein-umfrageformular)
- [Wo findest du die Anzeige der Umfrageergebnisse? Schritt für Schritt](#wo-findest-du-die-anzeige-der-umfrageergebnisse-schritt-für-schritt)
- [Die drei Optionen der Anzeige der Umfrageergebnisse](#die-drei-optionen-der-anzeige-der-umfrageergebnisse)
- [Auswählen, welche Fragen in den Ergebnissen erscheinen](#auswählen-welche-fragen-in-den-ergebnissen-erscheinen)
- [Was Besucher nach dem Absenden sehen](#was-besucher-nach-dem-absenden-sehen)
- [Wie die Ergebnisse berechnet werden (und was privat bleibt)](#wie-die-ergebnisse-berechnet-werden-und-was-privat-bleibt)
- [Welche Feldtypen im Ergebnis-Diagramm am besten funktionieren](#welche-feldtypen-im-ergebnis-diagramm-am-besten-funktionieren)
- [Häufige Fehler und ihre Lösung](#häufige-fehler-und-ihre-lösung)
- [Checkliste](#checkliste)
- [Häufig gestellte Fragen](#häufig-gestellte-fragen)

## Was ist die Anzeige der Umfrageergebnisse?

**Anzeige der Umfrageergebnisse** (im Code: Survey Results Display) ist eine Einstellung auf Formularebene, die bei jedem Formular verfügbar ist, dessen **Formulartyp** auf **Umfrage** steht. Sie legt fest, ob Personen, die deine Umfrage ausfüllen, direkt nach dem Absenden — unterhalb der Bestätigungsmeldung, auf derselben Seite — ein zusammengefasstes Ergebnis-Diagramm aus allen bisherigen Antworten zu sehen bekommen. Du brauchst dafür kein Add-on, keine separate Ergebnisseite und keine höhere Plan-Stufe; es gehört zum normalen Umfrage-Formulartyp des Plugins.

Neben dem Diagrammtyp selbst hat jedes einzelne Feld seinen eigenen Schalter **„Dieses Feld in den öffentlichen Umfrageergebnissen anzeigen.“** Das ist die zweite Hälfte der Funktion: Der Diagrammtyp entscheidet, *wie* (oder ob überhaupt) Ergebnisse gezeichnet werden, der Schalter pro Feld entscheidet, *welche* Fragen einbezogen werden.

## Wie erstellst du ein Umfrageformular?

Es gibt zwei Wege zu einem Formular vom Typ Umfrage:

1. **Starte mit einer Umfrage-Vorlage.** Beim Erstellen eines neuen Formulars enthält die Vorlagen-Galerie Vorlagen, die für Umfragen markiert sind:
   - **Umfrage** — „Erstelle Formulare für Befragungen oder Umfragen oder Fragebogen.“
   - **Umfrage zum Einkaufserlebnis** — „Sammle Kundenfeedback zum Einkaufserlebnis im Laden.“
   - **Umfrage zum Wählerverhalten** — „Umfragevorlage zur Untersuchung des Wählerverhaltens.“

   Wählst du eine davon, steht der **Formulartyp** deines neuen Formulars bereits auf **Umfrage**.

2. **Starte mit einem leeren Formular und ändere den Typ.** Baue das Formular wie gewohnt auf, öffne dann **Formulareinstellungen** und stelle den **Formulartyp** auf **Umfrage** (siehe nächster Abschnitt). Das funktioniert bei jedem neuen oder bestehenden Formular, nicht nur bei Umfrage-Vorlagen.

Sobald der **Formulartyp** auf **Umfrage** steht, wird die Einstellung **Anzeige der Umfrageergebnisse** in beiden Fällen verfügbar.

## Wo findest du die Anzeige der Umfrageergebnisse? Schritt für Schritt

1. Öffne das Formular im **Formular-Baukasten**.
2. Klicke auf den Bereich **Formulareinstellungen** (das Einstellungssymbol auf Formularebene, nicht die Einstellungen eines einzelnen Feldes).
3. Scrolle nach unten zum Abschnitt **Fortgeschritten** — er ist standardmäßig aufgeklappt, du musst also normalerweise nichts extra öffnen.
4. Suche das Dropdown **Formulartyp** und wähle **Umfrage**. Sobald du das tust, erscheint direkt darunter ein neues Feld: **Anzeige der Umfrageergebnisse**.
5. Öffne das Dropdown **Anzeige der Umfrageergebnisse** und wähle eine der drei Optionen (nächster Abschnitt).
6. Direkt unter dem Dropdown bestätigt ein kleiner Hinweistext, was du gerade aktiviert hast: „Nach der Teilnahme können Besucher die zusammengefassten Umfrageergebnisse einsehen.“
7. Speichere das Formular.

> Siehst du **Anzeige der Umfrageergebnisse** gar nicht, prüfe noch einmal Schritt 4 — das Feld bleibt verborgen, bis der **Formulartyp** wirklich auf **Umfrage** steht.

## Die drei Optionen der Anzeige der Umfrageergebnisse

| Option (auf dem Bildschirm) | Was sie bewirkt |
|---|---|
| **Keine Ergebnisse anzeigen** | Der Standard für jedes neue Umfrageformular. Besucher sehen nach dem Absenden keinen Ergebnisbereich, nur die normale Bestätigungsmeldung. |
| **Ergebnisse als Balkendiagramm anzeigen** | Nach dem Absenden sehen Besucher einen Bereich **Umfrageergebnisse** mit einem Balkendiagramm für jede Frage, die du öffentlich gemacht hast. |
| **Ergebnisse als Kreisdiagramm anzeigen** | Derselbe Ergebnisbereich, aber geeignete Fragen werden als Kreisdiagramm statt als Balkendiagramm gezeichnet. |

Welche Option du auch wählst, sie gilt einheitlich für alle Fragen, die du auf diesem Formular öffentlich gemacht hast — du kannst auf demselben Formular nicht einen Teil als Balkendiagramm und einen anderen Teil als Kreisdiagramm anzeigen.

## Auswählen, welche Fragen in den Ergebnissen erscheinen

Öffne die Einstellungen eines beliebigen Feldes und suche **„Dieses Feld in den öffentlichen Umfrageergebnissen anzeigen.“** Diesen Schalter gibt es bei jedem Feldtyp, der sich sinnvoll zählen oder mitteln lässt (Auswahlfelder, Bewertungs- und Skalenfelder, Datumsauswahl, Nummer und Textfelder zeigen ihn alle; Layout-Elemente wie Schritt nicht).

Genau hier lohnt es sich, das Verhalten wirklich zu verstehen:

- **Solange du diesen Schalter bei keinem Feld angefasst hast**, bezieht das Plugin automatisch jede Frage ein, die es zusammenfassen kann (alle Auswahl-, Bewertungs-/Skalen-, Net-Promoter-Score-, Matrix-, Nummer-, Text- und Datumsfelder) — sobald du Balken- oder Kreisdiagramm wählst. Du musst also nichts manuell aktivieren, um ein erstes, funktionierendes Ergebnis-Panel zu bekommen.
- **Sobald du den Schalter bei auch nur einem Feld einschaltest**, wechselt das ganze Formular von „alles automatisch einbeziehen“ zu „nur einbeziehen, was ausdrücklich eingeschaltet ist.“ Alle anderen Felder bleiben jetzt verborgen, bis du auch ihren Schalter aktivierst.

In der Praxis heißt das: Willst du von Anfang an volle Kontrolle, schalte den Schalter Frage für Frage bei genau den Fragen ein, die öffentlich sein sollen — sobald du die erste anfasst, verstummen die übrigen, bis du sie ebenfalls aktivierst. Bist du mit „alles anzeigen“ zufrieden, kannst du einfach keinen einzigen Schalter anfassen.

## Was Besucher nach dem Absenden sehen

Steht **Anzeige der Umfrageergebnisse** auf Balken- oder Kreisdiagramm und liegen für mindestens ein Feld Ergebnisse vor:

1. Der Besucher füllt die Umfrage aus und sendet sie ganz normal ab.
2. Seine Antwort wird zuerst gespeichert — sie ist also bereits in den Summen enthalten, wenn der nächste Schritt läuft.
3. Die Bestätigungsmeldung erscheint (standardmäßig „Die Befragung wurde erfolgreich abgeschlossen.“, oder deine eigene Dankes-Nachricht, falls du eine festgelegt hast).
4. Direkt darunter, auf demselben Bildschirm — keine separate Seite, keine eigene URL — erscheint ein Bereich **Umfrageergebnisse** mit einer Anzahl der **Antworten** und je einer Diagrammkarte pro öffentlicher Frage.

Steht **Anzeige der Umfrageergebnisse** auf **Keine Ergebnisse anzeigen**, oder liegen noch für keine Frage verwertbare Ergebnisse vor (etwa weil jeder Schalter für öffentliche Felder aus ist, oder weil es die allererste Antwort ist und die beteiligten Felder noch keine gezählten Daten haben), sieht der Besucher einfach die normale Bestätigungsmeldung ohne Ergebnisbereich — kein Fehler, keine leere Box.

## Wie die Ergebnisse berechnet werden (und was privat bleibt)

Der Ergebnisbereich wird bei jedem Absenden serverseitig aus allen gespeicherten Antworten zu genau diesem Formular neu berechnet — es ist kein zu einem früheren Zeitpunkt eingefrorener Schnappschuss. Das hat zwei praktische Folgen:

- **Eine spätere Änderung des Diagrammtyps wirkt rückwirkend.** Wechselst du von Balken- zu Kreisdiagramm (oder von „Keine Ergebnisse anzeigen“ zu einem Diagramm), sieht der nächste Besucher, der absendet, sofort ein Diagramm aus *allen* bisherigen Antworten dieses Formulars, nicht nur aus neuen.
- **Nichts identifiziert, wer was geantwortet hat.** Der Bereich erhält ausschließlich Anzahl- und Durchschnittswerte pro Frage — zum Beispiel, wie viele Personen bei einer Mehrfachauswahl-Frage welche Option gewählt haben, oder den Durchschnitt eines Bewertungsfelds. Einzelne Antworten, Namen oder E-Mail-Adressen sind nie Teil der Daten, die für diese Funktion an den Browser gesendet werden.

Eine Zahl solltest du richtig einordnen können: Die Anzahl bei **Antworten** über dem Diagramm ist die Summe der Antworten über *alle aktuell im Bereich gezeigten Fragen* — nicht die Anzahl der Personen, die das Formular abgeschickt haben. Machst du zwei Fragen öffentlich und beantwortet jeder beide, zeigt diese Zahl etwa das Doppelte deiner tatsächlichen Anzahl an Formularabsendungen. Für die echte Anzahl an Absendungen prüfst du stattdessen die Nachrichten-Liste dieses Formulars in Easy Form Builder.

## Welche Feldtypen im Ergebnis-Diagramm am besten funktionieren

Intern wird jeder Feldtyp einer Kategorie zugeordnet, bevor er im Ergebnisbereich erscheinen kann:

| Feldtypen | Kategorie | Verhalten im Ergebnisbereich |
|---|---|---|
| Optionsfeld, Checkbox, Auswählen, Mehrfachauswahl, Ja/Nein, Umschalten | Auswahl | Wird sauber als Balken- oder Kreisdiagramm dargestellt. |
| Bewertung, 5-Punkte-Skala, Bereich | Skala | Wird sauber als Balkendiagramm dargestellt (Bereich wird automatisch in Intervalle gruppiert). |
| Net Promoter Score | NPS | Wird dargestellt, im selben Balken-/Kreisdiagrammtyp, den du für das Formular gewählt hast. |
| Datumsauswahl | Datum | Wird als Balkendiagramm dargestellt, gruppiert nach Monat. |
| Nummer | Numerisch | **Wird vom Ergebnisbereich derzeit nicht gezeichnet.** Schaltest du den Schalter hier ein, können nachfolgende Fragen im Bereich ganz ausbleiben. |
| Text, Längerer Text | Text | **Wird vom Ergebnisbereich derzeit nicht gezeichnet.** Gleiches Risiko wie bei Nummer. |
| NPS-Tabellen-Matrix | Matrix | **Wird vom Ergebnisbereich derzeit nicht gezeichnet.** Gleiches Risiko wie bei Nummer. |

**Praktische Regel:** Schalte „Dieses Feld in den öffentlichen Umfrageergebnissen anzeigen“ bei deinen Auswahl- und Bewertungs-/Skalenfragen sowie bei Datumsauswahl-Feldern ein. Lass ihn bei Nummer-, Text-, Längerer-Text- und NPS-Tabellen-Matrix-Fragen aus — der Ergebnisbereich kann diese noch nicht darstellen, und schaltest du sie ein, können die danach folgenden Fragen nicht mehr angezeigt werden.

## Häufige Fehler und ihre Lösung

| Symptom | Wahrscheinliche Ursache | Lösung |
|---|---|---|
| „Anzeige der Umfrageergebnisse“ erscheint nicht in den Formulareinstellungen | **Formulartyp** steht nicht auf **Umfrage** | Öffne Formulareinstellungen → Fortgeschritten und stelle den Formulartyp auf Umfrage |
| Besuchern wird kein Ergebnisbereich angezeigt, obwohl ein Diagrammtyp gewählt ist | Für keine Frage liegen bisher Ergebnisse vor, oder das Formular hat noch keine Antworten | Sende eine Testantwort ab und prüfe, ob mindestens ein passendes Feld öffentlich sein soll |
| Der Ergebnisbereich zeigte früher mehrere Fragen, jetzt nur noch eine | Du hast den Schalter bei einem Feld eingeschaltet, wodurch das ganze Formular von „alles automatisch zeigen“ auf „nur ausdrücklich Eingeschaltetes zeigen“ wechselt | Schalte „Dieses Feld in den öffentlichen Umfrageergebnissen anzeigen“ bei jeder Frage ein, die enthalten sein soll |
| Der Ergebnisbereich bricht mittendrin ab, oder eine Frage erscheint nie | Ein Nummer-, Text-/Längerer-Text- oder NPS-Tabellen-Matrix-Feld hat „Dieses Feld in den öffentlichen Umfrageergebnissen anzeigen“ eingeschaltet | Schalte diesen Schalter aus; lass ihn nur bei Auswahl-, Bewertungs-, Skalen- und Datumsfragen an |
| Die Zahl bei „Antworten“ wirkt zu hoch | Sie summiert Antworten über alle öffentlichen Fragen, nicht die Anzahl der Absendungen | Prüfe die Nachrichten-Liste dieses Formulars für die echte Anzahl an Absendungen |
| Es erscheint überhaupt kein Diagramm | Anzeige der Umfrageergebnisse steht noch auf „Keine Ergebnisse anzeigen“ | Stelle sie auf Balken- oder Kreisdiagramm um und speichere das Formular |

## Checkliste

- [ ] Formulartyp steht in Formulareinstellungen → Fortgeschritten auf **Umfrage**.
- [ ] Anzeige der Umfrageergebnisse steht auf **Balkendiagramm** oder **Kreisdiagramm**, nicht auf „Keine Ergebnisse anzeigen“.
- [ ] Bei jeder Frage, die öffentlich sein soll, ist „Dieses Feld in den öffentlichen Umfrageergebnissen anzeigen“ eingeschaltet (oder du hast bewusst keinen Schalter angefasst, damit das Plugin automatisch alles einbezieht).
- [ ] Nummer-, Längerer-Text-, Text- und NPS-Tabellen-Matrix-Felder sind ausgeschaltet oder so platziert, dass sie keine anderen Fragen blockieren.
- [ ] Das Formular wurde gespeichert und mit einer echten Absendung im Frontend getestet.
- [ ] Der Bereich **Umfrageergebnisse** samt Anzahl der **Antworten** erscheint nach einer Testabsendung korrekt unterhalb der Bestätigungsmeldung.

## Häufig gestellte Fragen

### Ist das Umfrageergebnisse-Diagramm eine reine Pro-Funktion?

Nein. Es gehört zum Umfrage-Formulartyp der normalen, kostenlosen Version von Easy Form Builder — es gibt kein Add-on zu installieren und keine Lizenzprüfung.

### Wo genau schalte ich das Diagramm ein oder aus?

Öffne im Formular-Baukasten die **Formulareinstellungen**, klappe **Fortgeschritten** auf (standardmäßig bereits offen), stelle den **Formulartyp** auf **Umfrage** und nutze dann das erscheinende Dropdown **Anzeige der Umfrageergebnisse**.

### Wie heißen die drei Diagramm-Optionen?

**Keine Ergebnisse anzeigen** (Standard), **Ergebnisse als Balkendiagramm anzeigen** und **Ergebnisse als Kreisdiagramm anzeigen**.

### Zählt meine eigene Antwort schon zu dem Diagramm, das ich direkt nach dem Absenden sehe?

Ja. Deine Antwort wird gespeichert, bevor die Ergebnisse berechnet werden — sie ist also bereits im Diagramm enthalten, das du auf dem Bestätigungsbildschirm siehst.

### Wie steuere ich, welche Fragen im Diagramm erscheinen?

Über den Schalter „Dieses Feld in den öffentlichen Umfrageergebnissen anzeigen“ bei jedem einzelnen Feld. Lässt du jeden Schalter unangetastet, werden automatisch alle zusammenfassbaren Fragen gezeigt; schaltest du auch nur einen ein, zeigt das ganze Formular ab sofort nur noch das, was du ausdrücklich aktiviert hast.

### Welche Feldtypen sollte ich nicht öffentlich machen?

Nummer-, Text-, Längerer-Text- und NPS-Tabellen-Matrix-Felder. Der Ergebnisbereich kann sie noch nicht darstellen, und schaltest du sie ein, können nachfolgende Fragen nicht mehr angezeigt werden.

### Zeigt der Ergebnisbereich, wer was geantwortet hat?

Nein. Er zeigt ausschließlich zusammengefasste Anzahl- und Durchschnittswerte pro Frage — nie einzelne Antworten, Namen oder E-Mail-Adressen.

### Was passiert, wenn ich den Diagrammtyp einstelle, aber noch niemand geantwortet hat?

Besucher sehen einfach die normale Bestätigungsmeldung. Es gibt keinen Fehler und keine leere Diagramm-Box — der Bereich erscheint erst, wenn es tatsächlich Daten zum Zeigen gibt.

### Kann ich den Diagrammtyp ändern, nachdem schon Leute geantwortet haben?

Ja, und das wirkt rückwirkend — da die Ergebnisse bei jeder Absendung live aus allen gespeicherten Antworten berechnet werden, sieht der nächste Besucher, der absendet, ein Diagramm aus allen bisherigen Antworten, nicht nur aus neuen.

### Wird das Ergebnis-Diagramm auf einer eigenen Seite angezeigt?

Nein. Es erscheint eingebettet, direkt unterhalb der Bestätigungsmeldung, auf demselben Bildschirm, auf dem der Besucher das Formular gerade abgeschickt hat.

## Vorgeschlagene FAQ-strukturierte-Daten für die Veröffentlichung

Verwende diesen Block nur, wenn das SEO-Plugin deiner Website nicht bereits ein vergleichbares FAQ-Schema erzeugt. Die Fragen und Antworten müssen auf der veröffentlichten Seite sichtbar bleiben (entsprechend dem Abschnitt „Häufig gestellte Fragen“ oben).

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Ist das Umfrageergebnisse-Diagramm eine reine Pro-Funktion?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Nein. Es gehört zum Umfrage-Formulartyp der normalen, kostenlosen Version von Easy Form Builder – es gibt kein Add-on zu installieren und keine Lizenzprüfung."
      }
    },
    {
      "@type": "Question",
      "name": "Wo genau schalte ich das Diagramm ein oder aus?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Öffne im Formular-Baukasten die Formulareinstellungen, klappe Fortgeschritten auf (standardmäßig bereits offen), stelle den Formulartyp auf Umfrage und nutze dann das erscheinende Dropdown Anzeige der Umfrageergebnisse."
      }
    },
    {
      "@type": "Question",
      "name": "Wie heißen die drei Diagramm-Optionen?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Keine Ergebnisse anzeigen (Standard), Ergebnisse als Balkendiagramm anzeigen und Ergebnisse als Kreisdiagramm anzeigen."
      }
    },
    {
      "@type": "Question",
      "name": "Zählt meine eigene Antwort schon zu dem Diagramm, das ich direkt nach dem Absenden sehe?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Ja. Deine Antwort wird gespeichert, bevor die Ergebnisse berechnet werden, und ist daher bereits im Diagramm auf dem Bestätigungsbildschirm enthalten."
      }
    },
    {
      "@type": "Question",
      "name": "Wie steuere ich, welche Fragen im Diagramm erscheinen?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Über den Schalter „Dieses Feld in den öffentlichen Umfrageergebnissen anzeigen“ bei jedem einzelnen Feld. Unangetastete Schalter zeigen automatisch alle zusammenfassbaren Fragen; sobald du einen einschaltest, zeigt das Formular nur noch ausdrücklich aktivierte Fragen."
      }
    },
    {
      "@type": "Question",
      "name": "Welche Feldtypen sollte ich nicht öffentlich machen?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Nummer-, Text-, Längerer-Text- und NPS-Tabellen-Matrix-Felder. Der Ergebnisbereich kann sie noch nicht darstellen, und ihre Aktivierung kann nachfolgende Fragen an der Anzeige hindern."
      }
    },
    {
      "@type": "Question",
      "name": "Zeigt der Ergebnisbereich, wer was geantwortet hat?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Nein. Er zeigt ausschließlich zusammengefasste Anzahl- und Durchschnittswerte pro Frage, nie einzelne Antworten, Namen oder E-Mail-Adressen."
      }
    },
    {
      "@type": "Question",
      "name": "Was passiert, wenn ich den Diagrammtyp einstelle, aber noch niemand geantwortet hat?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Besucher sehen einfach die normale Bestätigungsmeldung. Es gibt keinen Fehler und keine leere Diagramm-Box; der Bereich erscheint erst, sobald tatsächlich Daten vorliegen."
      }
    },
    {
      "@type": "Question",
      "name": "Kann ich den Diagrammtyp ändern, nachdem schon Leute geantwortet haben?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Ja, und das wirkt rückwirkend, da die Ergebnisse bei jeder Absendung live aus allen gespeicherten Antworten berechnet werden."
      }
    },
    {
      "@type": "Question",
      "name": "Wird das Ergebnis-Diagramm auf einer eigenen Seite angezeigt?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Nein. Es erscheint eingebettet, direkt unterhalb der Bestätigungsmeldung, auf demselben Bildschirm, auf dem der Besucher das Formular gerade abgeschickt hat."
      }
    }
  ]
}
</script>
```

## Editorial SEO notes

- **Primäre Suchintention:** Lernen, wie man ein Ergebnis-Diagramm auf einem WordPress-Umfrageformular mit Easy Form Builder ein- oder ausblendet und welche Fragen einbezogen werden sollten.
- **Empfohlener Title-Tag:** Umfrageergebnisse-Diagramm in Easy Form Builder (nach dem Absenden anzeigen oder verbergen)
- **Empfohlene URL:** `/easy-form-builder-survey-results-chart/`
- **Empfohlener Auszug:** Zeige Besuchern direkt nach dem Absenden ein live berechnetes Balken- oder Kreisdiagramm der Umfrageergebnisse — die komplette, anhand des Codes geprüfte Anleitung zur Einstellung „Anzeige der Umfrageergebnisse“ in Easy Form Builder.
- **Empfohlene interne Verlinkungen:** Easy-Form-Builder-Installationsanleitung, Conditional-Logic-Dokumentation, Response-Box-Anleitung, allgemeine Erste-Schritte-Anleitung zum Formular-Baukasten.
- **Empfohlener Alt-Text für Bilder:** Einstellung „Anzeige der Umfrageergebnisse“ im WordPress-Adminbereich von Easy Form Builder.
