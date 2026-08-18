---
title: "How to Let Clients Upload Files to Your WordPress Site (Without Code)"
slug: "let-clients-upload-files-wordpress"
meta_description: "Stop chasing email attachments. Add a file upload form to your WordPress site in about five minutes — no code, no developer, and no FTP access needed."
focus_keyphrase: "let clients upload files to your WordPress site"
secondary_keyphrases:
  - "WordPress form to collect documents from clients"
  - "add a file upload field to a WordPress form"
  - "receive files from customers WordPress"
  - "accept CV and PDF uploads WordPress form"
  - "free WordPress file upload form plugin"
  - "stop asking clients to email attachments"
search_intent: "How-to — a non-technical site owner who needs to receive files from visitors"
audience: "WordPress site owners and beginners, no coding experience assumed"
product_version: "Easy Form Builder 4.1.3 and later"
last_reviewed: "2026-08-17"
---

# How to Let Clients Upload Files to Your WordPress Site (Without Code)

**Keywords:** let clients upload files to your WordPress site, WordPress form to collect documents from clients, add a file upload field to a WordPress form, receive files from customers WordPress, accept CV and PDF uploads WordPress form, free WordPress file upload form plugin.

**Breadcrumb:** Easy Form Builder Blog › File Uploads · Languages: English

If you have ever written *"just email me the file"* and then spent three days chasing it, you already know the problem. Attachments bounce because they are too big. Transfer links expire before you click them. The one file you need is buried in a thread from last month.

There is a better way, and it takes about five minutes: put an upload box directly on your website. The visitor picks their file, hits send, and it lands on your site with their name and message attached to it.

No code. No developer. No FTP.

## Quick answer

- Install the **Easy Form Builder** plugin from your WordPress dashboard — it is free and the upload field is included.
- Create a form, drag in the **File upload** field, and save.
- Copy the shortcode it gives you, such as `[EMS_Form_Builder id=12]`, and paste it into any page or post.
- Set **Acceptable file types** and **Max File Size** on the field so you only receive what you actually want.
- Uploaded files are checked on your server automatically, renamed so nobody can guess their address, and attached to the submission you receive.

## What you need before you start

Just two things:

1. A WordPress site where you can install plugins (you are an administrator).
2. A rough idea of what you are collecting — photos, PDFs, a CV, a signed contract.

That is genuinely it. You do not need hosting access, and you do not need to touch a single line of code.

## Step 1 — Install the plugin

In your WordPress dashboard go to **Plugins → Add New**, search for **Easy Form Builder**, then click **Install Now** and **Activate**.

A new **Easy Form Builder** item appears in your dashboard menu on the left.

## Step 2 — Create your form

Go to **Easy Form Builder → Create**.

Give the form a name you will recognise later — "Document upload" or "Send us your CV" is plenty. Vague names like "Form 1" become a problem the moment you have three of them.

## Step 3 — Add the upload field

In the form builder you will see the available fields listed for you to place. You have two upload options, and the difference is only how it looks to the visitor:

| Field | What the visitor sees |
| --- | --- |
| **File upload** | A normal "choose a file" button |
| **D&D File Upload** | A drop area they can drag a file straight into |

Drag whichever one fits into your form. Most people also add a **Name** and an **Email** field above it, so you know who sent what.

That is the whole form. Really.

## Step 4 — Tell it what you accept

Click your upload field and you will find two settings that are worth ten seconds each. Skipping them is the single most common regret:

**Acceptable file types** — choose the narrowest option that fits the job:

- **Image** for photos and screenshots
- **Document** for PDFs, Word files, and spreadsheets
- **Media** for audio and video
- **Zip** for archives
- **Customize** if you want to list exact file types yourself

Collecting CVs? Choose **Document**. Now nobody can send you a 200 MB video by mistake.

**Max File Size** — set the largest file you genuinely expect. If you leave it empty the limit is 20 MB. Your hosting plan has its own limit too, and the smaller of the two always wins.

## Step 5 — Put the form on a page

Save the form. Easy Form Builder gives you a shortcode that looks like this:

```
[EMS_Form_Builder id=12]
```

Copy it, open the page or post where you want the form, paste it in, and update the page.

Visit the page as a visitor. Your upload form is live.

## What happens after someone uploads a file

The file is saved to your site's normal uploads folder and attached to that person's submission, so you always see the file together with the name, email, and message that came with it. You read submissions under **Easy Form Builder → Panel**.

Two small things happen automatically that people are usually glad to hear about:

