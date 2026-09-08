# The rating dialog and the review reward

Two weeks after somebody starts using Easy Form Builder, a Free or Free Plus
site is asked — about once a month at most, on an Easy Form Builder screen and
nowhere else — to rate the plugin.

There are **two ways out of the question**, and they are deliberately different
conversations:

```
ask ──4-5 stars──▶ praise ──▶ claim ──▶ checking ──▶ result
 │
 └──1-3 stars──▶ feedback ──▶ sent
```

| Path | What happens |
| ---- | ------------ |
| 4–5 stars | Sent to WordPress.org. Comes back with a username, White Studio verifies the review is five stars, and emails a **64%** discount code for the first year of Pro. |
| 1–3 stars | **Never** sent to WordPress.org. The complaint goes privately to the team through the same reports pipeline the deactivation survey uses. |

---

## Where each piece lives

| Piece | Codebase | File |
| ----- | -------- | ---- |
| the dialog | `easy-form-builder` | `includes/class-Emsfb-review-request.php` + its CSS/JS |
| the review list | `ws-widgets` (WS) | `includes/class-ws-widgets-wordpress-org.php` — already cached weekly |
| verify, store, mint, mail | `payEfb` (WS) | `includes/services/class-review-reward-service.php` |
| the door | `payEfb` (WS) | `includes/controllers/class-review-rest-controller.php` → `payefb/v1/review-reward` |
| the low-star complaint | `ws-efb-feedback` | reason `rating_feedback` |

## Five rules the client keeps

1. **Pro sites are never asked.** They already paid.
2. **Nobody is asked twice by accident.** Printing the dialog spends it for a
   month; "Do not ask again" is permanent; Escape means later, never never.
3. **An unhappy rating never reaches WordPress.org.** Pushing somebody who just
   said the plugin is hard to use toward a public review form is how you earn
   the two-star review this feature exists to avoid.
4. **The discount figure is never written into a sentence.** Every string
   carries `%s`; the number comes from `discount_label_efb()`.
5. **The plugin never decides whether a review exists.** It forwards a username
   and repeats the answer. A site that could grant itself a coupon by editing an
   option would be a coupon printer.

## The seven answers

The dialog can draw exactly seven outcomes, and `outcomes_efb()` is the one
table that says what each looks like and what the person can do next — a new
outcome cannot be added without deciding both.

| Outcome | Tone | Next |
| ------- | ---- | ---- |
| `granted` | green | Close |
| `pending` | amber | Close — the cron mails it when the review appears |
| `notFound` | amber | Edit details · Try again |
| `lowStars` | amber | Send feedback · Try again |
| `used` | neutral | Contact support · Close |
| `badEmail` | red | Edit details · Try again |
| `server` | red | Later · Try again |

## Seeing it without waiting two weeks

```
/wp-admin/admin.php?page=Emsfb&efb_review_preview=1
```

Opens whatever the plan, the age of the install or the stored answer — so it
works on a Pro site too. **Nothing is counted, snoozed or recorded**, and a
claim there cycles the seven outcomes locally instead of calling White Studio.
The cursor lives in `sessionStorage`, because several outcomes offer no way back
to the claim step and seeing all seven means reloading.

## On the server

`ReviewRewardService::claim()` reads the review list ws-widgets already caches —
it never calls WordPress.org itself, so a slow wordpress.org cannot hang a
visitor's claim. Consequences worth knowing:

- **A review posted minutes ago is not in the cache yet.** A first claim for an
  unknown username is parked as `pending`, not refused; a repeat claim for a
  username still absent is told `notFound`, so somebody who mistyped their name
  is not left waiting for an email that will never come.
- `ws_widgets_reviews_refreshed` fires when the cache actually changes, and
  `grant_pending()` mints and mails the parked claims that have since become
  grantable.

**One code per username, forever** — enforced by the `UNIQUE KEY username`, not
by a SELECT that could race two simultaneous claims. The row is written *before*
the mail goes out, so a bounced address can be retried without minting a second
coupon.

The code is a Stripe **coupon plus a promotion code** (checkout redeems
promotion codes, not coupons): `percent_off`, `duration: once` — the first
invoice, which on an annual plan is the first year — `max_redemptions: 1`, and a
7-day `redeem_by`.

The REST answer carries **only the outcome, never the code**. The mailbox is
what proves the person owns the address they gave.

## The email

`EmailService::send_review_reward_email()`, previewable like every other:

```php
(new payEfb\Services\EmailService())->render_email_preview('review_reward', 'fa');
```

Adding this uncovered that **payEfb had no Persian at all** —
`Email_Localization::normalize_language()` returned only `en`/`de`/`ar`, so every
Persian recipient silently got the English template. `fa` is now a real language
there, and the direct `$texts[$lang]` lookups that assumed the old three now
fall back to English instead of warning.

Digits are localized with a local helper rather than `number_format_i18n()`,
which follows the *site's* locale — the sender's, not the reader's.

## The end-to-end test

Every other test stubs something. This one stubs nothing:

```
node tests/test-review-reward-e2e.js        # 62 assertions
```

- a real Free site installed a month ago, **no preview flag**, so the dialog has
  to decide to appear on its own;
- a real HTTP request across two separate WordPress installs;
- a real lookup against the WordPress.org reviews ws-widgets has cached, using a
  real reviewer's username picked from that list at run time;
- a **real Stripe coupon and promotion code**, minted against the test keys and
  then verified by asking Stripe for them back;
- a real email, composed and handed to `wp_mail()`;
- the low-rating path too: a real `rating_feedback` report landing in the
  feedback service, with the rating, the topics and the sentence.

The only thing intercepted is the mail transport, through a temporary mu-plugin
on the ws install, so the message can be read instead of delivered.

Everything it creates is removed afterwards — the claim row, the Stripe objects,
both temporary mu-plugins, the probe rows, and every option touched. The suite
asserts that itself, and a manual check of the database and the Stripe account
confirmed nothing survives a run.

Two support scripts sit beside it because the customer install and the ws
install are different WordPress sites and cannot be loaded into one process:
`tests/e2e-review-reward-env.php` (the customer side) and
`tests/e2e-review-reward-inspect.php` (the ws side).

## Tests

```
C:\xampp\php\php.exe tests/test-review-request.php                    # 58
node tests/test-review-request-browser.js                             # 64
node tests/test-review-reward-e2e.js                                  # 62
C:\xampp\php\php.exe ../../../ws/wp-content/plugins/payEfb/tests/test-review-reward.php   # 67
```

> These suites mutate shared site state — `WPLANG`, plugin activation, plugin
> options — and restore it in a `finally`. Run them **one at a time**. Running
> several back to back has been seen to flake once, in a way that did not
> reproduce; each passes reliably on its own.

The browser suite walks all seven steps and all seven outcomes in LTR, RTL and
at 390px, and asserts that nothing escapes the shell in any of them. The server
suite stubs Stripe and mail through a subclass — minting real promotion codes
from a test run would leave live coupons behind — and covers the one-code-per-
person rule, username normalization, and a review that appears only later.

All three restore everything they touch.
