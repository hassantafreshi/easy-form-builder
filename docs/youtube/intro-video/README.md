# Easy Form Builder intro video: YouTube kit

Everything needed to publish the 1-minute intro video (`efb-promo-voice`, 1920×1080, 1:01) on YouTube with SEO and GEO in mind: metadata, captions, thumbnails, and the structured data for embedding the video on whitestudio.team.

| File | Use |
|---|---|
| `description.en.txt` | Main description (English, about 3,700 of the 5,000 allowed characters) |
| `description.fa.txt` | Persian description (YouTube → Languages → add translation) |
| `captions.en.srt` | English captions (YouTube → Subtitles → upload file) |
| `captions.fa.srt` | Persian captions |
| `thumbnails/*.png` | 3 thumbnails, 1280×720, each under 2 MB |

---

## Title

**English (91 characters):**

```
Easy Form Builder: Free Drag & Drop Form Builder for WordPress | All Your Forms, One Plugin
```

**Persian (for the translation):**

```
فرم‌ساز آسان: افزونه فرم ساز رایگان وردپرس با کشیدن و رها کردن | همه فرم‌ها در یک افزونه
```

The first 60 to 70 characters show in search results, so the main keywords ("Easy Form Builder", "Free", "Form Builder", "WordPress") come first.

## Tags

```
easy form builder, wordpress form builder, free wordpress form builder, wordpress form plugin, drag and drop form builder, wordpress contact form, wordpress payment form, stripe payment form, paypal payment form, multi step form wordpress, conditional logic form, wordpress survey form, wordpress registration form, file upload form, no code form builder, whitestudio, فرم ساز وردپرس, افزونه فرم ساز
```

## Pinned comment

```
👋 Thanks for watching! Get Easy Form Builder free: https://wordpress.org/plugins/easy-form-builder/
Docs, guides & support: https://whitestudio.team/

Which form will you build first: a contact form, a payment form or a registration form? Tell us below 👇
```

## Thumbnails (A/B/C test)

Upload all three with **Test & Compare** (YouTube Studio → Thumbnail → Test & compare). YouTube then keeps the one with the best watch time.

| File | Idea |
|---|---|
| `thumbnail-a-every-form-one-plugin.png` | Main message of the video, "Every Form. One Plugin.", with real UI cards (checkout, voice message, multi-step) |
| `thumbnail-b-8-plugins-to-1.png` | Before and after: 8 form plugins → 1 plugin |
| `thumbnail-c-drag-drop-done.png` | "Drag. Drop. Done." with the builder screenshot and a FREE badge |

All three use the video's colors, font (Plus Jakarta Sans) and logo, and leave the bottom-right corner clear, where YouTube shows the video length.

## Upload checklist

1. Rename the file before uploading, for example `easy-form-builder-free-wordpress-form-builder.mp4`.
2. Paste the title and `description.en.txt`. Chapters (Key Moments) are created automatically from the timestamps in the description.
3. Thumbnails: Test & Compare with A, B and C.
4. Tags: the list above.
5. Video language: **English**. Category: **Science & Technology**.
6. Subtitles: upload `captions.en.srt` (English) and `captions.fa.srt` (Persian). Uploaded captions are more accurate than automatic ones, so search engines and AI tools index the right words.
7. Languages → add translation: Persian title + `description.fa.txt`.
8. Playlist: add the video to an "Easy Form Builder Tutorials" playlist.
9. End screen (last 5 seconds): Subscribe button + a tutorial video or the playlist.
10. Pin the comment above.
11. After publishing:
    - Replace the video ID in the `readme.txt` embed (`[youtube https://www.youtube.com/watch?v=...]`) so the WordPress.org page shows the new video.
    - Embed the video on the whitestudio.team plugin page and add the JSON-LD below to the same page.

## Structured data for whitestudio.team (GEO)

