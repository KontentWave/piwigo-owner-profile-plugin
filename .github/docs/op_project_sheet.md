# Owner Profile Plugin `project_sheet.md` (The Present 📜)

This document is the living technical specification for extracting owner profile data from CPT into a standalone Piwigo plugin.

It now serves two purposes:

- document what is already implemented in the standalone `owner_profile` repository
- separate that from the still-deferred compatibility and integration work

Suggested plugin name:

```text
owner_profile
```

Suggested repository name:

```text
piwigo-owner-profile-plugin
```

---

## Action

Create a new standalone Piwigo plugin that owns profile fields, contact data, availability, UCP My Profile, and public profile payload/rendering.

CPT remains responsible for album ownership and privacy.

## Current Delivery State

Implemented now in the standalone `owner_profile` repository:

- standalone plugin bootstrap and repository split
- canonical `piwigo_owner_profile` table
- install-time and lazy idempotent migration from `piwigo_cpt_owner_profile`
- Owner Profile UCP block on the Piwigo profile page
- AJAX save path with CSRF check and ownership validation
- public profile rendering on owner root album pages only
- plugin-owned city option dataset and loader for the `city` controlled field
- server-side Bootstrap Darkroom payload preparation via `OPP_OWNER_PROFILE_TABLE`
- Slovak phone normalization helper and candidate-phone helper
- focused PHPUnit coverage for save validation and public rendering

Planned later in other PRs:

- Two Factor integration changes
- CPT disable/skip behavior when Owner Profile is active
- temporary CPT compatibility wrappers if they are still needed
- PLG regression-only follow-up
- search/tag indexing follow-up

Implemented now for the CPT extraction transition:

- `opp_get_owner_profile_editor_data()` and `opp_get_owner_profile_public_data_for_album()` are globally available through the normal plugin bootstrap path
- Owner Profile attaches `My Profile` independently of CPT's old `UCP_OWNER_PROFILE` assignment
- Owner Profile save/update flows use Owner Profile storage and `owner_profile.update`
- Owner Profile public rendering stays root-album-only while continuing to use CPT helper APIs for owner/root resolution
- focused PHPUnit coverage now mirrors the key CPT extraction scenarios from the Owner Profile side

---

## Responsibility Split

```text
Owner Profile plugin
= profile fields
= profile table
= My Profile UCP editor
= profile validation and persistence
= public profile payload
= contact phone candidate for 2FA
= availability rows
= public profile rendering partial

CPT
= album ownership
= effective owner/root album resolution
= album privacy/sharing
= representative image
= cpt_update_album()
= album visibility helpers

Two Factor SMS
= reads candidate phone from Owner Profile
= stores verified phone itself

PLG
= reads verified phone from Two Factor
= asks CPT to privatize/restore albums

Bootstrap Darkroom
= layout/placement only

CUG
= upload target guard only
```

---

## Dependency Contract

Required:

- Piwigo
- CPT plugin active for owner/root album resolution

Optional but expected in the full portal:

- Two Factor SMS
- Profile Liveness Guard
- Bootstrap Darkroom custom theme
- Community / Community Upload Guard

The Owner Profile plugin should fail gracefully if CPT is missing:

```text
- no My Profile block for users without resolvable owner root album
- admin warning that CPT is required
- no profile save without owner/root verification

Cross-plugin extraction note:

- CPT now detects Owner Profile by checking `function_exists('opp_get_owner_profile_editor_data')` and `function_exists('opp_get_owner_profile_public_data_for_album')`
- those functions must remain available from the normal active plugin bootstrap path
- Owner Profile should treat CPT strictly as an ownership/privacy helper provider, not as a source of profile template variables or legacy save webservices
```

---

## Database

New canonical table:

