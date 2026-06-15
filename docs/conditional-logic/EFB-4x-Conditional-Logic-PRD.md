# Easy Form Builder 4.x — Smart Conditional Logic Engine PRD

> [Documentation index](README.md) · [Product roadmap](EFB-Conditional-Logic-Product-ROADMAP.md) · [AI roadmap](EFB-AI-Conditional-Logic-ROADMAP.md)

## 1) Product Goal

Add a **Conditional Logic** engine to Easy Form Builder that goes beyond simple **show / hide field** behavior and can also:

- control form behavior
- change steps dynamically
- apply dynamic validation
- handle calculations and pricing
- run conditional email, confirmation, webhook, and payment flows

This direction matters because leading form builders already support important parts of this space in different ways. WPForms emphasizes conditional field display, notifications, and confirmations. Jotform adds update/calculate behavior and control over required/enabled states. Typeform supports logic jumps based on answers and hidden fields. Gravity Forms is particularly strong in pricing and conditional pricing.
Source references for competitive context:
- WPForms Conditional Logic: https://wpforms.com/features/conditional-logic/
- WPForms AND/OR Logic: https://wpforms.com/docs/how-to-use-and-or-conditional-logic/
- WPForms Conditional Logic Docs: https://wpforms.com/docs/how-to-use-conditional-logic-with-wpforms/
- Jotform Smart Forms / Conditional Logic: https://www.jotform.com/help/57-smart-forms-conditional-logic-for-online-forms/
- Jotform Update / Calculate Fields: https://www.jotform.com/ai/features/update-calculate-fields/
- Jotform Insert Text or Calculation with Logic: https://www.jotform.com/help/268-how-to-insert-text-or-calculation-into-a-field-using-conditional-logic/
- Typeform Logic Jumps: https://www.typeform.com/developers/create/logic-jumps/
- Gravity Forms Pricing Fields Guide: https://docs.gravityforms.com/gravity-forms-pricing-fields-guide/
- Gravity Forms Conditional Logic Limitations: https://docs.gravityforms.com/conditional-logic-limitations/

---

## 2) 4.x Release Objective

### Main objective
Deliver a **Visual Logic Builder** that is fast, simple, and powerful enough to cover around **80% of real-world needs** for professional and semi-professional users.

### Commercial objective
- increase the value of Pro / Free Plus
- reduce migration to Gravity Forms, WPForms, Jotform, and similar builders
- unlock monetizable use cases such as:
  - quote forms
  - booking forms
  - multi-step intake forms
  - dynamic pricing forms
  - lead qualification funnels
  - support triage forms

### Technical objective
- implement logic without noticeable performance degradation
- avoid breaking current form behavior
- prepare the architecture for advanced features in 4.1 / 4.2 / 4.3

---

## 3) Suggested Positioning

**Easy Form Builder 4.x Smart Logic**
*Not just show/hide fields — automate the whole form flow.*

Alternative positioning:

**Visual Form Automation for WordPress**

---

## 4) 4.x Scope

### In scope

#### Phase 1 — Core engine
- rule builder
- conditions based on field values
- field-level actions
- step/page logic
- conditional required fields
- conditional email notification
- conditional confirmation / redirect
- conditional webhook
- basic calculations
- preview / test mode

#### Phase 2 — Competitive differentiation within 4.x
- nested groups
- priority system
- rule conflict warnings
- reusable rule groups inside the same form
- URL / query param conditions
- user state conditions
- simple debugger / inspector

### Out of scope for the first 4.x wave
- AI logic builder
- cross-form logic
- full rule version history
- full visual flowchart canvas
- advanced post-submit automation graphs
- logic based on previous database submissions

These are better targets for **4.2+ or 5.0**.

---

## 5) User Personas

### 1. SMB / company website owner
Needs to:
- show some fields only in relevant cases
- route emails to the right team
- keep the form short and professional

### 2. service sales / quotation flow owner
Needs to:
- calculate live pricing
- change fields based on selected service
- vary the submission flow based on order type

### 3. agency / developer
Needs to:
- build complex rules
- debug logic easily
- manage multi-step conditional flows
- run conditional webhooks and integrations

### 4. medical / intake workflow owner
Needs to:
- show follow-up questions only when needed
- change step flow based on patient answers
- display special warnings or messages for sensitive answers

---

## 6) Key Use Cases

### Use Case 1 — Smart contact form
If the user selects **Sales**:
- show budget field
- send notification to sales team

If the user selects **Support**:
- show activation code field
- send notification to support team

### Use Case 2 — Multi-step form
If `country = Canada`:
- show province step
- show tax-related step

If `country != Canada`:
- skip those steps

### Use Case 3 — Pricing flow
If `plan = Pro`:
- subtotal = base + add-ons
- if billing = yearly, apply discount

### Use Case 4 — Conditional webhook
If `lead score > 70`:
- trigger CRM webhook
- otherwise only store locally in WordPress

### Use Case 5 — Dynamic validation
If `Business customer = yes`:
- company name becomes required
- VAT number becomes required

---

## 7) Functional Requirements

# A. Logic Builder

