# The five-star invitation

Two weeks after somebody starts using Easy Form Builder, a Free or Free Plus
site is asked - once, on an Easy Form Builder screen and nowhere else - to rate
the plugin. Five stars earns a discount code for the first year of Pro, issued
by the same White Studio service that already rewards bug reports.

| Half | Lives in |
| ---- | -------- |
| the invitation | `includes/class-Emsfb-review-request.php` (+ its CSS/JS) |
| the look | `includes/admin/assets/css/modal-system-efb.css` - see [modal-design-system.md](modal-design-system.md) |
| the coupon | the `ws-efb-feedback` service, reached through `Deactivation_Feedback::send_report_efb()` |

---

## Four rules the client keeps

1. **Pro sites are never asked.** They already paid; there is no reward to offer
   and no reason to interrupt them.
2. **Nobody is asked twice by accident.** "Maybe later" is a real fourteen-day
   snooze, "Do not ask again" is permanent, and both are recorded *before* the
   modal closes - with `keepalive`, so closing the tab still counts as an
   answer. Escape means later, never never.
3. **A rating below five stars never reaches WordPress.org.** It opens the
   support route instead. Pushing an unhappy person toward a public review form
   is how you earn the two-star review this feature exists to avoid.
4. **The discount figure is never written into a sentence.** Every string
   carries `%s` and the number arrives from `discount_label_efb()`.

## When it appears

All of these must hold:

| Condition | Where |
| --------- | ----- |
| `manage_options` | `should_ask_efb()` |
| `emsfb_pro` is 2 (Free) or 3 (Free Plus) | `plan_is_eligible_efb()` |
| 14+ days since first use | `days_in_use_efb()` |
| not `rated`, not `dismissed`, past any snooze | `state_efb()` |
| the screen id contains `emsfb` | `is_plugin_screen_efb()` |
| `emsfb_review_should_ask_efb` has not vetoed it | filter |

The screen check is **case-insensitive on purpose**. The hook suffix keeps the
menu slug's capitals (`toplevel_page_Emsfb`) but `WP_Screen` lower-cases the id
it derives from that same slug (`toplevel_page_emsfb`) - and `admin_footer`, the
hook that prints the modal, is passed no suffix at all. A `strpos()` for
`'Emsfb'` matches the first and silently never the second.

### The install date was never being written

`emsfb_install_date` was read in two places and written in none, so
`installed_days` in every deactivation report was `0`. `seed_install_date_efb()`
now writes it on `admin_init`, and **backdates it from the oldest form's
creation date** where forms exist. Without that, shipping this feature would
have restarted everybody's clock and no existing site would have been asked for
another fortnight.

## The conversation

```
ask ──5 stars──▶ reward ──opens wp.org──▶ claim ──▶ done
 │
 └──1-4 stars──▶ improve ──▶ support
```

`emsfb_review_state` holds `status`, `snooze_until`, `shown`, `rating`,
`rated_at`, `coupon_state` and `coupon_code`.

The claim button stays hidden until the WordPress.org link has actually been
clicked - asking for a code before anyone could have written a review is how you
teach people to click past it.

### About once a month, not once a login

**Printing the invitation is what spends it.** `render_modal_efb()` writes the
30-day snooze immediately, before anybody has answered - because most people
answer by ignoring it, and an invitation that waits for an answer it will never
get is one that reappears on every single page load.

So a site that logs in every morning sees it on one of those mornings, not
thirty. "Maybe later" spends the same thirty days, which is why `SNOOZE_DAYS` is
one constant and not two.

The invitation also **waits its turn**: it arrives on a timer, so if a field
editor or a delete confirmation is already open it re-checks every 1.5s for up
to 30s rather than stacking on top of somebody's work.

## Seeing it without waiting two weeks

Add `?efb_review_preview=1` to any Easy Form Builder screen:

```
/wp-admin/admin.php?page=Emsfb&efb_review_preview=1
```

It opens whatever the plan, the age of the install or the stored answer - so it
can be looked at on a site that would never be asked, including a Pro one. It is
a look, not a rehearsal: **nothing is counted, nothing is snoozed, no answer is
written**, and the claim step answers itself with a sample code instead of
asking the service for a real one. The capability check is the only gate it does
not lift.

## The offer

`discount_label_efb()` is the only place the number lives.

```php
add_filter( 'emsfb_review_discount_percent_efb', fn() => 50 );
```

Values are clamped to 1-100 and formatted with `number_format_i18n()`. Two more
filters exist: `emsfb_review_url_efb` and `emsfb_review_support_url_efb`.

## The coupon

`claim_efb()` posts a report with reason `five_star` through
`Deactivation_Feedback::send_report_efb()` - the same identity, HMAC signature
and one-retry-on-401 pipeline the deactivation survey uses. That method was made
public for this; keeping a second copy of the signing code would let it drift
out of step with the service.

**Every failure path still ends with the person told something true.** No
network, a dead endpoint, or a service that does not know `five_star` yet all
resolve to `coupon_state = 'pending'` and "we will email it to you shortly" -
never an error the person cannot act on. The pledge is recorded locally either
way, and a second claim returns the stored code rather than asking for another.

> The service side needs `five_star` added to `WS_EFB_Feedback_REST::reasons()`
> before codes issue on the spot. Until then every claim resolves to `pending`,
> which is a correct and honest state, not a failure.

## Wording

`strings_efb()` reads three sources, most specific first: phrases pushed by the
White Studio settings payload (`text->review<Key>`), the fa/ar/de translations
bundled in the class, then the English source strings - the same arrangement as
the deactivation survey, and for the same reason.

## Tests

```
C:\xampp\php\php.exe tests/test-review-request.php     # 53 assertions
node tests/test-review-request-browser.js              # 32 assertions
```

The PHP suite covers the gate, the clock, the state machine, install-date
seeding and backdating, and the two promises about wording - that no string
hard-codes the figure and that no translation dropped its `%s`. It also asserts
the stylesheets carry no `[dir]` block and no physical `left`/`right`, with CSS
comments stripped first so the prose explaining those rules cannot satisfy them.

The browser suite proves the modal opens, that three stars leads to support
while five leads to WordPress.org, that the claim button waits for the link,
that the layout and star row hold in both directions, and that the invitation
stands down while another dialog is open.

Both suites restore every option they touch, pass or fail.
