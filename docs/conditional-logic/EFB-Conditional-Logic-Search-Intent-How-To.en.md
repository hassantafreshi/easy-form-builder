# Easy Form Builder Conditional Logic Search-Intent How-To Guide

This file is prepared for content writers and documentation authors. Each heading is written like a real search query a user might type, followed by the visual steps a WordPress admin should follow in Easy Form Builder to build that behavior.

> Note: This guide covers features available in the current Conditional Logic UI. AI features such as prompt-based rule generation, optimization, and advanced simulation should be described as roadmap or future capabilities unless they are implemented later.

---

## 1. How do I show a field based on a user's answer?

Users often want to show a field only after a specific answer, such as showing Company Name or VAT Number when the user selects Business. To build this, the admin first creates both the source field and the field that should appear. Then they open Conditional Logic, stay on the Fields tab, click Add, and create a new rule.

In the IF section, the admin selects the source field, such as Customer Type. Then they choose the is operator and select Business as the value. In the THEN section, they choose Show Field and select the target field, such as Company Name or VAT Number. After saving, they should use Test Mode with the Business value and confirm that the rule is marked as Matched.

## 2. How do I hide a field when the user selects a specific option?

To hide a field, the admin follows the same rule-building flow but chooses Hide Field in the THEN section. A common example is hiding Company Name, Tax ID, or Business Address when the user selects Personal as the customer type.

If multiple fields should be hidden, the admin can add multiple actions inside the same rule and choose a separate target for each action. Hidden fields are ignored during submission, so they should not block the form even if they were required or had stale values from an earlier path.

## 3. How do I make a field required only in certain conditions?

To make a field conditionally required, the admin creates a rule in the Fields tab. In the IF section, they define the condition, such as Request Type is Support. In the THEN section, they choose Set Required and select a field such as Activation Code.

This is useful when a field is necessary only in one path. Examples include requiring an order number for support requests, requiring a company name for business customers, or requiring a passport number for international travel. In Test Mode, both paths should be tested: one where the rule matches and the field becomes required, and one where it does not match.

## 4. How do I make a required field optional in some paths?

If a field is required by default but should not be required in some paths, the admin can use Set Optional. They create a rule in the Fields tab, define the condition in the IF section, then choose Set Optional in the THEN section and select the target field.

This is important for multi-path forms. For example, Company Registration Number may be required for business users but optional when the user selects Individual. If the same field is also hidden in that path, the admin should make sure rule priorities produce the expected final behavior.

## 5. How do I disable a field based on a condition?

To lock a field without hiding it, the admin uses Disable Field. They create a rule in the Fields tab, define the condition in the IF section, then choose Disable Field and select the target field.

This is useful when the user should see a value but not edit it, such as a price, customer code, dataset-filled value, or option that should be locked after a choice is made. If the field should become editable again in another path, the admin should create another rule using Enable Field.

## 6. How do I enable a disabled field again?

To enable a field again, the admin creates a rule with the Enable Field action. For example, if the user selects Manual Entry, an address field that was previously disabled can become editable again.

For enable/disable behavior, conditions should be clear and non-conflicting. If multiple rules affect the same field, the admin should use Priority to control which rule wins.

## 7. How do I hide a step in a multi-step form?

To hide a whole step, the form must already be built as a multi-step form. The admin opens Conditional Logic, creates a rule in the Fields tab, defines the path condition in the IF section, then chooses Hide Step in the THEN section. The target is selected from the step list instead of the field list.

A common example is hiding the Company Information step when Customer Type is Personal. When a step is hidden, the fields inside it are also ignored during validation and submission. The admin should test this with Next and Previous navigation.

## 8. How do I show a step only for certain users?

To show a step conditionally, the admin uses Show Step. This is useful when a step should be hidden by default and appear only for a specific path, such as showing Payment Details only when the user selects a paid plan.

In the IF section, the admin selects the field that determines the path, such as Plan is Pro. Then they choose Show Step in the THEN section and select the target step. If multiple steps depend on each other, each step needs its own rule or action.