```sql
CREATE TABLE piwigo_owner_profile (
  id INT AUTO_INCREMENT PRIMARY KEY,
  root_album_id INT NOT NULL,
  owner_user_id INT NOT NULL,
  field_key VARCHAR(64) NOT NULL,
  value_text TEXT DEFAULT NULL,
  tag_id INT DEFAULT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY root_field (root_album_id, field_key),
  KEY owner_user_id (owner_user_id),
  KEY tag_id (tag_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
```

Migration source:

```text
piwigo_cpt_owner_profile
```

Migration target:

```text
piwigo_owner_profile
```

Pseudo-SQL:

```sql
INSERT IGNORE INTO piwigo_owner_profile
  (root_album_id, owner_user_id, field_key, value_text, tag_id, updated_at)
SELECT
  root_album_id, owner_user_id, field_key, value_text, tag_id, updated_at
FROM piwigo_cpt_owner_profile;
```

---

## Field Schema

Initial field schema is copied from CPT.

Field groups:

```text
basic
= nationality, city, age, measurements

body
= breasts, eyes, hair, private_parts, tattoo, piercing

services
= experience, i_offer, other_girls, services_for, i_speak

contact
= contact_number, contact_phone, contact_sms, contact_whatsapp

availability
= availability_monday ... availability_sunday
```

Contact interpretation:

```text
contact_number
= actual phone/contact number text

contact_phone
= Yes/No flag for public phone calls

contact_sms
= Yes/No flag for public SMS contact

contact_whatsapp
= Yes/No flag for public WhatsApp link

city
= resolved from `include/data/city_options.txt` inside Owner Profile, not from CPT

city legacy compatibility
= editor payload remaps stale stored `tag_id` values by saved `value_text` when older CPT-era numbering no longer matches the local city option list
```

---

## Public Helper API

Expose stable helper names with a neutral prefix:

```php
opp_get_owner_profile_field_schema(): array
opp_get_owner_profile_editor_data(int $user_id): ?array
opp_get_owner_profile_public_data_for_album(int $album_id): ?array
opp_fetch_owner_profile_rows(int $root_album_id, int $owner_user_id): array
opp_update_owner_profile(array $payload, int $user_id): bool
opp_get_contact_phone_candidate(int $user_id): array
opp_get_contact_rows(int $user_id): array
opp_get_last_error(): ?string
opp_get_rendered_owner_profile_table_for_album(int $album_id): ?string
```

Suggested contact candidate return:

```php
array(
  'available' => true,
  'raw_phone' => '+421 905 000 000',
  'normalized_phone' => '+421905000000',
  'masked_phone' => '+421905***000',
  'source' => 'owner_profile.contact_number',
  'flags' => array(
    'contact_phone' => true,
    'contact_sms' => true,
    'contact_whatsapp' => false,
  ),
  'error' => null,
)
```

---

## Temporary Compatibility API

Status: planned later, not implemented yet in the current standalone repository.

During migration, provide wrappers for old CPT profile functions if CPT has not already defined them:

```php
cpt_get_owner_profile_field_schema()
cpt_get_owner_profile_editor_data()
cpt_get_owner_profile_public_data_for_album()
cpt_fetch_owner_profile_rows()
cpt_update_owner_profile()
```

Compatibility policy:

```text
- wrappers are transitional only
- new code should call OPP helpers
- old wrappers may be removed after 2FA/CPT/PLG docs and code are migrated
```

---

## UCP My Profile

The new plugin owns:

```text
template/ucp_owner_profile.tpl
js/owner_profile.js
template/style.css
```

Behavior:

1. Logged-in owner opens Profile.
2. Plugin resolves owner root album via CPT.
3. Plugin loads saved profile rows.
4. Plugin renders My Profile block.
5. Owner saves profile.
6. Server verifies owner/root album ownership before saving.

Security:

- owner can edit only own root profile
- CSRF token required
- unknown field keys ignored
- controlled values must match known options
- contact number is normalized only where needed; stored display value may preserve readable formatting

Current implementation note:

- `contact_number` is validated as a Slovak phone candidate for the current standalone plugin behavior
- the UI provides immediate client-side validation in addition to backend validation