- **Every file is renamed** to something random like `efb-PLG-260817-1NBGMJ30.zip`. Nobody can guess the address of a file somebody else sent you — which matters a lot the first time a client uploads a signed contract.
- **Files from abandoned forms are cleaned up.** If someone picks a file and then wanders off without submitting, that file is removed automatically after 24 hours instead of quietly filling your disk.

## "Is it safe to let strangers upload files to my site?"

It is a fair question, and it is the right one to ask. An upload box is the only part of a website that lets a stranger put a file on your server, so it deserves a moment of thought.

The short answer: the risky part is handled for you, and you do not have to configure it.

Easy Form Builder checks every file on your server rather than trusting the visitor's browser. Programs and scripts — things ending in `.php`, `.exe`, `.sh` and similar — are refused no matter how your form is set up. Renaming a file does not help either, because the plugin looks inside the file to confirm it really is what its name claims. A script renamed to `photo.jpg` is turned away.

So a normal contact or application form with an upload field is safe to publish today.

Where it is worth doing more is when a form is **public, popular, and attracts bots** — a careers page, a support form, anything linked from social media. Those forms get hit by automated traffic, and automated traffic is a different problem from a bad file: it is about volume, not content.

That is what the **Form Security & Spam Protection** add-on is for. It is part of the Pro package, and it adds the layer the standard checks deliberately leave alone — limiting how fast a single visitor can submit or upload, keeping a blocklist, and telling the difference between a person filling in a form and a script posting to it. If your form is behind a login or only linked from one quiet page, you can comfortably leave this for later.

For the full detail on what is checked and when, there is a separate guide: [How to Secure File Uploads on a WordPress Form](../../uploads/EFB-Secure-File-Uploads-Guide.en.md).

## Frequently asked questions

### Do I need to know how to code to add a file upload form?

No. You install the plugin, drag the **File upload** field into a form, and paste a shortcode into a page. There is no code and no hosting access involved.

### Is Easy Form Builder free?

Yes, and the file upload field is included in the free version. The Pro package adds extra add-ons, including **Form Security & Spam Protection** for forms that attract automated traffic.

### Where do the uploaded files go?

Into your site's normal WordPress uploads folder, attached to the submission they came with. You read submissions under **Easy Form Builder → Panel**.

### Can I stop people sending me huge files?

Yes. Set **Max File Size** on the upload field. If you leave it empty the limit is 20 MB, and your hosting plan's own limit still applies on top — the smaller number always wins.

### Can I accept only PDFs?

Yes. Open the upload field and set **Acceptable file types** to **Document** for PDFs and office files, or choose **Customize** to list exactly the file types you want.

### What stops someone uploading a virus or a script?

Programs and scripts are refused automatically, whatever your form settings are. The plugin also looks inside each file to check it matches its name, so renaming a script to `photo.jpg` does not get it through.

### Can visitors upload more than one file?

Yes. Each visitor can upload up to 3 files per upload field per hour, which covers the normal "picked the wrong one, let me try again" situation without leaving the field open to abuse.

<!-- FAQPage JSON-LD -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Do I need to know how to code to add a file upload form?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "No. You install the plugin, drag the File upload field into a form, and paste a shortcode into a page. There is no code and no hosting access involved."
      }
    },
    {
      "@type": "Question",
      "name": "Is Easy Form Builder free?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes, and the file upload field is included in the free version. The Pro package adds extra add-ons, including Form Security & Spam Protection for forms that attract automated traffic."
      }
    },
    {
      "@type": "Question",
      "name": "Where do the uploaded files go?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Into your site's normal WordPress uploads folder, attached to the submission they came with. You read submissions under Easy Form Builder → Panel."
      }
    },
    {
      "@type": "Question",
      "name": "Can I stop people sending me huge files?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes. Set Max File Size on the upload field. If you leave it empty the limit is 20 MB, and your hosting plan's own limit still applies on top — the smaller number always wins."
      }
    },
    {
      "@type": "Question",
      "name": "Can I accept only PDFs?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes. Open the upload field and set Acceptable file types to Document for PDFs and office files, or choose Customize to list exactly the file types you want."
      }
    },
    {
      "@type": "Question",
      "name": "What stops someone uploading a virus or a script?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Programs and scripts are refused automatically, whatever your form settings are. The plugin also looks inside each file to check it matches its name, so renaming a script to photo.jpg does not get it through."
      }
    },
    {
      "@type": "Question",
      "name": "Can visitors upload more than one file?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes. Each visitor can upload up to 3 files per upload field per hour, which covers the normal \"picked the wrong one, let me try again\" situation without leaving the field open to abuse."
      }
    }
  ]
}
</script>