## A1. Rule creation
Each rule should include:

- Name
- Enabled / Disabled state
- Trigger scope
- Conditions
- Actions
- Priority
- Stop further processing

### Example structure
- IF `[conditions]`
- THEN `[one or more actions]`

## A2. Condition grouping
Support:
- ALL (AND)
- ANY (OR)
- Nested condition groups

This is important because WPForms explicitly highlights AND/OR logic, while Gravity Forms documents limitations around nested conditional logic. Strong support for nested logic can therefore become a real Easy Form Builder advantage.

## A3. Supported operators

### General
- is
- is not
- contains
- does not contain
- starts with
- ends with
- is empty
- is not empty

### Numeric
- greater than
- less than
- greater than or equal
- less than or equal
- between
- not between

### Choice-based
- includes option
- excludes option

### Boolean
- true / false
- checked / unchecked

### Date-based
- before
- after
- between dates

---

# B. Trigger Sources

Rules should be able to read from:

- Text field
- Textarea
- Number
- Email
- Phone
- Select
- Multi-select
- Radio
- Checkbox
- Date
- Time
- Hidden field
- Calculation result
- Current step/page
- Query string / URL param
- Logged-in status
- WordPress user role

Typeform explicitly supports hidden fields in logic jumps, so strong hidden/query-driven logic matters for competition.

---

# C. Actions

## C1. Field Actions
- Show field
- Hide field
- Enable field
- Disable field
- Mark as required
- Mark as optional
- Set value
- Copy value from another field
- Clear value
- Change placeholder
- Change help text
- Change label
- Focus field
- Scroll to field

Jotform publicly emphasizes update/calculate fields and logic for required/enabled states, so these should exist in Easy Form Builder 4.x.

## C2. Step / Page Actions
- Show step
- Hide step
- Jump to step
- Skip step
- End form early with custom message

Typeform’s logic jump model shows why robust step logic is necessary.

## C3. Submission Actions
- Allow submit
- Block submit
- Show inline warning
- Show inline success/info message

## C4. Notification Actions
- Send email template A
- Send email template B
- Add CC/BCC conditionally
- Change recipient conditionally
- Change subject dynamically

WPForms officially supports conditional notifications and confirmations, so this is now table-stakes functionality.

## C5. Confirmation Actions
- Show custom thank-you message
- Redirect to URL A
- Redirect to URL B
- Redirect with query parameters

## C6. Integration Actions
- Trigger webhook A
- Trigger webhook B
- Stop webhook
- Modify webhook payload fields conditionally

## C7. Pricing / Calculation Actions
- Set field value by formula
- Add amount
- Subtract amount
- Apply percent discount
- Apply surcharge
- Set total
- Round result

Gravity Forms documents core pricing fields and also sells a Conditional Pricing add-on. Jotform also exposes update/calculate logic. This confirms that pricing logic is a real market need rather than a decorative feature.

---

## 8) Logic Scopes

In the UI, each rule should belong to one of these scopes:

- Field Logic
- Step Logic
- Form Logic
- Notification Logic
- Confirmation Logic
- Webhook Logic
- Pricing Logic

This makes the product feel like a **logic engine**, not just a “conditional fields” feature.

---

## 9) UX Requirements

### 9.1 Entry point
Inside Form Builder, add a separate tab:

**Logic**

With these subsections:
- Fields
- Steps
- Notifications
- Confirmations
- Webhooks
- Pricing

### 9.2 Rule creation flow
Provide an **Add Rule** button.

Each Rule Card should display:
- rule name
- scope badge
- enabled toggle
- conditions summary
- actions summary
- priority

### 9.3 Simple editing experience
The UI should avoid feeling too technical.

Recommended controls:
- dropdown for field selection
- dropdown for operator
- value input
- Add Condition button
- Add Group button
- Add Action button

### 9.4 Human-readable summary
At the top of each rule, show a readable sentence such as:

> If Service = “Web Design” and Budget > 1000, then show Project Timeline and send Sales notification.

This summary is very important for usability.

### 9.5 Preview / Test Mode
Allow the user to:
- enter test values
- see whether a rule matches
- see which actions would run

---

## 10) Debugger / Inspector

This should be one of the **killer features** of 4.x.

### Capabilities
- show which rule matched
- show which rule failed
- explain why it failed
- show evaluation order
- warn about conflicts

### Example output
- Rule 2 matched: Country = Canada
- Rule 4 skipped: Budget was empty
- Rule 7 not applied due to higher priority rule

This feature is especially valuable for agencies, advanced users, and support teams.

---

## 11) Priority & Conflict System

Each rule should support:
- Priority number
- Apply all matching rules
- Apply first matching rule only
- Stop processing after this rule

### Example conflict
- Rule A → Field X required
- Rule B → Field X optional

System warning:

> Conflicting actions detected for Field X.

---

## 12) Calculations — 4.x Basic Model

### Supported formula sources
- Number fields
- Quantity fields
- Option prices
- Hidden values
- Static values

### Basic operators
- `+`
- `-`
- `*`
- `/`
- `()`
- percentage
- round