## 9. How do I send the user directly to a specific step after an answer?

To move a user directly to another step in a multi-step form, the admin uses Jump to Step. In the IF section, they define the condition, such as Issue Type is Technical. In the THEN section, they choose Jump to Step and select a target step such as Troubleshooting.

This is different from hiding a step because it actively changes the user's current step. After building the rule, the admin should test that the Previous button, progress bar, and destination-step validation work correctly.

## 10. How do I show a conditional help message or warning inside the form?

To show an inline message, the admin uses Show Message. They define the condition in the IF section, such as Budget less than 1000. Then they choose Show Message in the THEN section, select the target field, and write the message text.

The message appears near the target field and works well for contextual guidance. Examples include "The minimum project budget is 1000," "Please enter your activation code for support requests," or "This option is available only for business users."

## 11. How do I automatically fill a field value?

To automatically fill a field, the admin uses Set Value. In the IF section, they define when the value should be set. In the THEN section, they choose Set Value, select the target field, and enter the static value.

For example, if Department is Sales, a hidden Route field can be set to sales. If Plan is Enterprise, a Lead Priority field can be set to high. If Auto-Populate or Dataset is active, the admin can use a dataset column instead of a static value.

## 12. How do I clear a field value when the user changes paths?

To clear an old value, the admin uses Clear Value. This is useful when the user previously entered data in one path but later changes to another path where that data should no longer be submitted.

For example, if the user changes from Business to Personal, VAT Number or Company Name can be cleared. The admin defines the new path in the IF section and adds Clear Value actions for the related fields in the THEN section.

## 13. How do I create an AND condition?

To create AND logic, the admin adds multiple conditions in the IF section and sets the connector between them to AND. Example: Country is Canada AND Customer Type is Business. The rule runs only when all conditions are true.

After building the AND conditions, the admin chooses the action in the THEN section, such as Show Field for Province or Set Required for VAT Number. If any condition is false, the rule should not match.

## 14. How do I create an OR condition?

To create OR logic, the admin adds multiple conditions in the IF section and sets the connector between them to OR. Example: Department is Sales OR Department is Support. The action runs if any one of the conditions is true.

OR is useful when different answers should trigger the same behavior. For example, if Request Type is Sales or Partnership, the Budget field can be shown.

## 15. How do I build complex conditional logic with groups?

For complex logic, the admin uses Add Group. Inside a group, they can combine multiple conditions with AND/OR, then combine that group with other conditions outside it. Example: (Plan is Premium AND Budget greater than 5000) OR Coupon Code is FORCE.

Groups are needed when the decision cannot be represented by a single simple condition. Content writers should recommend naming complex rules clearly and testing multiple scenarios in Test Mode.

## 16. How do I create a numeric condition?

For numeric logic, the form needs a number-like field such as Number or Range. In the IF section, the admin selects that field and chooses operators such as greater than, less than, greater than or equal, or less than or equal. The comparison value is entered as a number.

Example: if Budget is greater than 5000, show Consultation Type or send a notification to a manager. For sensitive numeric rules, zero, empty values, and decimal values should also be tested.

## 17. How do I create a between or numeric range condition?

For range conditions, the admin selects a numeric field in the IF section and chooses between or not between. The UI shows separate Min and Max inputs where the admin enters the range.

Example: if Budget is between 1000 and 5000, show a message for mid-size projects. If Budget is not between 1000 and 5000, a different path can be triggered. Empty values should not be treated as valid numbers.

## 18. How do I check whether a field is empty or filled?

To check whether a field is empty, the admin selects the field in the IF section and chooses is empty or is not empty. No comparison value is needed, so the value input is hidden.

Example: if Referral Code is not empty, show Discount Reason or send a separate email. If Upload File is empty, show a helper message.

## 19. How do I create a condition for checkbox or multiselect fields?

For checkbox and multiselect fields, the admin selects the field and then chooses one of that field's options as the value. The rule matches when the selected option exists in the user's answers.

