# Mobile Pro Features Implementation Guide

## Goal

Implement mobile-only field settings as **Pro-only** features, add two new mobile visibility controls, add one global mobile label-hide setting, fix persistent selected state for `btn-group-toggle` controls, and improve the download button placement/UX in both admin and frontend.

This file is meant to let another AI or engineer start implementation immediately with minimal re-discovery.

## Current Code Areas

Main files involved:

- `includes/admin/assets/js/val-efb.js`
- `includes/admin/assets/js/admin-efb.js`
- `includes/functions.php`
- `includes/admin/assets/css/style-efb.css`
- `public/assets/css/style-efb.css`
- any frontend/admin renderer that outputs the download button and its dropdown

## Existing Mobile Settings Already Present

These already exist in the field settings UI and responsive engine:

- `Mobile width`
- `Mobile Description | Align`
- `Mobile Label | Align`
- `Mobile Label Position`
- `Mobile Label size`

Existing responsive engine in `includes/admin/assets/js/admin-efb.js`:

- `efbViewPropMapEfb`
- `efbMobilePropDefaultsEfb`
- `efbGetViewPropEfb`
- `efbSetViewPropEfb`
- `efbApplyFieldViewEfb`
- `efbProjectMobileViewEfb`
- `switchViewEfb`

## Required New Data Keys

### Per-field keys

- `mobile_size`
- `mobile_message_align`
- `mobile_label_align`
- `mobile_label_position`
- `mobile_label_text_size`
- `mobile_hflabel`
- `mobile_hfdescription`

### Form-level key

- `global_mobile_hflabel`

## Behavior Rules

### Pro gating

The following field-level mobile settings must be usable only for Pro users:

- `Mobile width`
- `Mobile Description | Align`
- `Mobile Label | Align`
- `Mobile Label Position`
- `Mobile Label size`
- `Hide label in mobile`
- `Hide description in mobile`

For non-Pro users:

- keep the controls visible
- show a locked/disabled-looking UI
- do not save changes
- show the existing Pro upgrade message already used in the plugin

Do not create a parallel upgrade system. Reuse existing upgrade flows such as:

- `pro_show_efb(...)`
- `show_pro_message_for_elm(...)`
- existing text like `proUnlockMsg` / `youUseProElements`

### Global mobile label hiding

Add one form-level setting:

- `global_mobile_hflabel`

Behavior:

- affects only mobile
- hides labels for all fields on mobile
- should reflect in admin preview and published frontend

Recommended precedence:

1. If `global_mobile_hflabel == 1`, hide all field labels on mobile.
2. Otherwise, only hide labels for fields with `mobile_hflabel == 1`.

### New per-field mobile toggles

Add these new mobile-only field settings:

- `Hide label in mobile` -> `mobile_hflabel`
- `Hide description in mobile` -> `mobile_hfdescription`

These must affect:

- admin mobile preview
- published frontend
- persisted saved state

Desktop behavior must remain unchanged.

## Important Existing Problems To Fix

### `btn-group-toggle` selected state must persist and be visually clear

Problem:

- some toggle groups rely only on temporary class changes
- after reopening field settings, switching views, or returning later, the selected option may not be obvious enough or may not be re-synced correctly

Required result:

- the selected option must always be obvious
- the correct option must still be selected after reopening field settings
- the correct option must still be selected after switching between desktop/mobile
- the source of truth must be `valj_efb`

### Existing typo bug

There is an existing typo in desktop label position:

- `funSetPosElEfb('${idset}','besie')`

This must be fixed to:

- `beside`

Keep all handlers and stored values consistent.

## Implementation Plan

### Phase 1: Create shared Pro-gating helpers for mobile controls

In `includes/admin/assets/js/val-efb.js` and/or `includes/admin/assets/js/admin-efb.js`:

- add a shared helper for rendering locked mobile controls
- add a shared helper for checking access before changing mobile settings

Recommended helper ideas:

- `wrapProMobileControlEfb(html, locked)`
- `efbRequireProForMobileSettingEfb(...)`

Do not scatter plan checks everywhere. Keep mobile Pro gating centralized.

### Phase 2: Gate all existing mobile field settings

In `includes/admin/assets/js/val-efb.js`:

- wrap all mobile field controls with the Pro gate
- preserve visibility for Free users
- prevent actual state changes for Free users

Target controls:

- `mobileWidthEls`
- `MobileElementAlignEls('label')`
- `MobileElementAlignEls('description')`
- `mobileLabelPostionEls`
- `mobileLabelFontSizeEls`
- new mobile visibility toggles

### Phase 3: Add `mobile_hflabel`

In field settings UI:

- add a mobile-only toggle using the same visual pattern as `hideLabelEl`

In state:

- store on each field as `mobile_hflabel`

In admin preview:

- when current view is mobile and `mobile_hflabel == 1`, hide the field label
- when desktop view is restored, revert this mobile-only hide

In frontend:

- hide the label only on mobile

### Phase 4: Add `mobile_hfdescription`

In field settings UI:

- add a mobile-only toggle near `Mobile Description | Align`

In state:

- store on each field as `mobile_hfdescription`

In admin preview:

- when current view is mobile and `mobile_hfdescription == 1`, hide the field description element

In frontend:

- hide the description only on mobile

### Phase 5: Add `global_mobile_hflabel`

Add a form-level setting in the form settings UI, not field settings.

Recommended placement:

- a mobile settings section under form settings

Store on:

- `valj_efb[0].global_mobile_hflabel`

Make it update:

- admin mobile preview
- published frontend

### Phase 6: Extend responsive projection engine