### Examples
- `Total = Base + Addons`
- `Discount = Total * 0.1`
- `Final = Total - Discount`

Jotform’s update/calculate behavior and Gravity Forms’ pricing field ecosystem show that users expect this type of dynamic calculation.

---

## 13) Suggested Data Model

Rules should ideally be stored as JSON.

### Option A — inside form post meta
Store all logic in one meta key:

`efb_logic_rules`

### Example JSON structure
```json
[
  {
    "id": "rule_001",
    "name": "Show VAT for business users",
    "scope": "field",
    "enabled": true,
    "priority": 10,
    "stop_processing": false,
    "conditions": {
      "type": "group",
      "operator": "AND",
      "items": [
        {
          "source": "field",
          "field_id": "customer_type",
          "compare": "is",
          "value": "business"
        },
        {
          "source": "field",
          "field_id": "country",
          "compare": "is_not_empty"
        }
      ]
    },
    "actions": [
      {
        "type": "show_field",
        "target": "vat_number"
      },
      {
        "type": "set_required",
        "target": "vat_number",
        "value": true
      }
    ]
  }
]
```

### Advantages
- portable
- easy export/import
- extensible
- REST API-friendly

---

## 14) Runtime Engine Requirements

### Front-end engine
- pre-map dependencies between fields and rules
- evaluate only rules related to the changed field
- use debounce for text input
- prevent visual flicker
- support multi-step forms reliably

### Back-end validation engine
Important logic must also be enforced server-side:
- required / optional logic
- submit blocking
- pricing totals
- webhook conditions
- notification conditions

This is essential for security and bypass prevention.

---

## 15) REST / Developer Extensibility

### Proposed hooks / filters
- `efb_logic_before_evaluate_rule`
- `efb_logic_after_evaluate_rule`
- `efb_logic_modify_result`
- `efb_logic_before_actions`
- `efb_logic_after_actions`

### Developer API goals
Allow developers to:
- add custom operators
- add custom sources
- add custom actions

Examples:
- geolocation source
- coupon validation action
- CRM action

---

## 16) Backward Compatibility

- forms without logic must continue to behave exactly as before
- existing forms should not require forced migration
- unsupported logic in lower editions should degrade gracefully

---

## 17) Packaging / Monetization



### Free Plus
- simple field show/hide
- basic AND
- limited rules per form (3 logic)
- no pricing logic
- no webhook logic
- no debugger


### Pro
- AND / OR / NOR / NOT / NAND Logic
- required / optional
- step logic
- notification logic
- more rules
- URL param conditions
- nested groups
- calculations / pricing
- webhook logic
- priority system
- debugger
- reusable logic presets
- export/import rules


---

## 18) Suggested 4.x Release Plan

### 4.X
- field show/hide
- required/optional
- AND/OR / NOR / NOT / NAND groups
- step skip/jump
- conditional notifications
- conditional confirmations
- rule builder UI

### 4.X+1
- basic calculations/pricing
- webhook logic
- hidden/query param conditions
- priority system
- rule summary improvements

### 4.X+2
- debugger/inspector
- nested group polish
- export/import rules
- reusable presets
- performance optimization layer

---

## 19) Acceptance Criteria

### MVP acceptance
- user can build at least 10 rules without coding
- rules execute correctly on the front end
- important validation rules are enforced on the back end
- step logic does not break multi-step forms
- conditional notifications and confirmations work correctly
- no noticeable performance drop on medium-sized forms

### UX acceptance
- a new user can create the first rule in under 2 minutes
- every rule shows a readable summary
- common conflicts trigger warnings
- test mode clearly explains outcomes

---

## 20) Biggest Competitive Angles

### 1. One logic engine for everything
Not just fields, but also:
- steps
- notifications
- confirmations
- webhooks
- pricing

### 2. Nested logic without pain
This can become a major competitive angle, especially because Gravity Forms documents official limitations around nested conditional logic.

### 3. Built-in debugger
Many builders let users define rules, but do not clearly explain why something did not work.

### 4. Smart pricing inside the builder
Gravity Forms is strong in pricing. If Easy Form Builder makes pricing more visual and easier, that becomes a UX advantage.

### 5. Marketing-ready personalization
Support for:
- URL params
- hidden fields
- user-role-based rules
- redirect personalization

---

## 21) Recommended Marketing Headline

**Conditional Logic that does more than hide fields**
Build smarter flows, dynamic pricing, conditional notifications, and personalized form journeys — all visually.

---

## 22) Final Recommendation for 4.x

If only eight things are prioritized for 4.x, these should be the core set:

1. Visual rule builder
2. AND/OR + nested groups
3. Show/hide + required/optional + enable/disable
4. Step/page logic
5. Conditional notifications + confirmations
6. Webhook conditions
7. Basic calculations/pricing
8. Debug preview / inspector

This combination shifts Easy Form Builder from a standard builder with basic conditional logic into a **smart workflow form builder**.

---

## 23) Optional Future Extensions

Good candidates for later releases:

- AI-assisted rule generation
- cross-form logic
- rule versioning and rollback
- reusable global rule packs
- logic templates by industry
- analytics on rule usage and conversion impact
