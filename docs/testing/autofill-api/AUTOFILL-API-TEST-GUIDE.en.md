# Real-World Test Guide: Autofill via External API (Emsfb_autofill_api_efb)

> [Docs index](../../README.md) · [Testing](../README.md) · Languages: English | [فارسی](AUTOFILL-API-TEST-GUIDE.fa.md) | [العربية](AUTOFILL-API-TEST-GUIDE.ar.md) | [Deutsch](AUTOFILL-API-TEST-GUIDE.de.md)

**Keywords:** Easy Form Builder autofill API, WordPress form autofill external API, Auto-Populate Integrations, EFB autofill api, WordPress plugin form pre-fill from REST API, field mapping form to API, search_params, response_path, Bearer token authentication form, API key authentication WordPress form, cache duration autofill, REST API form integration WordPress, JSONPlaceholder test API, dynamic form prefill from API, AI form automation, fill form fields automatically from external API.

This guide explains how to practically test the **Auto-Populate Integrations** feature (automatically filling form fields from an external API). All examples use free, public APIs (jsonplaceholder.typicode.com and httpbin.org) so you can test without setting up any extra server.

> Prerequisite: Create a test form in Easy Form Builder with several text fields, each with a clear Label and `id_`. Example: `user_id`, `full_name`, `email_field`, `phone_field`, `website_field`.

---

## Part 1 — Creating a New Connection at admin.php?page=Emsfb_autofill_api_efb

Go to **Emsfb → Auto-Populate Integrations** and click "Add New Connection". A 4-step wizard will open.

### Scenario 1: Simple GET with a URL Placeholder (No Auth)

Goal: entering a number into the `user_id` field should fetch a user from JSONPlaceholder and fill the name/email/phone/website fields.

**Step 1 - Basic Info:**
- Name: `Test User Lookup`
- Method: `GET`
- Endpoint URL: `https://jsonplaceholder.typicode.com/users/{{user_id}}`
- Body Template: empty (GET request)

**Step 2 - Authentication:**
- Auth Type: `None`
- No custom headers needed

**Step 3 - Field Mapping:**
- Target Form: select your test form
- Search Fields: check `user_id`. No need to change the "API Parameter" column (since we use `{{user_id}}` directly in the URL, not a query string)
- Response Path: empty (the API returns a single object, no path extraction needed)
- Field Mappings (api_field → form_field):
  - `name` → field `full_name`
  - `email` → field `email_field`
  - `phone` → field `phone_field`
  - `website` → field `website_field`
- Cache Duration: `0` (no caching)

**Step 4 - Test & Save:**
- Test value for `user_id`: `1`
- Click "Test" — you should see a response containing `Leanne Graham`, `Sincere@april.biz`, etc.
- Click "Save".

### Frontend Test (Scenario 1):
1. Publish the form on a page.
2. Enter a number from `1` to `10` (e.g. `3`) in the `user_id` field.
3. Leave the field via Tab, Enter, or by clicking outside (blur).
4. In DevTools → Network tab, you should see a POST request to `wp-json/Emsfb/v1/autofill/external` with a body containing `{api_id, form_id, search_data:[{id:"user_id", value:"3"}]}`.
5. The response should be `success:true, m:"done", data:[...]`, and the fields `full_name`, `email_field`, `phone_field`, `website_field` should be automatically filled with user #3's data (`Clementine Bauch`).

---

### Scenario 2: GET with Query Params Built from search_params

Goal: test the mapping between a search field and an API parameter name (`search_params`), without using a URL placeholder.

**Step 1:**
- Name: `Test Comments by Post`
- Method: `GET`
- Endpoint URL: `https://jsonplaceholder.typicode.com/comments`

**Step 2:**
- Auth Type: `None`

**Step 3:**
- Search Fields: create a new field in your form called `post_id` and check it. In the "API Parameter" column, enter `postId` (this is exactly the parameter the API expects: `?postId=1`).
- Response Path: empty (the response is an array; the backend automatically takes the first item)
- Field Mappings:
  - `name` → field `commenter_name`
  - `email` → field `commenter_email`
  - `body` → field `comment_body`

**Test:**
- Test value for `post_id`: `1`
- Expected: a real request is sent to `https://jsonplaceholder.typicode.com/comments?postId=1`, the first comment is returned, and the fields `commenter_name`, `commenter_email`, `comment_body` get filled.

> Note: if you leave the "API Parameter" field empty, the new default behavior sets `search_params[post_id] = "post_id"` (the field ID is used as the parameter name). For this scenario, make sure to explicitly enter `postId`, since the API's parameter name differs from the form field's ID.

---

### Scenario 3: POST with Body Template + Bearer Auth (Verify Request Headers and Body)

Goal: test `auth_type=bearer` and `body_template` with a placeholder. We use `httpbin.org/post` because it echoes back exactly what it received (headers, body) in its JSON response.