Example: if Services includes Website Design, show Project Pages. If the user selects multiple services, each rule can match one of the selected options.

## 20. How do I create a condition for a Yes/No field?

For a Yes/No field, the admin selects the field in the IF section and chooses yes or no as the value. Example: if Have Website is yes, show Website URL; if it is no, show Brand Assets.

This type of condition is useful for short forms because the user makes a simple binary choice and the form changes immediately based on that answer.

## 21. How do I create a condition for successful or failed payment?

If the form has a payment field such as Stripe or PayPal, the admin can create payment conditions. In the IF section, they select the payment field and choose operators such as is paid or is not paid. These operators do not need a value.

Example: if Payment is paid, show a payment-success confirmation or send a webhook to the order system. If Payment is not paid, show a helper message or trigger a different path.

## 22. How do I create a condition based on payment amount?

For payment amount logic, the admin selects the payment field and chooses amount equals, amount greater than, or amount less than. Then they enter the amount to compare against.

Example: if amount is greater than 1000, send a notification to the sales manager. This is useful for order forms, paid registrations, and professional service request forms.

## 23. How do I send emails to the right team based on the user's answer?

For conditional email routing, the admin opens the Notifications tab, clicks Add, and builds the IF condition, such as Department is Sales. Then they enter the recipient email, subject, and template.

For complete routing, the admin can create multiple notification rules: one for Sales, one for Support, and one for Billing. Each rule sends only when its own condition matches.

## 24. How do I send a separate email for high-value leads?

In the Notifications tab, the admin creates a rule where the IF condition is something like Budget greater than 5000 or Plan is Enterprise. Then they set the recipient to a manager or premium sales team and customize the subject.

This prevents all submissions from being handled the same way. Higher-value users or sensitive requests can be routed to a separate team, special email, or faster follow-up workflow.

## 25. How do I show a different thank-you message after submission?

For conditional thank-you messages, the admin opens the Confirmation tab. In the IF section, they define the condition, such as Request Type is Support. Then they set the action to message and write the custom confirmation message.

In the same section, they can customize the done title, icon, tracking-code label, and colors. Example: support requests can see "Our support team will reply soon," while sales requests can see "A sales consultant will contact you."

## 26. How do I redirect users to a specific page after form submission?

For conditional redirects, the admin opens the Confirmation tab, creates a new rule, defines the IF condition, then sets the action to redirect. They enter the destination URL in the URL field.

Example: if Plan is Pro, redirect the user to a booking page after submission. If Plan is Free, show the normal thank-you message. The URL should be complete and valid.

## 27. How do I send a webhook only for certain answers?

For conditional webhooks, the admin opens the Webhook tab and creates a new rule. In the IF section, they define the condition, such as Request Type is API Integration. Then they enter the webhook ID, URL, and method.

If the method is POST, the full payload is sent to the URL. If GET is selected, key data is added to the URL query. This is useful for CRM systems, automations, helpdesks, and order systems.

## 28. How do I create multiple webhooks for different paths?

The admin can create multiple rules in the Webhook tab. Each rule has its own condition, URL, and webhook ID. Example: sales leads go to the CRM, support requests go to the helpdesk, and successful payments go to the order system.

For readability, each rule should have a clear name, such as Send Sales Lead to CRM or Send Support Ticket to Helpdesk. The admin should test that only the correct webhook fires for each path.

## 29. How do I test conditional logic before publishing the form?

The admin opens Conditional Logic and clicks Test Mode. Then they enter sample values for the form fields and click Run Test. Each rule returns a status such as Matched, Not matched, or Skipped.

For important forms, one test scenario is not enough. The admin should test the main paths separately: personal user, business user, sales request, support request, low budget, high budget, successful payment, and failed payment.

## 30. How do I control conflicts between multiple rules on the same field?

If multiple rules affect the same field, the admin should set Priority. Lower numbers run first. If a later rule matches the same target, it can override the earlier result.

If the admin wants a matched rule to prevent later rules from changing the same target, they should enable Stop after this rule matches. This freezes only the targets affected by that rule, while unrelated fields and steps can still be controlled by other rules.

