# Praxis-Testanleitung: Autofill über externe API (Emsfb_autofill_api_efb)

> [Docs index](../../README.md) · [Testing](../README.md) · Sprachen: Deutsch | [English](AUTOFILL-API-TEST-GUIDE.en.md) | [فارسی](AUTOFILL-API-TEST-GUIDE.fa.md) | [العربية](AUTOFILL-API-TEST-GUIDE.ar.md)

**Schlüsselwörter / Keywords:** Easy Form Builder autofill API, WordPress Formular Autofill externe API, Auto-Populate Integrations, EFB autofill api, WordPress Plugin Formular automatisch ausfüllen über REST API, Feldzuordnung Formular zu API (field mapping), search_params, response_path, Bearer-Token-Authentifizierung Formular, API-Key-Authentifizierung WordPress Formular, Cache Duration Autofill, REST-API-Integration WordPress Formular, JSONPlaceholder Test-API, dynamisches Formular-Prefill von API, KI-Formularautomatisierung, Formularfelder automatisch aus externer API befüllen.

Diese Anleitung beschreibt, wie das Feature **Auto-Populate Integrations** (automatisches Ausfüllen von Formularfeldern aus einer externen API) praktisch getestet wird. Alle Beispiele nutzen kostenlose, öffentliche APIs (jsonplaceholder.typicode.com und httpbin.org), sodass kein zusätzlicher Server benötigt wird.

> Voraussetzung: Erstellen Sie ein Testformular in Easy Form Builder mit mehreren Textfeldern, jeweils mit eindeutigem Label und `id_`. Beispiel: `user_id`, `full_name`, `email_field`, `phone_field`, `website_field`.

---

## Teil 1 — Neue Verbindung unter admin.php?page=Emsfb_autofill_api_efb erstellen

Gehen Sie zu **Emsfb → Auto-Populate Integrations** und klicken Sie auf "Neue Verbindung hinzufügen". Ein 4-Schritte-Wizard öffnet sich.

### Szenario 1: Einfacher GET mit URL-Platzhalter (ohne Auth)

Ziel: Die Eingabe einer Zahl im Feld `user_id` soll einen Benutzer von JSONPlaceholder abrufen und die Felder Name/E-Mail/Telefon/Webseite befüllen.

**Schritt 1 - Basisinformationen:**
- Name: `Test User Lookup`
- Method: `GET`
- Endpoint URL: `https://jsonplaceholder.typicode.com/users/{{user_id}}`
- Body Template: leer (GET-Request)

**Schritt 2 - Authentifizierung:**
- Auth Type: `None`
- Keine benutzerdefinierten Header nötig

**Schritt 3 - Field Mapping:**
- Target Form: Ihr Testformular auswählen
- Search Fields: `user_id` aktivieren. Die Spalte "API Parameter" muss nicht geändert werden (da `{{user_id}}` direkt in der URL verwendet wird, nicht als Query-String)
- Response Path: leer (die API gibt ein einzelnes Objekt zurück, keine Pfadextraktion nötig)
- Field Mappings (api_field → form_field):
  - `name` → Feld `full_name`
  - `email` → Feld `email_field`
  - `phone` → Feld `phone_field`
  - `website` → Feld `website_field`
- Cache Duration: `0` (kein Caching)

**Schritt 4 - Test & Save:**
- Testwert für `user_id`: `1`
- Klicken Sie auf "Test" — es sollte eine Antwort mit `Leanne Graham`, `Sincere@april.biz` usw. erscheinen.
- Klicken Sie auf "Save".

### Frontend-Test (Szenario 1):
1. Veröffentlichen Sie das Formular auf einer Seite.
2. Geben Sie eine Zahl zwischen `1` und `10` (z. B. `3`) in das Feld `user_id` ein.
3. Verlassen Sie das Feld per Tab, Enter oder Klick außerhalb (Blur).
4. In den DevTools → Network-Tab sollte ein POST-Request an `wp-json/Emsfb/v1/autofill/external` mit einem Body wie `{api_id, form_id, search_data:[{id:"user_id", value:"3"}]}` erscheinen.
5. Die Antwort sollte `success:true, m:"done", data:[...]` sein, und die Felder `full_name`, `email_field`, `phone_field`, `website_field` sollten automatisch mit den Daten von Benutzer #3 (`Clementine Bauch`) befüllt werden.