**Step 1:**
- Name: `Test POST with Bearer`
- Method: `POST`
- Endpoint URL: `https://httpbin.org/post`
- Body Template:
  ```json
  {"national_id": "{{national_code}}", "lookup": true}
  ```
  (add a `national_code` field to your test form)

**Step 2:**
- Auth Type: `Bearer`
- Auth Value: `my-secret-token-123`

**Step 3:**
- Search Fields: check `national_code` (the parameter name doesn't matter here since we use `body_template`, not `mapped_search_data`)
- Response Path: `json` (httpbin returns the sent body inside the `json` key)
- Field Mappings:
  - `national_id` → field `result_field` (create a new text field in the form)

**Test:**
- Test value for `national_code`: `0012345678`
- Expected: the Test response should show `national_id: "0012345678"` (i.e. the placeholder was correctly substituted), and `result_field` gets filled with the same value.
- To verify the Authorization header: click "Test" and look at the raw response under `headers.Authorization`; it should be `Bearer my-secret-token-123`.

---

### Scenario 4: API Key Auth + Custom Header

**Step 1:**
- Method: `POST`
- Endpoint URL: `https://httpbin.org/post`
- Body Template: `{"ping": "pong"}`

**Step 2:**
- Auth Type: `API Key`
- Auth Value: `abc123secret`
- Also add a custom header: Key=`X-Custom-Source`, Value=`efb-test`

**Step 3:**
- Response Path: `headers`
- Field Mappings:
  - `X-Api-Key` → field `apikey_check_field`
  - `X-Custom-Source` → field `custom_header_check_field`

**Test:**
- Click "Test". The response should show `X-Api-Key: abc123secret` and `X-Custom-Source: efb-test`, and the corresponding fields should be filled with these values.

> Note: the API Key header name is hardcoded as `X-API-Key` in the code (`build_headers()`), but httpbin normalizes header names to title case (`X-Api-Key`) — if the value comes back empty, change the field mapping key to `X-Api-Key`.

---

## Part 2 — Testing in the Form Builder

After creating the connections in Part 1, open your test form in the form builder:

1. In the form's general settings (the first/form-level row), enable AutoFill with the **External API** mode and select the connection you created (e.g. Scenario 1).
2. A large purple/pink info card titled "API AutoFill Integration is Active" should appear **only once**, at the form-settings level (not per field).
3. Go to the fields that are configured as targets in that connection's `field_mappings` (e.g. `full_name`, `email_field`, `phone_field`, `website_field`). Each one should show a small badge "Auto-filled via External API".
4. Fields that are not part of the mapping (other form fields) should not show any card or badge.
5. Save the form.

---

## Part 3 — Testing the Cache

1. Edit one of the connections (e.g. Scenario 1) and set `Cache Duration` to `1` (minute), then save.
2. Open the published form, enter `user_id = 1` and leave the field (blur).
3. In DevTools → Network, check the first response — it should not contain `cached`, or it should not be `true` (a real API call was made).
4. Reload the page and enter `user_id = 1` again (within the same minute).
5. The second response should contain `"cached": true` — meaning it was read from the `transient` cache, not the API.
6. After 1 minute passes, repeating the request should call the API again (without `cached`).

---

## Part 4 — Error Scenarios

| Scenario | Configuration | Expected Result |
|---|---|---|
| Invalid endpoint | Change the Endpoint URL to `https://does-not-exist.invalid/api` | REST response with `success:false`, code 500, and a connection error message |
| HTTP error code ≥ 400 | Change the endpoint to `https://httpbin.org/status/404` | `api_returned_error` message with status code 404 |
| Invalid JSON response | Change the endpoint to `https://httpbin.org/html` (returns HTML) | `parse_error` error |
| No matching field_mappings | Leave `field_mappings` empty, or map to fields that don't exist in the API response | `success:false` response with `no_matching_data` message (code 200) |
| Form not found | Change `target_form_id` to a deleted form's ID (or corrupt `form_id` directly in `emsfb_autofill_api_settings` in the database) | `form_not_found` response, code 404 |
| Disabled connection | Toggle one of the connections off from the list, then test from the frontend | The external request should not succeed / the connection should be treated as disabled |

---

## Summary Checklist

- [ ] Scenario 1 (GET + URL Placeholder, no auth) — fields get filled
- [ ] Scenario 2 (GET + search_params → query string) — fields get filled
- [ ] Scenario 3 (POST + body_template + Bearer) — Authorization header and body sent correctly
- [ ] Scenario 4 (API Key + custom header) — both headers visible in the external request
- [ ] Form Builder: large card shown only once at form level, badges only on mapped fields
- [ ] Cache: second request within `cache_duration` returns `cached:true`
- [ ] Errors: each case in Part 4's table returns the appropriate message
