# Owner Profile Plugin `PROJECT_ROADMAP.md` (The Future 🗺️)

## Project Vision

Create a standalone Piwigo plugin that owns model/profile data independently from CPT.

The new plugin becomes the canonical source for:

- owner public profile fields
- contact number and contact-channel flags
- availability schedule
- public profile payload for album pages
- UCP "My Profile" editor
- migration from the former CPT-owned profile table

The plugin should not own album privacy, album sharing, upload restrictions, SMS transport, or weekly liveness checks.

---

## Why This Plugin Exists

The profile feature started inside CPT because it was originally tied to owner album pages. That was correct for the MVP.

The feature has now grown into shared domain data:

```text
CPT
= needs public profile display on album pages

Two Factor SMS
= needs contact_number as editable candidate phone

PLG
= relies on verified phone through Two Factor, not raw profile data

Bootstrap Darkroom
= needs final rendered profile placement

Future search/filtering
= may need normalized public profile data
```

So profile data should be extracted from CPT and owned by a dedicated profile plugin.

---

## Phase 1: Standalone Owner Profile MVP

### Goal

Move CPT-owned profile schema, validation, editor payload, public payload, and rendering into a standalone plugin while preserving current behavior.

### Features

- Own a new profile table, for example `piwigo_owner_profile`.
- Provide UCP "My Profile" section.
- Preserve current field schema: basic fields, body/service fields, contact fields, and weekly availability fields.
- Provide public profile rows, contact links, and availability rows.
- Depend on CPT for effective owner/root album resolution.
- Render a public profile block on the owner root album page.
- Expose helper functions for CPT, 2FA, PLG, and theme integration.

### Non-goals

- Album privacy and sharing.
- Upload target restrictions.
- SMS verification.
- Weekly liveness expiry.
- Dating/social features.
- Matching, messaging, payments, geolocation search.

---

## Phase 2: Compatibility and Migration

### Goal

Extract without breaking the existing running portal.

### Features

- Copy data from `piwigo_cpt_owner_profile` to the new table.
- Do not delete the old CPT table during first rollout.
- Provide compatibility wrappers for old CPT profile helper names.
- Let 2FA prefer the new plugin helper but fallback to old CPT table.
- Let CPT skip its old profile block when the new plugin is active.
- Preserve public display and UCP behavior during transition.

---

## Phase 3: CPT Profile Code Removal

### Goal

After successful migration and testing, remove profile ownership from CPT.

### Features

- Remove profile field schema from CPT.
- Remove CPT owner profile table creation/update paths.
- Remove CPT My Profile UCP block.
- Remove CPT public profile rendering path.
- Keep album ownership/root helper functions in CPT.
- Keep privacy/sharing/representative image in CPT.

---

## Phase 4: Search and Normalized Indexing

### Goal

Optionally expose selected normalized profile traits for search/filtering.

### Features

- Keep profile table canonical.
- Add derived search tags only for selected normalized fields.
- Avoid raw tag sync for free-text fields.
- Possible sync fields: city, nationality, services, languages, contact flags, age range.
- Do not sync measurements as raw tags.

---

## Definition of Roadmap Done

- Profile data lives outside CPT.
- Existing portal behavior remains unchanged.
- 2FA reads candidate phone from Owner Profile plugin.
- PLG still reads verified phone from 2FA only.
- CPT remains the album/privacy engine only.
- Theme remains presentation only.

\*Foortnote

- The CPT (community privacy toggle) plugin is in
  `/home/marcel/projects/piwigo/plugins/core_privacy_toggle` directory
- The 2FA (two factor customized) plugin is in
  `/home/marcel/projects/piwigo/plugins/two_factor` directory
- The PLG (profile liveness guard) plugin is in
  `/home/marcel/projects/piwigo/plugins/profile_liveness_guard` directory
