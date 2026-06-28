# Owner Profile

- Internal name: `owner_profile`
- Purpose: owns public owner profile storage, rendering, and migration from the legacy CPT profile table

Current implementation scope:

- plugin bootstrap and install/upgrade hooks
- canonical `piwigo_owner_profile` table
- idempotent migration from `piwigo_cpt_owner_profile`
- profile editor block on the Piwigo profile page
- public profile rendering on owner root album pages

Planned integration work follows the staged rollout in `.github/docs/OP_PROJECT_ROADMAP.md`.