---

### Szenario 2: GET mit Query-Params aus search_params

Ziel: Testen der Zuordnung zwischen einem Suchfeld und einem API-Parameternamen (`search_params`), ohne URL-Platzhalter.

**Schritt 1:**
- Name: `Test Comments by Post`
- Method: `GET`
- Endpoint URL: `https://jsonplaceholder.typicode.com/comments`

**Schritt 2:**
- Auth Type: `None`

**Schritt 3:**
- Search Fields: Erstellen Sie ein neues Feld `post_id` im Formular und aktivieren Sie es. In der Spalte "API Parameter" geben Sie `postId` ein (genau der Parameter, den die API erwartet: `?postId=1`).
- Response Path: leer (die Antwort ist ein Array; das Backend nimmt automatisch das erste Element)
- Field Mappings:
  - `name` → Feld `commenter_name`
  - `email` → Feld `commenter_email`
  - `body` → Feld `comment_body`

**Test:**
- Testwert für `post_id`: `1`
- Erwartung: Ein echter Request wird an `https://jsonplaceholder.typicode.com/comments?postId=1` gesendet, der erste Kommentar wird zurückgegeben, und die Felder `commenter_name`, `commenter_email`, `comment_body` werden befüllt.

> Hinweis: Wenn Sie das Feld "API Parameter" leer lassen, setzt das neue Standardverhalten `search_params[post_id] = "post_id"` (die Feld-ID wird als Parametername verwendet). Für dieses Szenario muss `postId` explizit eingegeben werden, da der API-Parametername von der Formularfeld-ID abweicht.

---

### Szenario 3: POST mit Body Template + Bearer Auth (Header und Body prüfen)

Ziel: Testen von `auth_type=bearer` und `body_template` mit Platzhalter. Wir nutzen `httpbin.org/post`, da es genau das zurückgibt, was es empfangen hat (Header, Body) als JSON-Antwort.

**Schritt 1:**
- Name: `Test POST with Bearer`
- Method: `POST`
- Endpoint URL: `https://httpbin.org/post`
- Body Template:
  ```json
  {"national_id": "{{national_code}}", "lookup": true}
  ```
  (Feld `national_code` zum Testformular hinzufügen)

**Schritt 2:**
- Auth Type: `Bearer`
- Auth Value: `my-secret-token-123`

**Schritt 3:**
- Search Fields: `national_code` aktivieren (der Parametername ist hier unwichtig, da `body_template` statt `mapped_search_data` verwendet wird)
- Response Path: `json` (httpbin gibt den gesendeten Body innerhalb des Schlüssels `json` zurück)
- Field Mappings:
  - `national_id` → Feld `result_field` (neues Textfeld im Formular erstellen)

**Test:**
- Testwert für `national_code`: `0012345678`
- Erwartung: Die Test-Antwort sollte `national_id: "0012345678"` zeigen (d. h. der Platzhalter wurde korrekt ersetzt), und `result_field` wird mit demselben Wert befüllt.
- Zur Überprüfung des Authorization-Headers: Klicken Sie auf "Test" und prüfen Sie in der rohen Antwort `headers.Authorization`; der Wert sollte `Bearer my-secret-token-123` sein.

---

### Szenario 4: API-Key-Auth + benutzerdefinierter Header

**Schritt 1:**
- Method: `POST`
- Endpoint URL: `https://httpbin.org/post`
- Body Template: `{"ping": "pong"}`

**Schritt 2:**
- Auth Type: `API Key`
- Auth Value: `abc123secret`
- Fügen Sie zusätzlich einen benutzerdefinierten Header hinzu: Key=`X-Custom-Source`, Value=`efb-test`

**Schritt 3:**
- Response Path: `headers`
- Field Mappings:
  - `X-Api-Key` → Feld `apikey_check_field`
  - `X-Custom-Source` → Feld `custom_header_check_field`

**Test:**
- Klicken Sie auf "Test". Die Antwort sollte `X-Api-Key: abc123secret` und `X-Custom-Source: efb-test` enthalten, und die entsprechenden Felder werden mit diesen Werten befüllt.