Put this on the page where the video is embedded. It tells Google (video rich results, Key Moments) and AI assistants what the video and the product are. Replace `VIDEO_ID` and `uploadDate` after publishing.

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "VideoObject",
  "name": "Easy Form Builder: Free Drag & Drop Form Builder for WordPress",
  "description": "Easy Form Builder is a free drag-and-drop form builder plugin for WordPress. Create contact forms, Stripe and PayPal payment forms, surveys, login and registration forms, file upload and multi-step forms in one plugin, with no code.",
  "thumbnailUrl": "https://i.ytimg.com/vi/VIDEO_ID/maxresdefault.jpg",
  "uploadDate": "2026-11-06",
  "duration": "PT1M1S",
  "embedUrl": "https://www.youtube.com/embed/VIDEO_ID",
  "inLanguage": "en",
  "publisher": {
    "@type": "Organization",
    "name": "WhiteStudio",
    "url": "https://whitestudio.team/"
  },
  "about": {
    "@type": "SoftwareApplication",
    "name": "Easy Form Builder",
    "applicationCategory": "BusinessApplication",
    "operatingSystem": "WordPress",
    "url": "https://wordpress.org/plugins/easy-form-builder/",
    "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD" }
  },
  "hasPart": [
    { "@type": "Clip", "name": "Too many WordPress form plugins?", "startOffset": 0, "endOffset": 11, "url": "https://www.youtube.com/watch?v=VIDEO_ID&t=0" },
    { "@type": "Clip", "name": "Drag & drop builder: contact, support & survey forms", "startOffset": 11, "endOffset": 24, "url": "https://www.youtube.com/watch?v=VIDEO_ID&t=11" },
    { "@type": "Clip", "name": "Stripe & PayPal payment forms, login & sign-up forms", "startOffset": 24, "endOffset": 34, "url": "https://www.youtube.com/watch?v=VIDEO_ID&t=24" },
    { "@type": "Clip", "name": "File uploads, voice & video recording, smart forms", "startOffset": 34, "endOffset": 46, "url": "https://www.youtube.com/watch?v=VIDEO_ID&t=34" },
    { "@type": "Clip", "name": "Job applications, events & email notifications", "startOffset": 46, "endOffset": 61, "url": "https://www.youtube.com/watch?v=VIDEO_ID&t=46" }
  ],
  "transcript": "Contact forms here. Payments there. Surveys in yet another plugin. Sound familiar? Meet Easy Form Builder. Every form your site needs in one plugin. Just drag, drop, and publish. No code. No developer. Live in minutes. Create contact forms, support forms with message tracking, and surveys with instant results. Get paid with Stripe or PayPal, one time or on a subscription. Add login and sign-up forms, or keep any form members-only. Collect files, voice messages, video testimonials, even screen recordings for bug reports. Go further with multi-step forms, conditional logic, e-signatures, and a location picker. Hiring? Hosting an event? Covered, with beautiful email notifications built right in. All your forms. One plugin. Easy Form Builder. Download it free from WordPress.org today."
}
</script>
```

## Why this works for SEO and GEO

- **First 2 lines of the description** (the part shown before "more"): what the product is, who it is for, and the download link.
- **One clear definition sentence** ("Easy Form Builder is a free drag-and-drop form builder plugin for WordPress…"), repeated the same way in the description, the FAQ and the JSON-LD. AI assistants (ChatGPT, Gemini, Perplexity, Google AI Overviews) quote sentences like this.
- **FAQ in question form**, matching how people ask: "Is Easy Form Builder free?", "Can a WordPress form accept payments?".
- **Chapters** with keyword titles, which become Key Moments in Google.
- **Accurate captions** in English and Persian, so the spoken keywords are indexed.
- **Checked facts only** (taken from `readme.txt`), so nothing in the description is wrong when an AI repeats it.
- **No keyword stuffing**: keywords appear in natural sentences, and the tags and hashtags stay within YouTube's limits.