---

## Public Album Page Rendering

The plugin owns the public profile block.

Flow:

1. Album page loads.
2. Plugin resolves effective owner root album via CPT.
3. Plugin fetches public profile rows for that root owner.
4. Plugin assigns profile data to Smarty.
5. Plugin renders owner profile partial.
6. Plugin injects into a generic page slot for non-theme-specific flows, or prepares theme-consumable Smarty payloads for Bootstrap Darkroom.

Suggested variables:

```text
OPP_OWNER_PROFILE_ROWS
OPP_OWNER_PROFILE_CONTACTS
OPP_OWNER_PROFILE_AVAILABILITY
OPP_OWNER_PROFILE_TABLE
OPP_ALBUM_PAGE_HTML
```

Implemented now:

- `OPP_UCP_OWNER_PROFILE`
- `OPP_OWNER_PROFILE_ROWS`
- `OPP_OWNER_PROFILE_CONTACTS`
- `OPP_OWNER_PROFILE_AVAILABILITY`
- `OPP_OWNER_PROFILE_TABLE`
- `OPP_ALBUM_PAGE_HTML`
- `opp_get_rendered_owner_profile_table_for_album($album_id)`

Bootstrap Darkroom contract now implemented:

```text
desktop:
albums -> description -> owner profile block

mobile:
first album -> description -> owner profile block -> remaining albums
```

Implementation note:

- Bootstrap Darkroom renders owner-profile payloads directly from Smarty variables at its responsive anchors.
- The theme prefers `OPP_OWNER_PROFILE_TABLE` and may fall back to `CPT_OWNER_PROFILE_TABLE` during migration.
- For Bootstrap Darkroom, the plugin must assign `OPP_OWNER_PROFILE_TABLE` early enough for mobile category rendering, not only during the late attach hook.
- For Bootstrap Darkroom, `opp_prepare_album_page_assets()` still loads public profile CSS but skips the public placement script.
- For Bootstrap Darkroom, `opp_attach_owner_profile_to_album_page()` must not depend on `PLUGIN_INDEX_CONTENT_BEGIN` or `window.OPP_ALBUM_PAGE_HTML` for final placement.
- Generic themes may still use normal plugin content slots and plugin-side payload injection.

Implemented Bootstrap Darkroom helper behavior:

```text
opp_get_rendered_owner_profile_table_for_current_album()
= resolves the current album page
= renders owner_profile_table.tpl
= assigns OPP_OWNER_PROFILE_TABLE
= reuses the already assigned value when available
```

Important behavioral detail:

```text
Bootstrap Darkroom mobile placement runs earlier than its desktop placement block.
If OPP_OWNER_PROFILE_TABLE is assigned only in the late attach hook,
mobile owner-profile rendering can disappear while desktop still appears correct.
```

Temporary compatibility variables:

```text
CPT_OWNER_PROFILE_ROWS
CPT_OWNER_PROFILE_CONTACTS
CPT_OWNER_PROFILE_AVAILABILITY
CPT_OWNER_PROFILE_TABLE
CPT_ALBUM_PAGE_HTML
```

Status: Bootstrap Darkroom currently consumes `OPP_OWNER_PROFILE_TABLE` first and falls back to `CPT_OWNER_PROFILE_TABLE`. The remaining compatibility variables are still transition targets for broader cross-plugin integration.

Cross-plugin extraction contract implemented now:

```text
- CPT contributes My Galleries
- Owner Profile contributes My Profile
- CPT no longer needs to assign UCP_OWNER_PROFILE for Owner Profile to work
- Owner Profile renders the public profile block only for the effective owner root album
- descendant albums do not render the public profile block
```

---

## 2FA Integration

Status: deferred to PR 3 in the dedicated `two_factor` workspace.

Two Factor SMS should call:

```php
opp_get_contact_phone_candidate($user_id)
```

It must still store verified phone in its own table after OTP verification.

Rule:

