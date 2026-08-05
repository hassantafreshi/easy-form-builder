---
title: "Survey Form Results Chart in Easy Form Builder: The Complete Guide"
slug: "easy-form-builder-survey-results-chart"
meta_description: "How to build a WordPress survey form in Easy Form Builder and show or hide a results chart after submission, field by field. A code-verified guide."
focus_keyphrase: "WordPress survey form results chart"
secondary_keyphrases:
  - "WordPress survey form plugin"
  - "show survey results after submission"
  - "WordPress poll plugin with chart"
  - "NPS survey WordPress"
  - "hide survey results WordPress form"
  - "WordPress survey form builder"
search_intent: "Informational and setup guide"
audience: "WordPress site owners and administrators building survey, poll, or feedback forms with Easy Form Builder"
product_version: "Easy Form Builder 4.1.3 and later"
last_reviewed: "2026-08-05"
---

# Survey Form Results Chart in Easy Form Builder: The Complete Guide

**Keywords:** WordPress survey form results chart, WordPress survey form plugin, show survey results after submission, WordPress poll plugin with chart, NPS survey WordPress, hide survey results WordPress form, WordPress survey form builder, survey form with bar chart WordPress.

**Breadcrumb:** Easy Form Builder Documentation › Survey Forms › Complete Guide · Languages: [فارسی](EFB-Survey-Form-Complete-Guide.fa.md) | English | [العربية](EFB-Survey-Form-Complete-Guide.ar.md) | [Deutsch](EFB-Survey-Form-Complete-Guide.de.md)

> **Scope of this document:** This guide was written by reading the Survey form type's actual PHP and JavaScript in Easy Form Builder — the Form Settings panel, the public submit handler, and the chart-rendering script — plus the plugin's own shipped translation files. Nothing here is guessed. If a label looks slightly different in your installation, that's most likely due to your WordPress admin language or an updated plugin version.

## Quick answer (for search engines and AI assistants)