> Hinweis: Der API-Key-Header-Name ist im Code (`build_headers()`) fest als `X-API-Key` hinterlegt, aber httpbin normalisiert Header-Namen in Title-Case (`X-Api-Key`) — kommt der Wert leer zurück, ändern Sie den Field-Mapping-Schlüssel auf `X-Api-Key`.

---

## Teil 2 — Test im Form Builder

Nachdem die Verbindungen aus Teil 1 erstellt wurden, öffnen Sie Ihr Testformular im Form Builder:

1. Aktivieren Sie in den allgemeinen Formulareinstellungen (erste Zeile/Formularebene) AutoFill im Modus **External API** und wählen Sie die erstellte Verbindung (z. B. Szenario 1).
2. Es sollte eine große violette/pinke Infokarte mit dem Titel "API AutoFill Integration is Active" **nur einmal** auf Formularebene erscheinen (nicht pro Feld).
3. Gehen Sie zu den Feldern, die in den `field_mappings` dieser Verbindung als Ziel festgelegt sind (z. B. `full_name`, `email_field`, `phone_field`, `website_field`). Jedes davon sollte ein kleines Badge "Auto-filled via External API" anzeigen.
4. Felder, die nicht Teil des Mappings sind (andere Formularfelder), sollten keine Karte oder Badge anzeigen.
5. Speichern Sie das Formular.

---

## Teil 3 — Cache-Test

1. Bearbeiten Sie eine der Verbindungen (z. B. Szenario 1) und setzen Sie `Cache Duration` auf `1` (Minute), dann speichern.
2. Öffnen Sie das veröffentlichte Formular, geben Sie `user_id = 1` ein und verlassen Sie das Feld (Blur).
3. Prüfen Sie in den DevTools → Network die erste Antwort — sie sollte kein `cached` enthalten oder nicht `true` sein (ein echter API-Aufruf wurde gemacht).
4. Laden Sie die Seite neu und geben Sie erneut `user_id = 1` ein (innerhalb derselben Minute).
5. Die zweite Antwort sollte `"cached": true` enthalten — d. h., sie wurde aus dem `transient`-Cache gelesen, nicht aus der API.
6. Nach Ablauf von 1 Minute sollte eine erneute Anfrage wieder die API aufrufen (ohne `cached`).

---

## Teil 4 — Fehlerszenarien

| Szenario | Konfiguration | Erwartetes Ergebnis |
|---|---|---|
| Ungültiger Endpoint | Endpoint URL auf `https://does-not-exist.invalid/api` ändern | REST-Antwort mit `success:false`, Code 500, Verbindungsfehlermeldung |
| HTTP-Fehlercode ≥ 400 | Endpoint auf `https://httpbin.org/status/404` ändern | `api_returned_error`-Meldung mit Statuscode 404 |
| Ungültige JSON-Antwort | Endpoint auf `https://httpbin.org/html` ändern (gibt HTML zurück) | `parse_error`-Fehler |
| Keine passenden field_mappings | `field_mappings` leer lassen oder auf Felder verweisen, die nicht in der API-Antwort existieren | `success:false`-Antwort mit `no_matching_data` (Code 200) |
| Formular nicht gefunden | `target_form_id` auf die ID eines gelöschten Formulars setzen (oder `form_id` direkt in `emsfb_autofill_api_settings` in der Datenbank manipulieren) | `form_not_found`-Antwort, Code 404 |
| Deaktivierte Verbindung | Eine Verbindung in der Liste deaktivieren, dann im Frontend testen | Der externe Request sollte nicht erfolgreich sein / die Verbindung sollte als deaktiviert behandelt werden |

---

## Zusammenfassende Checkliste

- [ ] Szenario 1 (GET + URL-Platzhalter, ohne Auth) — Felder werden befüllt
- [ ] Szenario 2 (GET + search_params → Query-String) — Felder werden befüllt
- [ ] Szenario 3 (POST + body_template + Bearer) — Authorization-Header und Body korrekt gesendet
- [ ] Szenario 4 (API Key + benutzerdefinierter Header) — beide Header im externen Request sichtbar
- [ ] Form Builder: große Karte erscheint nur einmal auf Formularebene, Badges nur auf zugeordneten Feldern
- [ ] Cache: zweiter Request innerhalb von `cache_duration` liefert `cached:true`
- [ ] Fehler: jeder Fall aus der Tabelle in Teil 4 liefert die passende Meldung