## 31. How do I create different paths for personal and business users?

The admin first creates a Customer Type field with options such as Personal and Business. Shared fields remain normal, while business-specific fields such as Company Name, VAT Number, or Business Address are shown conditionally.

In Conditional Logic, the admin creates Show Field and Set Required actions for the Business option. For the Personal option, they can use Hide Field or Set Optional. If the form is multi-step, the Company Information step can also be shown only for Business users.

## 32. How do I make a support form change based on issue type?

The admin creates an Issue Type field with options such as Technical, Billing, and General. Then they creates separate rules for each path. If Technical is selected, Activation Code and Screenshot fields can be shown; if Billing is selected, Order Number can become required.

In the Notifications tab, each issue type can also be sent to a different email address. Technical can go to the technical team, Billing to finance, and General to support. This is one of the strongest use cases for reducing manual support routing.

## 33. How do I branch a quote request form based on budget?

The admin creates a numeric Budget field. Then they define numeric rules: if Budget is below the minimum, show a message; if Budget is between two values, show fields for mid-size projects; if Budget is above a certain amount, send a notification to the premium sales team.

For this scenario, greater than, less than, between, and not between are the key operators. A content writer can turn this into a complete example with three paths: low budget, mid budget, and high budget.

## 34. How do I change an event registration form based on ticket type?

The admin creates a Ticket Type field with options such as Free, Standard, and VIP. If VIP is selected, guest information or special preference fields can be shown. If Free is selected, the payment step can be hidden or a specific message can be displayed.

If the form includes payment, successful-payment logic can also be added. After payment succeeds, a specific confirmation or order webhook can run.

## 35. How do I customize a job application form based on job position?

The admin creates a Job Position field and shows related fields for each role. For Designer, Portfolio URL can become required; for Developer, GitHub or Technical Skills can be shown.

In the Notifications tab, each job position can be routed to the email address of the relevant hiring team. This lets one application form support multiple hiring paths without creating separate forms.

## 36. How do I build a medical or intake form with dependent questions?

The admin creates the base questions first, then shows follow-up questions for sensitive answers. Example: if the user says they take a specific medication, Medication Name and Dosage can become required.

For intake forms, Test Mode is especially important because hidden and required behavior matters. Each sensitive path should be tested separately to make sure required questions appear in the correct path.

## 37. How do I use a dataset value to fill a field automatically?

If Auto-Populate or Dataset is active, the admin can use Set Value and choose a dataset column instead of a static value. Before doing this, the dataset and mappings should already be prepared in the Auto-Populate settings.

In Conditional Logic, the admin creates the rule, chooses Set Value in the THEN section, selects the target field, and chooses the dataset column as the source value. This is useful for automatically filling name, city, customer code, or internal data.

## 38. How do I make a shorter form for simple paths?

The admin builds the complete form, then hides unnecessary fields or steps for simple paths using Hide Field or Hide Step. For example, if the user has a simple request, company, payment, or advanced-details steps can be hidden.

This lets one form support both simple and advanced paths. The important point is that hidden fields should not remain required, and the simple path should be fully tested in Test Mode.

## 39. How do I manage multiple request types with one form?

The admin creates a Request Type field at the beginning of the form, with options such as Sales, Support, Partnership, and Billing. Then they defines the fields, steps, messages, notifications, and webhooks for each request type.

This is one of the best examples of Conditional Logic because one central form can replace several separate forms, while still changing its behavior based on the user's answer.

## 40. How do I know which Conditional Logic articles to write?

From a search-intent perspective, articles should start with practical phrases: "how to show fields conditionally," "how to send emails based on form answers," "how to create conditional multi-step forms," "how to show different thank-you messages," "how to send conditional webhooks," and "how to test conditional logic before publishing."

For each article, the recommended structure is: real-world scenario, required fields, UI path for creating the rule, IF settings, THEN settings, Test Mode verification, common mistakes, and a ready-to-use example for real forms.
