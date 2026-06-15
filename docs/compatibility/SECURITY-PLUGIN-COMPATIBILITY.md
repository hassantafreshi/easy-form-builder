---
title: "Security Plugin Compatibility with Easy Form Builder – Fix 403 Errors"
description: "Learn how to fix 403 errors caused by security plugins blocking Easy Form Builder form submissions. Step-by-step guide for Wordfence, iThemes Security, All In One WP Security, and more."
slug: security-plugin-compatibility
date: 2026-04-27
last_modified: 2026-04-27
author: WhiteStudio Team
tags: [easy-form-builder, security plugins, 403 error, REST API, WordPress, form submissions, Wordfence, iThemes Security]
canonical: https://whitestudio.team/document/security-plugin-compatibility/
---

# Security Plugin Compatibility with Easy Form Builder

> [Docs index](../README.md)

> **Quick summary:** If users are getting **403 Forbidden errors** when submitting forms, a security plugin on your WordPress site is most likely the cause. This guide explains why it happens and how to fix it — for every major security plugin.

---

## Table of Contents

1. [Why Security Plugins Break Form Submissions](#why-security-plugins-break-form-submissions)
2. [How to Confirm a Security Plugin is the Cause](#how-to-confirm-a-security-plugin-is-the-cause)
3. [Common Causes](#common-causes)
4. [Fix by Plugin](#fix-by-plugin)
   - [Wordfence](#wordfence)
   - [iThemes Security / Solid Security](#ithemes-security--solid-security)
   - [All In One WP Security & Firewall](#all-in-one-wp-security--firewall)
   - [Sucuri Security](#sucuri-security)
   - [Shield Security](#shield-security)
   - [WP Cerber Security](#wp-cerber-security)
5. [General Fix for Any Security Plugin](#general-fix-for-any-security-plugin)
6. [How Easy Form Builder Detects Security Plugins](#how-easy-form-builder-detects-security-plugins)
7. [Frequently Asked Questions](#frequently-asked-questions)

---

## Why Security Plugins Break Form Submissions

Easy Form Builder uses the **WordPress REST API** to submit forms. When a user fills out a form and clicks submit, the browser sends a `POST` request to a REST API endpoint (`/wp-json/...`) along with a security token called the **X-WP-Nonce header**.

Security plugins — while excellent for protecting your site — sometimes block this communication in the following ways:

| What the Security Plugin Does | Effect on Easy Form Builder |
|---|---|
| Disables REST API for non-logged-in users | Anonymous visitors cannot submit forms → 403 error |
| Blocks or strips the `X-WP-Nonce` header | Nonce verification fails → 403 error |
| WAF (Web Application Firewall) rules flag form data as suspicious | Request blocked before reaching WordPress → 403 error |
| Login protection / rate limiting on POST requests | Form submission treated as a brute-force attempt → 403 error |
| Disables REST API endpoints entirely | All form submissions fail |

---

## How to Confirm a Security Plugin is the Cause

**Step 1:** Open your browser's developer tools (press `F12`) and go to the **Network** tab.

**Step 2:** Submit a form on your website.

**Step 3:** Look for a failed request to a URL containing `/wp-json/`. If the status code is **403**, a security plugin is blocking the request.

**Step 4:** Temporarily deactivate your security plugin and try submitting the form again. If it works, the security plugin is confirmed as the cause.

> **Note:** Easy Form Builder will also display a warning notice in your WordPress admin dashboard when it detects a known security plugin that may cause compatibility issues.

---

## Common Causes

### 1. "Disable REST API for non-logged-in users"

This is the most common cause. Many security plugins offer a setting to restrict REST API access to logged-in users only. Since form submissions from anonymous visitors use the REST API, this setting will block all form submissions.

**Solution:** Whitelist Easy Form Builder's REST API namespace (`efb/v1` or `emsfb/v1`) or disable this restriction entirely.

---

### 2. X-WP-Nonce Header Being Stripped or Blocked

Easy Form Builder passes a nonce token via the `X-WP-Nonce` HTTP header for CSRF protection. Some WAF rules or header-filtering rules treat custom headers as suspicious and remove them. When the nonce is missing, WordPress rejects the request with a 403 error.

**Solution:** Configure your security plugin or server to allow the `X-WP-Nonce` header through on REST API requests.

---

### 3. WAF Rules Flagging Form Data

Web Application Firewall (WAF) rules may flag certain form field values (e.g., fields containing `<`, `>`, SQL keywords, or special characters) and block the request.

**Solution:** Add an exclusion rule for Easy Form Builder's REST API endpoint in your WAF settings.

---

### 4. Login Protection / Rate Limiting

Some security plugins apply aggressive rate limiting to all POST requests, not just login attempts. This can prevent form submissions from going through.

**Solution:** Whitelist the Easy Form Builder REST API endpoint from rate limiting rules.

---

## Fix by Plugin

### Wordfence

1. Go to **Wordfence → Firewall → Firewall Options**.
2. Under **Whitelisted URLs**, add: `/wp-json/efb/` and `/wp-json/emsfb/`
3. If you use "Login Security" with rate limiting on REST endpoints, create an exception for Easy Form Builder's namespace.
4. Go to **Wordfence → All Options** and ensure "Disable the WordPress REST API" is **not** enabled, or that unauthenticated access is allowed for `efb` and `emsfb` namespaces.

---

### iThemes Security / Solid Security

1. Go to **Security → Settings → WordPress Tweaks**.
2. Find the **REST API** setting. Set it to **"Default WordPress API access"** (not "Restricted Access").
3. If you need to keep REST API restrictions, go to **Security → Tools → IP Manager** and whitelist the API namespaces used by Easy Form Builder.

---

### All In One WP Security & Firewall

1. Go to **WP Security → Firewall → Basic Firewall Rules**.
2. Find "Disable REST API for non-logged-in users" and **uncheck** it, or set it to allow the Easy Form Builder namespace.
3. If you use the **6G/7G Firewall**, it may block certain POST request patterns. Add an exclusion for `/wp-json/efb/` and `/wp-json/emsfb/`.

---

### Sucuri Security

1. Log into your **Sucuri Dashboard** (cloud-based WAF).
2. Go to **Firewall → Whitelist**.
3. Add the URL paths `/wp-json/efb/` and `/wp-json/emsfb/` to the whitelist.
4. If you use Sucuri's **"Block PHP Files in Uploads"** or similar rules, ensure they don't affect REST API requests.

---

### Shield Security

1. Go to **Shield Security → Config → WordPress REST API**.
2. Set the REST API access to **"Default (WP Core)"** or create a custom rule that allows `efb` and `emsfb` namespaces for all users.
3. If you have **Silent Captcha** or bot protection enabled, ensure it excludes REST API POST requests from Easy Form Builder. Easy Form Builder includes built-in Shield Silent Captcha compatibility — see the plugin settings under **Integrations**.

---

### WP Cerber Security

1. Go to **WP Cerber → Anti-spam & Security Rules**.
2. Find the **REST API** access section and ensure it allows unauthenticated access to the `efb` and `emsfb` namespaces.
3. Under **Traffic Inspector**, add exceptions for Easy Form Builder's REST API endpoints to prevent form data from being flagged as malicious traffic.

---

## General Fix for Any Security Plugin

If your security plugin is not listed above, follow these general steps:

1. **Find the REST API restriction setting** in your security plugin and either disable it or create an exception for the following URL patterns:
   - `/wp-json/efb/`
   - `/wp-json/emsfb/`

2. **Allow the `X-WP-Nonce` header** to pass through without being stripped.

3. **Whitelist form submission endpoints** in your WAF settings to prevent form data from being blocked.

4. **Check rate limiting settings** and exclude REST API POST requests from Easy Form Builder.

5. **Test after each change**: Use your browser's Network tab to verify that form submissions return a `200` status code instead of `403`.

---

## How Easy Form Builder Detects Security Plugins

Starting from Easy Form Builder **v4**, the plugin automatically scans your active plugins and identifies known security plugins that may cause compatibility issues. When a known security plugin is detected:

- A **warning notice** appears in your WordPress admin dashboard.
- The notice shows the plugin name, version, and the most common causes of 403 errors.
- A link to this guide is provided for quick resolution.

This detection is purely informational — Easy Form Builder does not modify or interfere with your security plugin's settings.

---

## Frequently Asked Questions

### Will disabling REST API restrictions affect my site's security?

Disabling REST API restrictions for Easy Form Builder's specific namespaces (`efb`, `emsfb`) has minimal security impact. Anonymous users will only be able to submit forms — they cannot access sensitive WordPress data. You are simply allowing form submissions, not opening up the entire REST API.

---

### My form works when I'm logged in but fails for visitors. What's wrong?

This is the classic symptom of "Disable REST API for non-logged-in users" being enabled. Logged-in users pass the security check, but anonymous visitors are blocked. Follow the steps for your specific security plugin above.

---

### The 403 error only happens with certain form fields. Why?

Your WAF is likely triggering on specific content in those fields (e.g., special characters, HTML tags, certain keywords). Check your WAF logs for blocked requests and add an exclusion rule for Easy Form Builder's endpoint.

---

### Easy Form Builder shows a security plugin warning, but forms work fine. Should I be concerned?

If your forms are working correctly, the warning is informational only. The detected security plugin *could* cause issues depending on its configuration. No action is required if submissions are working. You can dismiss the notice from the admin dashboard.

---

### I'm using a cloud-based WAF (Cloudflare, Sucuri, etc.). What should I do?

Cloud-based WAFs operate outside of WordPress and require whitelisting at the WAF dashboard level. Log into your WAF provider's dashboard and whitelist the paths `/wp-json/efb/` and `/wp-json/emsfb/`, and ensure the `X-WP-Nonce` header is not being stripped.

---

## Still Having Issues?

If you've followed this guide and form submissions are still failing:

1. Go to **Easy Form Builder → Settings → Advanced** and check the debug log for error details.
2. Contact our support team at [whitestudio.team](https://whitestudio.team) with your security plugin name and the error details from the log.

---

*Last updated: April 2026 · Easy Form Builder v4+*