```text
Owner Profile contact_number = editable candidate phone
two_factor.phone_number = trusted verified phone
PLG = uses trusted Two Factor phone only
```

---

## PLG Integration

Status: no direct Owner Profile changes are required yet.

PLG does not need raw owner profile data.

PLG continues to use:

```text
Two Factor -> verified SMS phone
CPT -> album/root/privacy/snapshot/restore
```

Owner Profile plugin is not a required direct dependency for PLG.

---

## Test Plan

Implemented or covered now:

1. Migrates rows from `piwigo_cpt_owner_profile`.
2. Does not duplicate rows when migration runs twice.
3. Owner sees the Owner Profile UCP block.
4. Owner can save text fields.
5. Owner can save controlled fields.
6. Non-owner cannot save another owner profile.
7. Public album page displays rows/contacts/availability.
8. Invalid Slovak `contact_number` is rejected.
9. Contact phone candidate returns `contact_number` from Owner Profile helpers.
10. Contact flags do not become phone numbers in Owner Profile helper behavior.
11. Bootstrap Darkroom keeps owner-profile placement in `OPP_OWNER_PROFILE_TABLE`.
12. Bootstrap Darkroom skips plugin-side public placement script while still loading CSS.
13. Owner Profile attaches the replacement `My Profile` block independently of CPT's old profile variable.
14. CPT album visibility/shared-user helper changes do not modify Owner Profile rows.
15. Owner Profile still resolves the same effective root album while CPT visibility helpers remain usable.
16. City options are loaded locally from Owner Profile data, including continuation-row labels such as Bratislava II and Košice IV.
17. Editor data remaps legacy city `tag_id` values from the saved city label when local option numbering differs from old CPT numbering.

Deferred to later PRs:

18. 2FA reads candidate phone from Owner Profile with CPT fallback.
19. CPT skips its old profile block when Owner Profile plugin is active in live cross-plugin verification.
20. PLG regression verification after 2FA/CPT integration changes.
21. Search/indexing behavior for selected normalized fields.

---

## Definition of Done

PR 1 done:

- New plugin installs and creates its own table.
- Existing CPT profile rows migrate successfully.

PR 2 done or partially done:

- My Profile works from the new plugin.
- Public profile display works from the new plugin.
- focused tests exist for save validation and public rendering behavior.

Still deferred:

- 2FA candidate phone source works through live Two Factor integration.
- CPT skips old profile block when Owner Profile plugin is active.
- no CUG change is required remains an assumption until later integration passes confirm it.

## CPT Extraction Integration

Current cross-plugin goal:

- whenever both CPT and Owner Profile are enabled, Owner Profile becomes the active owner of profile UI, profile persistence, and public profile rendering
- CPT remains the owner of album privacy, sharing, representative-image behavior, and owner/root/visibility helper APIs

Owner Profile responsibilities in this transition:

- attach the `My Profile` block on the Piwigo profile page
- provide the editor payload for that block
- accept profile saves through `owner_profile.update`
- render the public profile block for effective owner root albums only
- avoid showing the public profile block on descendant albums
- avoid any dependency on CPT assigning `UCP_OWNER_PROFILE` or exposing `core_privacy_toggle.owner_profile.update`

CPT helper APIs Owner Profile should keep using:

- `cpt_get_effective_owner_root_album_id_for_user()`
- `cpt_get_effective_owner_root_album_id_for_album()`
- `cpt_get_effective_owner_root_album_data()`
- `cpt_get_album_effective_owner_id()`
- `cpt_get_album_visibility_mode()`
- `cpt_get_album_shared_user_ids()`

Acceptance shape for this integration:

1. On the profile page, `My Galleries` comes from CPT and `My Profile` comes from Owner Profile.
2. On the owner root album page, the public profile block comes from Owner Profile.
3. On descendant albums, no public profile block is rendered.
4. Changing album visibility in CPT does not modify profile data.
5. CPT helper APIs remain usable by PLG and other plugins.