- Easy Form Builder can show visitors a live, aggregated results chart right after they submit a **Survey**-type form — this is a built-in feature, not a separate add-on, and it works on the free version.
- The control lives in the form builder, under **Form Settings → Advanced → Survey Results Display**, and it only appears once the form's **Form type** is set to **Survey**.
- There are exactly three choices: **Do not show results** (the default), **Show results with bar chart**, and **Show results with pie chart**.
- Every field also has its own **"Show this field in public survey results"** toggle, so you decide exactly which questions are shown to visitors and which stay private.
- The chart is aggregated and anonymous: it shows counts and averages per question, never who answered what.
- The results panel is built for choice- and rating-type questions (Multiple Choice, Checkboxes, Dropdown, Multiple Select, Yes/No, Switch, Star Rating, 5 Point Scale, Range, Net Promoter Score, Date Picker). Number, Longer Text, and NPS Table Matrix questions should be left out of public results — see [Which field types work best](#which-field-types-work-best-in-the-results-chart).
- Because the chart is recalculated live from every stored response, the visitor's own just-submitted answer is already included in the chart they see, and switching the chart type later applies to everyone from that point on.

## Table of contents

- [What is the Survey Results Display setting?](#what-is-the-survey-results-display-setting)
- [How do I create a Survey form?](#how-do-i-create-a-survey-form)
- [Where is the Survey Results Display setting, step by step?](#where-is-the-survey-results-display-setting-step-by-step)
- [The three Survey Results Display options](#the-three-survey-results-display-options)
- [Choosing which questions appear in the results](#choosing-which-questions-appear-in-the-results)
- [What visitors see after they submit](#what-visitors-see-after-they-submit)
- [How the results are calculated (and what stays private)](#how-the-results-are-calculated-and-what-stays-private)
- [Which field types work best in the results chart](#which-field-types-work-best-in-the-results-chart)
- [Common errors and how to fix them](#common-errors-and-how-to-fix-them)
- [Checklist](#checklist)
- [Frequently asked questions](#frequently-asked-questions)

## What is the Survey Results Display setting?

**Survey Results Display** is a form-level setting available on any form whose **Form type** is set to **Survey**. It controls whether people who fill out your survey get to see an aggregate results chart — built from everyone's answers so far — immediately after they submit, right below the confirmation message, on the same page. No add-on, no separate results page, and no extra plan tier is required; it's part of the plugin's standard Survey form type.

Next to the chart type itself, each individual field carries its own **"Show this field in public survey results"** toggle. That's the second half of the feature: the chart type decides *how* results are drawn (or whether they're drawn at all), and the per-field toggle decides *which questions* are included.

## How do I create a Survey form?

There are two ways to end up with a Survey-type form:

1. **Start from a survey template.** When you create a new form, the template gallery includes templates tagged for surveys:
   - **Survey** — "Create survey, poll, or questionnaire forms."
   - **Store Experience Survey** — "Collect customer feedback about in-store experience."
   - **Voter Behavior Survey** — "Survey template for voter behavior research."

   Picking any of these starts you with a form whose **Form type** is already set to **Survey**.

2. **Start from a blank form and switch its type.** Build the form as usual, then open **Form Settings** and set **Form type** to **Survey** (see the next section). This works on any new or existing form, not only ones started from a survey template.

Either way, once **Form type** is **Survey**, the **Survey Results Display** setting becomes available.

## Where is the Survey Results Display setting, step by step?

1. Open the form in the **Form Builder**.
2. Click the **Form Settings** panel (the form-level gear/settings icon, not an individual field's settings).
3. Scroll down to the **Advanced** section — it's expanded by default, so you shouldn't need to click anything to open it.
4. Find the **Form type** dropdown and select **Survey**. As soon as you do, a new field appears right below it: **Survey Results Display**.
5. Open the **Survey Results Display** dropdown and pick one of the three options (covered in the next section).
6. Directly under the dropdown, a small help line confirms what you just turned on: *"After submission, visitors can see aggregate survey results."*
7. Save the form.

> If you don't see **Survey Results Display** at all, double-check step 4 — the field is hidden until **Form type** is actually set to **Survey**.

## The three Survey Results Display options

| Option (on screen) | What it does |
|---|---|
| **Do not show results** | The default for every new Survey form. No results panel is shown to visitors after they submit — just the normal confirmation message. |
| **Show results with bar chart** | After submitting, visitors see a **Survey Results** panel with a bar chart for each question you've marked public. |
| **Show results with pie chart** | Same results panel, but eligible questions are drawn as pie charts instead of bar charts. |

Whichever option you pick applies uniformly to every question you've made public on that form — you can't mix bar charts for some questions and pie charts for others on the same form.

## Choosing which questions appear in the results

Open any field's own settings and look for **"Show this field in public survey results."** This toggle exists on every field type that can meaningfully be counted or averaged (choice fields, rating and scale fields, Date Picker, Number, and text fields all show the toggle; layout elements like Step don't).

This is where the behavior gets genuinely useful to understand correctly:

- **On a form where you haven't touched this toggle on any field yet**, the plugin auto-includes every question it knows how to summarize (all choice, rating/scale, Net Promoter Score, matrix, number, text, and date fields) as soon as you pick bar or pie chart. You don't have to turn anything on manually to get a first working results panel.
- **The moment you turn the toggle on for even one field**, the form switches from "include everything automatically" to "include only what's explicitly turned on." Every other field's results now stay hidden unless you also switch its toggle on.

In practice: if you want full control from the start, turn the toggle on for each question you want public, one by one — as soon as you touch the first one, the rest go quiet until you opt them in too. If you're happy with "show everything," you can simply leave every toggle untouched.

## What visitors see after they submit

When **Survey Results Display** is set to bar or pie chart and at least one field's results are available:

1. The visitor fills out and submits the survey as normal.
2. Their answer is saved first — so it's already counted in the totals by the time the next step runs.
3. The confirmation message appears (the default is *"The survey has been successfully completed,"* or your own custom thank-you message if you've set one).
4. Directly beneath it, on the same screen — not a separate page or URL — a **Survey Results** panel appears, with a **Responses** count and one chart card per public question.

If **Survey Results Display** is set to **Do not show results**, or if no field's results end up available yet (for example, if every public field toggle is off, or this is the very first submission and none of the involved fields have counted data), visitors simply see the normal confirmation message with no results panel — there's no error state or empty box.

## How the results are calculated (and what stays private)

The results panel is aggregated on the server from every stored response to that specific form, every time someone submits — it isn't a snapshot frozen at some earlier point. Two practical consequences:

- **Changing the chart type later is retroactive.** If you switch from bar to pie chart (or from "Do not show results" to a chart), the very next visitor who submits sees a chart built from *all* of that form's responses so far, not just new ones going forward.
- **Nothing identifies who answered what.** The panel only ever receives counts and averages per question — for example, how many people picked each option in a Multiple Choice question, or the average of a Rating field. Individual submissions, names, or emails are never part of the data sent to the browser for this feature.

One number worth understanding correctly: the **Responses** count shown above the chart is the sum of answers across *every question currently shown in the panel* — not the number of people who submitted the form. If you make two questions public and everyone answers both, that count will read roughly double your actual number of submissions. For the true number of submissions, check that form's Messages list in Easy Form Builder instead.

## Which field types work best in the results chart

Internally, each field type is grouped into a category before it can appear in the results panel:

| Field types | Category | Behaves in the results panel |
|---|---|---|
| Radio, Check Box, Select, Multiple Select, Yes/No, Switch | Choice | Renders cleanly as a bar or pie chart. |
| Rating, 5 Point Scale, Range | Scale | Renders cleanly as a bar chart (Range is grouped into ranges automatically). |
| Net Promoter Score | NPS | Renders, using the same bar/pie chart type you picked for the form. |
| Date Picker | Date | Renders as a bar chart, grouped by month. |
| Number | Numeric | **Not currently drawn by the results panel.** Turning its toggle on can stop later questions in the panel from displaying at all. |
| Text, Longer Text | Text | **Not currently drawn by the results panel.** Same risk as Number. |
| NPS Table Matrix | Matrix | **Not currently drawn by the results panel.** Same risk as Number. |

**Practical rule:** turn "Show this field in public survey results" on for your choice-based and rating/scale-based questions, and for Date Picker fields. Leave it off for Number, Text, Longer Text, and NPS Table Matrix questions — the results panel isn't built to chart these yet, and including them can prevent the questions listed after them from rendering.

## Common errors and how to fix them

| Symptom | Likely cause | Fix |
|---|---|---|
| "Survey Results Display" doesn't appear in Form Settings | **Form type** isn't set to **Survey** | Open Form Settings → Advanced and set Form type to Survey |
| No results panel shows to visitors, even though a chart type is selected | No question's results are available yet, or the form has no submissions yet | Submit a test response, and confirm at least one relevant field is meant to be public |
| The results panel used to show several questions, now shows only one | You turned the per-field toggle on for one field, which switches the whole form from "show everything automatically" to "show only what's explicitly on" | Turn "Show this field in public survey results" on for every question you want included |
| The results panel stops partway through, or one question's chart never appears | A Number, Text/Longer Text, or NPS Table Matrix field has "Show this field in public survey results" turned on | Turn that field's toggle off; keep it on only for choice-, rating-, scale-, and date-type questions |
| The "Responses" count looks too high | It sums answers across every public question, not the number of submissions | Check that form's Messages list for the real submission count |
| No chart appears no matter what | Survey Results Display is still set to "Do not show results" | Change it to bar or pie chart and save the form |

## Checklist

- [ ] Form type is set to **Survey** in Form Settings → Advanced.
- [ ] Survey Results Display is set to **bar chart** or **pie chart**, not "Do not show results."
- [ ] Each question you want public has "Show this field in public survey results" turned on (or you've deliberately left every toggle off to let the plugin include everything automatically).
- [ ] Number, Longer Text, Text, and NPS Table Matrix fields are left off, or placed where they won't block other questions.
- [ ] The form has been saved and tested with a real front-end submission.
- [ ] The **Survey Results** panel and its **Responses** count appear correctly beneath the confirmation message after a test submission.

## Frequently asked questions

### Is the survey results chart a Pro-only feature?

No. It's part of the Survey form type in the standard, free version of Easy Form Builder — there's no add-on to install and no license check involved.

### Where exactly do I turn the chart on or off?

In the Form Builder, open **Form Settings**, expand **Advanced** (it's open by default), set **Form type** to **Survey**, then use the **Survey Results Display** dropdown that appears.

### What are the three chart options called?

**Do not show results** (default), **Show results with bar chart**, and **Show results with pie chart**.

### Does my own answer count toward the chart I see right after submitting?

Yes. Your response is saved before the results are calculated, so it's already included in the chart you see on the confirmation screen.

### How do I control which questions appear in the chart?

Use each field's own **"Show this field in public survey results"** toggle. Leaving every toggle untouched shows every summarizable question automatically; turning on even one toggle switches the whole form to "only show what I've explicitly turned on."

### Which field types should I avoid making public?

Number, Text, Longer Text, and NPS Table Matrix fields. The results panel isn't built to chart them yet, and turning them on can stop later questions from displaying.

### Does the results panel show who answered what?

No. It only ever shows aggregated counts and averages per question — never individual answers, names, or emails.

### What happens if I set the chart type but nobody has answered yet?

Visitors just see the normal confirmation message. There's no error and no empty chart box — the panel only appears once there's data to show.

### Can I change the chart type after people have already responded?

Yes, and it applies retroactively — since results are calculated live from every stored response each time someone submits, the next visitor to submit sees a chart built from all responses so far, not just new ones.

### Is the results chart shown on a separate page?

No. It appears inline, directly beneath the confirmation message, on the same screen the visitor just submitted the form on.

<!-- FAQPage JSON-LD, matching the FAQ section above -->
```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Is the survey results chart a Pro-only feature?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "No. It's part of the Survey form type in the standard, free version of Easy Form Builder — there's no add-on to install and no license check involved."
      }
    },
    {
      "@type": "Question",
      "name": "Where exactly do I turn the chart on or off?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "In the Form Builder, open Form Settings, expand Advanced (it's open by default), set Form type to Survey, then use the Survey Results Display dropdown that appears."
      }
    },
    {
      "@type": "Question",
      "name": "What are the three chart options called?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Do not show results (default), Show results with bar chart, and Show results with pie chart."
      }
    },
    {
      "@type": "Question",
      "name": "Does my own answer count toward the chart I see right after submitting?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes. Your response is saved before the results are calculated, so it's already included in the chart you see on the confirmation screen."
      }
    },
    {
      "@type": "Question",
      "name": "How do I control which questions appear in the chart?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Use each field's own \"Show this field in public survey results\" toggle. Leaving every toggle untouched shows every summarizable question automatically; turning on even one toggle switches the whole form to only show what you've explicitly turned on."
      }
    },
    {
      "@type": "Question",
      "name": "Which field types should I avoid making public?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Number, Text, Longer Text, and NPS Table Matrix fields. The results panel isn't built to chart them yet, and turning them on can stop later questions from displaying."
      }
    },
    {
      "@type": "Question",
      "name": "Does the results panel show who answered what?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "No. It only ever shows aggregated counts and averages per question, never individual answers, names, or emails."
      }
    },
    {
      "@type": "Question",
      "name": "What happens if I set the chart type but nobody has answered yet?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Visitors just see the normal confirmation message. There is no error and no empty chart box; the panel only appears once there is data to show."
      }
    },
    {
      "@type": "Question",
      "name": "Can I change the chart type after people have already responded?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes, and it applies retroactively, since results are calculated live from every stored response each time someone submits."
      }
    },
    {
      "@type": "Question",
      "name": "Is the results chart shown on a separate page?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "No. It appears inline, directly beneath the confirmation message, on the same screen the visitor just submitted the form on."
      }
    }
  ]
}
</script>
```

## Editorial SEO notes

- **Primary search intent:** Learn how to show or hide a results chart on a WordPress survey form built with Easy Form Builder, and which questions to include.
- **Recommended title tag:** Survey Form Results Chart in Easy Form Builder (Show or Hide After Submission)
- **Recommended URL:** `/easy-form-builder-survey-results-chart/`
- **Recommended excerpt:** Show visitors a live bar or pie chart of survey results right after they submit — a complete, code-verified guide to Easy Form Builder's Survey Results Display setting.
- **Suggested internal links:** Easy Form Builder installation guide, Conditional Logic documentation, Response Box guide, general form-builder getting-started guide.
- **Suggested image alt text:** Survey Results Display setting in the Easy Form Builder WordPress admin panel.