Update `includes/admin/assets/js/admin-efb.js`.

Required:

- keep `valj_efb` as the only source of truth
- extend mobile projection so it also applies label/description visibility
- ensure switching back to desktop removes mobile-only hidden classes

Recommended helpers:

- `efbApplyMobileLabelVisibilityEfb(item, view)`
- `efbApplyMobileDescriptionVisibilityEfb(item, view)`

Then call them from:

- `efbApplyFieldViewEfb(item, view)`
- `efbProjectMobileViewEfb()`

### Phase 7: Fix `btn-group-toggle` persistence and clarity

In all desktop/mobile grouped controls:

- desktop label position
- mobile label position
- desktop label align
- desktop description align
- mobile label align
- mobile description align

Requirements:

- render `active` class from `valj_efb`
- sync `input[type=radio].checked`
- after every update, re-sync the whole group
- on modal reopen, hydrate from saved state

Recommended helper:

- `efbSyncButtonGroupState(groupEl, selectedValue)`

Do not rely on click-time styling alone.

### Phase 8: Improve mobile settings UX in field settings modal

Recommended grouping order:

1. `Mobile width`
2. `Mobile Label Position`
3. `Mobile Label | Align`
4. `Mobile Label size`
5. `Hide label in mobile`
6. `Mobile Description | Align`
7. `Hide description in mobile`

Recommended UX:

- wrap them inside a visible `Mobile Settings` section
- show a `Pro` badge or lock icon for locked controls
- keep controls visible but non-functional for Free users
- avoid removing them completely

### Phase 9: Add/adjust translation keys

In `includes/functions.php`, add text keys as needed:

- `mobileSettings`
- `mobileHideLabel`
- `mobileHideDescription`
- `globalMobileHideLabel`

Reuse existing upgrade/pro texts where possible.

## Frontend Rendering Strategy

Do not solve frontend mobile visibility with admin-only JS.

Recommended frontend approach:

- add field/form CSS classes or data attributes during render
- handle mobile hiding in `public/assets/css/style-efb.css`

Suggested classes:

- `efb-mobile-hide-label`
- `efb-mobile-hide-description`
- `efb-mobile-hide-label-global`

Then inside mobile breakpoint CSS:

- hide labels for matching fields
- hide descriptions for matching fields

For global hide:

- attach a form-level class to the wrapper and hide all labels under it on mobile

## Download Button Placement and UX

Target button:

```html
<button type="button" class="efb-msg-dl-btn" onclick="toggleDlDropdown_EFB(this)" aria-expanded="false" title="Download"><i class="bi bi-download"></i></button>
```

This must be improved in both admin and frontend.

### Required structure

Use a wrapper structure like:

```html
<div class="efb-msg-dl-wrap">
  <button type="button" class="efb-msg-dl-btn" ...></button>
  <div class="efb-msg-dl-dropdown">...</div>
</div>
```

Rules:

- wrapper must be `position: relative`
- dropdown must be anchored to the wrapper
- avoid random margins/absolute positioning from unrelated containers

### Placement requirements

Desktop:

- place it in the top-right of the related card/toolbar in LTR
- place it in the top-left in RTL

Mobile:

- keep it in the action/header row
- do not let it overlap content
- make touch target comfortable

### UX requirements

- sync `aria-expanded`
- clicking outside closes dropdown
- opening one dropdown closes others
- prevent viewport overflow
- if needed, flip dropdown direction based on available space
- visible hover/focus/active states

### Styling requirements

Create or normalize shared classes:

- `.efb-msg-dl-wrap`
- `.efb-msg-dl-btn`
- `.efb-msg-dl-dropdown`
- `.efb-msg-dl-btn.is-open`

Ensure:

- clear spacing
- correct `z-index`
- good RTL/LTR behavior
- responsive dropdown positioning

## Flows That Must Re-sync After Render

Any flow that rebuilds field markup must re-apply mobile projection and state sync:

- `switchViewEfb()`
- `editFormEfb()`
- add field flow
- duplicate field flow
- undo/redo if present
- reopen field settings modal

After re-render, ensure:

- `efbProjectMobileViewEfb()`
- button-group state sync
- mobile label/description visibility sync

## Testing Checklist

### Free plan

- user sees all mobile settings
- user cannot actually enable/change them
- existing Pro message appears
- no forbidden mobile state is saved

### Pro plan

- all mobile settings save correctly
- reopening field settings shows the correct selected state
- switching desktop/mobile preserves correct state
- saving and reloading preserves correct state

### Visibility

- `mobile_hflabel` hides label only on mobile
- `mobile_hfdescription` hides description only on mobile
- `global_mobile_hflabel` hides all labels only on mobile
- desktop remains unchanged

### Button groups

- selected option is visually obvious
- selected option persists after reopening modal
- selected option persists after view switch
- `beside` works correctly everywhere

### Download button

- correct placement in admin desktop
- correct placement in admin narrow/mobile widths
- correct placement in frontend desktop
- correct placement in frontend mobile
- correct behavior in RTL
- dropdown does not overflow viewport
- outside click closes dropdown

## Definition of Done

The work is complete only when:

- all field mobile settings are Pro-only
- Free users see the existing upgrade message instead of changing state
- `Hide label in mobile` works in admin preview and frontend
- `Hide description in mobile` works in admin preview and frontend
- `global_mobile_hflabel` works in admin preview and frontend
- all affected `btn-group-toggle` controls always show the correct selected state
- selected state still appears correctly after reopen, switch, save, and reload
- the download button has a stable, user-friendly position and dropdown behavior in both admin and frontend
