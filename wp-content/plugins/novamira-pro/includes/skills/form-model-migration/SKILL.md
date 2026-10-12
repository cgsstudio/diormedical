---
name: form-model-migration
description: Activate when migrating a contact-form schema between form plugins — moving Contact Form 7 forms to WPForms, WPForms to Gravity Forms, Gravity Forms to Fluent Forms, Fluent Forms to Formidable Forms, Ninja Forms to anything (or vice-versa), and so on. Walks the agent through the seven-phase workflow (audit → plan → execute → verify) using form-model-schema as the canonical intermediate format. Schemas only — submitted entries / Flamingo rows / WPForms entry table / Gravity entries / Fluent submissions / Formidable entries / Ninja Forms submissions are explicitly out of scope. Trigger phrases. "migrate forms from X to Y", "move CF7 forms to WPForms", "convert Gravity Forms to Fluent Forms", "audit what I'd lose moving from WPForms to CF7", "import this Ninja Forms form as Gravity", "switch form plugin", "AI-assisted form schema migration". This skill turns the canonical vocabulary into a guided, confirmable migration — it does not claim one-click universal import/export.
---

# Form Model Migration

A workflow playbook for moving contact-form schemas between up to six form plugins (Contact Form 7, WPForms, Gravity Forms, Fluent Forms, Formidable Forms, Ninja Forms). Uses `form-model-schema` as the canonical intermediate format and treats migration as a seven-phase, user-confirmed pipeline — never an autonomous one-shot. The plugins listed represent the full intended coverage; whether a side is actually available in your install is decided at runtime by Phase 1 (`<plugin>-check-setup`) — branch on its result, never assume a reader is registered.

**Ninja Forms is a first-class, symmetric citizen** like the other five plugins. Novamira Pro drives it entirely through its own `novamira/ninja-forms-*` abilities (model layer — no dependency on Ninja's native `ninjaforms/*` abilities) for read AND write: `ninja-forms-check-setup` (Phase 1), `ninja-forms-list-forms` / `ninja-forms-get-form` / `ninja-forms-get-form-canonical` (Phase 2), and `ninja-forms-create-form` + `add-field` + `create-notification` + `edit-form` (Phase 5 when Ninja is the target). See the `ninja-forms-integration` skill for the full surface.

## Scope

**Covered:**
- Forms (one form per migration; loop the pipeline for batch migrations).
- Fields, with type translation via the `form-model-schema` 17-type vocabulary.
- Mail configuration: subject, sender, recipient, body, additional headers, attachments, HTML flag, exclude-blank flag.
- Additional notifications (autoresponder, secondary admin, BCC) when the source supports them.
- Validation messages (`messages{}`) — string-keyed, preserved as-is on round-trip.
- Conditional logic (`conditional_rules[]`) when the source exposes structured rules.
- Field-level validation rules (min, max, length, regex, mime accepts, file size).

**Not covered (out of scope by design):**
- Submitted entries (Flamingo `flamingo_inbound` / `flamingo_outbound` for CF7, WPForms `wpforms_entries` table, Gravity `gform_entry` table, Fluent `fluentform_submissions` table, Formidable `frm_items` table). Submission migration is a separate problem with plugin-specific storage layouts. Decline politely and direct the user to per-plugin export tools (CSV / XML) if they insist.
- Lossless conversion of:
  - Anti-spam configuration — Akismet, reCAPTCHA, hCaptcha, Turnstile, honeypots. Site-global per plugin, must be reconfigured on the target.
  - Anti-spam logic — CF7 `quiz`, WPForms anti-spam token, Gravity honeypot, Fluent reCAPTCHA. Lost on migration; warning severity `structural`.
  - Third-party integrations — Mailchimp, Salesforce, Stripe / PayPal payments, Zapier webhooks, CRM sync. Must be reconfigured on the target plugin manually.
  - Credentials / API tokens — Never migrated; recreate on target.
  - Multi-step / multi-page forms — canonical has no `page` field type; migrations produce a single-page equivalent with `severity: structural` warnings.
  - Form-level custom CSS / JS — out of scope, reapply manually.
- Builder bindings (Elementor form widget references, Bricks contact-form binding, Bricks query loops referencing form IDs). These break on the new plugin because the form ID changes — flag this as a follow-up task, do not attempt to translate in-place.

## Mandatory workflow

Before any write, the agent must walk these phases in order. Skipping a phase is not allowed.

### Phase 0 — Activate the prerequisite skills

In order, in the same turn:

1. `novamira/skill-get slug=form-model-schema` — load the canonical vocabulary.
2. `novamira/skill-get slug=<source-plugin>-integration` — e.g. `cf7-integration` if migrating from CF7.
3. `novamira/skill-get slug=<target-plugin>-integration` — e.g. `wpforms-integration` if migrating to WPForms.

Without all three, the agent does not have the knowledge to translate. `skill-get` returns `not_found` only when the slug literally does not exist in the bundled skill index (typo or the skill was renamed) — that's a fatal error, halt. The skill being registered does NOT imply the underlying plugin is installed on the site: that check happens in Phase 1 below via the per-plugin `check-setup` ability. Do not conflate the two.

### Phase 1 — Setup check, both ends

Call check-setup on both plugins. **Both must report active AND meet the minimum version.** Refuse to proceed otherwise — there is no point planning a migration toward an inactive target or one below the audited minimum.

| Source / target plugin | Setup ability | Min-version constant |
|---|---|---|
| Contact Form 7 | `novamira/cf7-check-setup` | `NOVAMIRA_PRO_CF7_MIN_VERSION` (5.8.0) |
| WPForms (Lite / Pro) | `novamira/wpforms-check-setup` | `NOVAMIRA_PRO_WPFORMS_MIN_VERSION` (1.8.0) |
| Gravity Forms | `novamira/gravityforms-check-setup` | `NOVAMIRA_PRO_GRAVITYFORMS_MIN_VERSION` (2.7) |
| Fluent Forms (Lite / Pro) | `novamira/fluentforms-check-setup` | `NOVAMIRA_PRO_FLUENTFORMS_MIN_VERSION` (6.0.0) |
| Formidable Forms (Lite / Pro) | `novamira/formidable-check-setup` | `NOVAMIRA_PRO_FORMIDABLE_MIN_VERSION` (6.0.0) |
| Ninja Forms | `novamira/ninja-forms-check-setup` | `NOVAMIRA_PRO_NINJAFORMS_MIN_VERSION` (3.13.0) — `storage_ready` flag must be `true` |

**Handle inactive-plugin null returns explicitly**: if a plugin is not active on the site, its `*-check-setup` ability is NOT registered in the WP Abilities API at all — `wp_get_ability('novamira/<plugin>-check-setup')` returns `null`, NOT a structured `{plugin_active: false}` response. Detect this with `if ( null === wp_get_ability(...) )` (or the equivalent in your wrapper) and treat as "plugin inactive — halt with a clear message instructing the user to activate the plugin first". Don't try to coerce a missing ability into a falsy structured response — it'll throw.

Record edition gates carefully. Each is a Phase-1 halt-condition if the target side doesn't satisfy it.

| Edition gate | Requirement | What to check in check-setup output |
|---|---|---|
| WPForms Pro field types | `rich-text`, `file-upload`, `multi-select`, `signature`, `rating`, `payment-*` | `wpforms-check-setup` reports `edition: pro`. Refuse to plan Pro types when target is Lite. |
| Gravity Forms license | All Gravity functionality | `gravityforms-check-setup` reports `license_active: true`. Gravity refuses to render forms on the front-end when the license has lapsed — refuse the migration if `false`. |
| Fluent Forms Pro field types | `repeater_field`, `signature`, `ratings`, `payment_*` | `fluentforms-check-setup` reports `edition: pro`. Refuse Pro types on Lite target. |
| Formidable Pro field types | `image`, `lookup`, `rich_text`, `signature`, `payment`, `summary`, `tags`, `address`, `name` (composite) | `formidable-check-setup` reports `edition: pro`. Refuse Pro types on Lite target. |
| CF7 | No edition gate (single-edition plugin) | `cf7-check-setup` always reports the same shape; `min_satisfied` is the only halt-condition. |

**Coexistence**: all five form plugins coexist at boot. No `Cannot redeclare ...` collisions in the supported version range. If two are active simultaneously (e.g. CF7 + WPForms during a transition), they each register their own admin menu and CPTs — no cross-conflict.

### Phase 2 — Read the source schema

Use the source integration's read abilities to produce a complete picture. The per-plugin read surface is **not symmetric** — each plugin has its own ability names and `include_fields` semantics. Use this table:

| Source plugin | List forms | Get form (full + canonical) | Tag/field vocabulary |
|---|---|---|---|
| Contact Form 7 | `cf7-list-forms` | `cf7-get-form id=<id>` (raw shape) + `cf7-get-form-canonical id=<id>` (canonical shape with warnings) | `cf7-get-tags-schema` |
| WPForms | `wpforms-list-forms` | `wpforms-get-form id=<id>` + `wpforms-get-form-canonical id=<id>` | `wpforms-get-field-types` |
| Gravity Forms | `gravityforms-list-forms` | `gravityforms-get-form id=<id>` + `gravityforms-get-form-canonical id=<id>` | `gravityforms-get-field-types` |
| Fluent Forms | `fluentforms-list-forms` | `fluentforms-get-form id=<id>` + `fluentforms-get-form-canonical id=<id>` | `fluentforms-get-field-types` |
| Formidable Forms | `formidable-list-forms` | `formidable-get-form id=<id>` + `formidable-get-form-canonical id=<id>` | `formidable-get-field-types` |
| Ninja Forms | `ninja-forms-list-forms` | `ninja-forms-get-form form_id=<id>` (raw shape; `include=["fields","actions","settings"]`) + `ninja-forms-get-form-canonical form_id=<id>` (canonical shape with warnings) | `ninja-forms-get-field-types-schema` |

Prefer the `*-get-form-canonical` flavour when present — it returns the schema already mapped to the `form-model-schema` vocabulary plus the relevant `warnings[]`, saving the agent the manual translation step.

When the source plugin doesn't yet expose a canonical reader, fall back to the raw `*-get-form` and translate in Phase 3 using the per-plugin mapping section below.

### Phase 3 — Translate to canonical

Map the source form into a `form-model-schema` JSON document. Use the per-plugin "Mapping guidance" section below for field-type and structural translations.

While translating:

- For every concept that does NOT fit the canonical vocabulary, append an entry to `warnings[]` with:
  - `path` — JSON-pointer-ish reference (e.g. `fields[3].quiz`, `mail_config.additional_headers`).
  - `reason` — what is being dropped or transformed, in user-readable language.
  - `severity` — `structural` (semantics changed: anti-spam logic dropped, conditional logic flattened, multi-page collapsed to single-page) or `informational` (UI sugar dropped: CSS class names, label position hint, admin column).
- Never invent canonical types ad-hoc. If a source type has no canonical equivalent, model it with the closest match plus modifiers and warn about the gap.
- Normalize mail-tag syntax to `[field-name]` notation (the CF7 convention adopted as canonical). The target side translates back to its own syntax in Phase 5.

If the source's `*-get-form-canonical` ability already returned a canonical shape, this phase is a pass-through — preserve the warnings the ability emitted, do not re-translate.

### Phase 4 — Show the plan

Print to the user:

1. The canonical schema (compact summary if large — full body on request).
2. The migration plan: the target-plugin write that will be performed (ability name + payload shape) — usually a single `<target>-create-form` call, but some targets need follow-up `<target>-edit-form` for notifications that don't fit in `create`.
3. The full `warnings[]` list with severity.
4. Any structural warnings called out at the top of the message.
5. Name-collision detection: call `<target>-list-forms` and check whether a form with the same title or slug already exists. If yes, set `next_action_required: user_resolution_for_name_collision` and propose skip / rename / merge.

**Stop and request explicit user confirmation before any write.** If `warnings[]` contains entries with `severity: structural`, the confirmation must restate them ("Confermi anche queste perdite strutturali? <list>").

### Phase 5 — Execute (only after confirmation)

Call the target plugin's `create-form` ability with the translated payload. Sequence:

1. **Create the form** with `<target>-create-form`: title, slug, status, locale, fields, primary mail config, messages. This is the single load-bearing call.
2. **Add additional notifications** (when the source had `notifications[]` entries beyond the primary `mail_config`): one call to `<target>-edit-form id=<new id> notifications=[...]` (or, for plugins that store all notifications inline in the form payload, fold them into step 1).
3. **Apply conditional rules** (when both ends support `conditional_rules[]`): either inline in `create-form` or via a follow-up `<target>-edit-form`, plugin-dependent.
4. **Apply reserved-name renames** if the target rejected a field name as reserved (Gravity reserves `entry_id`, `form_id`, `date_created`, …; Fluent reserves `id`, `form_id`; WPForms reserves `wpforms_*`; Formidable reserves `frm_*`). The check happens at write-side — if a `*-create-form` fails with a reserved-name error, propose a prefixed alternative and re-run.

If any write fails, halt the pipeline. Do not auto-retry. Report which step failed, the error, and the partial state to the user (the new form may have been created but lack its notifications; the user must decide to delete-and-retry or patch in place).

**Per-plugin write abilities** — the canonical payload must be translated to each plugin's parameter convention. Read the ability description (`wp_get_ability(...)->get_input_schema()` or directly the file under `includes/abilities/<plugin>/`) before composing the payload. **Do not assume canonical key names match the target's keys.**

| Target plugin | Create form ability | Notifications shape |
|---|---|---|
| Contact Form 7 | `cf7-create-form title=... form=<form-tag string> mail={...} mail_2={...} messages={...}` | Exactly one `mail_2` (secondary). For >1 canonical notifications, only the FIRST is mapped to `mail_2`; remaining are dropped with `severity: structural`. |
| WPForms | `wpforms-create-form title=... fields=[...] notifications=[...]` | Array of notifications inline. |
| Gravity Forms | `gravityforms-create-form title=... fields=[...] notifications=[...]` | Array of notifications inline. |
| Fluent Forms | `fluentforms-create-form title=... fields=[...] notifications=[...]` | Array of notifications inline. |
| Formidable Forms | `formidable-create-form name=... fields=[...] actions=[...]` | Notifications are "Form Actions" of type `email`. |

**Canonical → CF7 form-tag string** (write side helper — keep this snippet as the reference implementation, agents may invoke it via `novamira/execute-php` when CF7 is the target):

```php
$tag_for = function (array $field): string {
    $type = $field['type'] ?? 'text';
    $name = $field['name'] ?? '';
    $required = !empty($field['required']) ? '*' : '';
    $opts = [];

    // Map canonical type → CF7 tag type
    $cf7_type = match ($type) {
        'text'     => 'text',
        'email'    => 'email',
        'tel'      => 'tel',
        'url'      => 'url',
        'number'   => 'number',
        'textarea' => 'textarea',
        'date'     => 'date',
        'select'   => 'select',
        'radio'    => 'radio',
        'checkbox' => 'checkbox',
        'file'     => 'file',
        'hidden'   => 'hidden',
        'submit'   => 'submit',
        'consent'  => 'acceptance',
        default    => 'text', // fallback — agent should have warned
    };

    if (!empty($field['placeholder'])) {
        $opts[] = 'placeholder "' . addslashes((string) $field['placeholder']) . '"';
    }
    if (!empty($field['validation']['min_length'])) {
        $opts[] = 'minlength:' . (int) $field['validation']['min_length'];
    }
    if (!empty($field['validation']['max_length'])) {
        $opts[] = 'maxlength:' . (int) $field['validation']['max_length'];
    }
    if (!empty($field['validation']['min'])) {
        $opts[] = 'min:' . $field['validation']['min'];
    }
    if (!empty($field['validation']['max'])) {
        $opts[] = 'max:' . $field['validation']['max'];
    }
    foreach (($field['choices'] ?? []) as $choice) {
        $opts[] = '"' . addslashes((string) ($choice['label'] ?? $choice['value'] ?? '')) . '"';
    }

    if ($cf7_type === 'submit') {
        // Submit carries its label as the only positional token.
        return '[submit "' . addslashes((string) ($field['label'] ?? 'Send')) . '"]';
    }

    $opts_str = $opts === [] ? '' : ' ' . implode(' ', $opts);
    return '[' . $cf7_type . $required . ' ' . $name . $opts_str . ']';
};

$form_tag_string = implode("\n", array_map($tag_for, $canonical['fields']));
```

The snippet is intentionally small — it's exactly the kind of "simple thing" that belongs as a documented `execute-php` template, not as a new ability. The complex part of CF7 (parsing the form-tag string back into structured canonical) IS a PHP ability (`cf7-get-form-canonical`) because the regex / modifier handling is non-trivial.

### Phase 6 — Verify

Re-read the target form with the same abilities used in Phase 2 (`<target>-get-form-canonical`), and produce a verification summary:

- Field count: canonical source vs canonical target.
- Per-field diff: type, required flag, validation block.
- Mail config diff: subject, sender, recipient, body presence.
- Notifications count match.
- Re-emitted `warnings[]` from the target read — these document what survived round-trip vs what was lost.

Surface the verification summary to the user before declaring success.

**Failure handler — re-read returns an error.** If `<target>-get-form-canonical` returns a `WP_Error` or the equivalent during verification (target plugin deactivated mid-pipeline, transient DB connectivity issue, form id corrupted, target check-setup now reporting inactive), do NOT mark the migration itself as failed — Phase 5 has already completed successfully and the target form exists. Instead:

1. Report to the user that the verification step could not run, citing the specific error from the re-read.
2. Emit `next_action_required: none` (the migration is complete; verification is the unverified deliverable).
3. Recommend the user re-run the workflow in `mode: verify` once the target plugin is reachable again — that path will produce the verification summary without re-creating anything.
4. Do not delete or roll back the target form on a verification failure. The Phase 6 failure is an observability gap, not a migration failure.

## Modes

The user can request a subset of the pipeline:

| Mode | Phases run | Output |
|---|---|---|
| `audit-only` | 0 → 3 | Canonical schema + warnings[]. No plan, no write. |
| `plan` | 0 → 4 | Canonical schema + migration plan + warnings[]. No write. |
| `execute` | 0 → 6 | Full pipeline; requires explicit confirmation between 4 and 5. |
| `verify` | 6 only (against an existing target form) | Verification summary comparing source canonical vs target canonical. Useful after a manual migration. |

If the user does not specify a mode, default to `plan` and ask whether to execute.

## Safety rules

These are non-negotiable. The agent must apply them even if the user asks to skip them.

1. **Never overwrite an existing target form** without the user explicitly typing the form title to confirm. If the target already has a form with the same title or slug as the one we are about to create:
   - **Stop the execute phase.**
   - Propose: (a) skip this form, (b) rename (offer a suffix like `_migrated`), or (c) merge — only when the structures are compatible.
2. **Never modify the source form** during migration. The migration is additive on the target side; the source is read-only.
3. **Never migrate stored submissions** as part of a schema migration. If the user asks for "everything", restate the scope ("Migro lo schema. Le submission richiedono un passo separato.") and require explicit re-confirmation if they insist.
4. **Always pause on `severity: structural` warnings** before Phase 5. Read them back to the user verbatim and require yes/no.
5. **Refuse delete operations on the source.** The migration never deletes the source form. Cleanup is a separate, explicit task.
6. **One pipeline per turn.** Don't chain multiple source-to-target migrations in one execute phase — each gets its own audit/plan/confirm cycle.
7. **Name collision on the target — detect at Phase 4, halt at Phase 5.** Call `<target>-list-forms` in Phase 4 and check whether a form with the same title or slug already exists. If yes, do NOT execute Phase 5 — instead set `next_action_required: user_resolution_for_name_collision` and present the user with three options: (a) skip this form, (b) rename with a suffix (`_migrated`, `_imported`), or (c) merge — only when the structures are compatible. Do not auto-resolve.
8. **Reserved field names — check at write-side, not at translate-side.** Each plugin has a different reserved set (Gravity `entry_id`, `form_id`, `date_created`, `created_by`, `is_starred`, `is_read`, `ip`, `source_url`, `post_id`, `currency`, `payment_status`, `payment_date`, `transaction_id`, `payment_amount`, `payment_method`, `is_fulfilled`, `transaction_type`, `user_agent`; Fluent Forms `id`, `form_id`, `serial_number`; WPForms `wpforms_*`; Formidable `frm_*`). The check belongs in Phase 5 (just before each `<target>-create-form` call), not in Phase 3 (translate). The source plugin's read abilities have no view of the target's reserved set, so do not try to populate a `warnings[]` entry from the translate side. If the create call rejects a name during execute, halt, propose a prefixed alternative (`contact_form_id`), and resume from there.
9. **Never migrate credentials.** API keys, integration tokens, payment processor secrets — never copied. The user reconfigures them on the target plugin from their source of truth.

## Mapping guidance — per plugin

Field-type translation goes through the `form-model-schema` 17-type vocabulary. Plugin-specific quirks the agent must know:

### Contact Form 7

CF7's form template is a **single string** of bracket-syntax form-tags, not a structured array. The `cf7-get-form-canonical` ability handles the parsing; from canonical the write-side rebuilds the string via the `execute-php` snippet shown in Phase 5.

**Form-tag → canonical:**

| CF7 form-tag | Canonical type | Notes |
|---|---|---|
| `text`, `text*` | `text` | `*` → `required: true`. `minlength:`, `maxlength:`, `placeholder` map to `validation` / `placeholder`. |
| `email`, `email*` | `email` | |
| `tel`, `tel*` | `tel` | |
| `url`, `url*` | `url` | |
| `number`, `number*` | `number` | `min:`, `max:`, `step:` → `validation`. |
| `date`, `date*` | `date` | `min:`, `max:` → `validation` (ISO `YYYY-MM-DD`). |
| `textarea`, `textarea*` | `textarea` | `cols:` / `rows:` go to `meta` (rendering hint, not canonical). |
| `select`, `select*` | `select` | `multiple` → `cardinality: multi`. `include_blank` → `meta`. Quoted tokens become `choices[]`. |
| `checkbox`, `checkbox*` | `checkbox` | `cardinality: multi` by default. `exclusive` → `meta` (single-pick mode). |
| `radio` | `radio` | `cardinality: single` always. |
| `file`, `file*` | `file` | `filetypes:` → `validation.accepts` (MIME-mapped). `limit:` → `validation.max_size_bytes`. |
| `acceptance` | `consent` | `optional` modifier → `warning: informational` (canonical `consent` is always required). |
| `quiz` | — | `warning: structural` — anti-spam logic has no canonical equivalent. |
| `submit` | `submit` | Quoted token → `label`. |
| `hidden` | `hidden` | `default:` → `default`. |
| `response` | — | Output area, no canonical equivalent — skipped in translation. |
| `recaptcha` | — | `warning: informational` — keys are site-global, not per-form. |

**Special mail-tags** (`[_serial_number]`, `[_remote_ip]`, `[_user_agent]`, `[_date]`, `[_time]`, `[_url]`, `[_post_title]`, `[_user_email]`) are preserved verbatim in `mail_config.body` and `mail_config.subject`. When the target plugin lacks equivalents, emit a `warning: informational` per occurrence.

**Mail config:** `mail` (primary) → `mail_config{}`. `mail_2` (secondary autoreply) → `notifications[{name:"autoreply", active, subject, sender, recipient, body, additional_headers, attachments, use_html, exclude_blank}]`.

**Messages:** preserved 1:1 (CF7 has ~20 message keys, not whitelisted — canonical `messages{}` accepts arbitrary keys).

**Quirks:**
- The form-tag string parser must handle the `*` required modifier, the optional quoted label, and `key:value` options. Regex anchored at `\[(?!\/)(<type>)(<\*?>)\s+(<name>)(.*?)\]` — full implementation in `cf7-get-form-canonical`.
- reCAPTCHA / Turnstile are site-global → not per-form, dropped with `warning: informational`.
- Submissions are NOT persisted by CF7 itself — Flamingo is a separate plugin and out of scope.
- `additional_settings` is a raw newline-separated string; treat as an opaque blob in `meta.cf7_additional_settings` to round-trip lossless.

### WPForms

Field structure is a **PHP array serialized** in postmeta `wpforms`. Each field is `{id, type, label, choices?, …}`.

| WPForms type | Canonical type | Notes |
|---|---|---|
| `text` | `text` | |
| `email` | `email` | |
| `textarea` | `textarea` | |
| `number` | `number` | |
| `number-slider` (Pro) | `number` | Slider UI lost; `warning: informational`. |
| `select` | `select` | `multiple` (Pro) → `cardinality: multi`. |
| `multi-select` (Pro) | `select` | `cardinality: multi`. |
| `checkbox` | `checkbox` | Single checkbox → `cardinality: single`; group → `multi`. |
| `radio` | `radio` | |
| `gdpr-checkbox` | `consent` | |
| `file-upload` (Pro) | `file` | `extensions` → `validation.accepts` (MIME-mapped). `max_size` → `validation.max_size_bytes`. |
| `date-time` | `datetime` (or `date`/`time` based on `format` option) | |
| `phone` | `tel` | |
| `url` | `url` | |
| `name` (composite) | `group` with sub-fields `first` / `middle`? / `last` | `warning: informational` — composite wrapper lost. |
| `address` (composite) | `group` with sub-fields `address1` / `address2` / `city` / `state` / `postal` / `country` | `warning: informational` — composite wrapper lost. |
| `password` | `text` with `format: password` | |
| `rich-text` (Pro) | `textarea` | HTML editor lost; `warning: informational`. |
| `signature` (Pro) | — | `warning: structural`. |
| `rating` (Pro) | — | `warning: structural` — no canonical type. |
| `payment-*` (Pro) | — | `warning: structural` — e-commerce semantics not portable. |
| `divider`, `html`, `pagebreak` | — | `warning: informational` (layout / multi-step). |
| `hidden` | `hidden` | |

**Notifications:** WPForms has an array of notifications natively → maps directly to canonical `notifications[]`. First notification fills `mail_config{}`; remaining go to `notifications[]`.

**Conditional logic:** native array `[{action, logic_type, conditional, conditionals[[...]]}]` → direct mapping on `conditional_rules[]`.

### Gravity Forms

Field structure is a **PHP array serialized** in `wp_gf_form_meta.display_meta`. Fields keyed by integer `id`, mail-tag syntax is `{Label:id}` or `{:id}`.

| Gravity field type | Canonical type | Notes |
|---|---|---|
| `text` | `text` | |
| `email` | `email` | |
| `phone` | `tel` | |
| `website` | `url` | |
| `number` | `number` | `numberFormat: decimal_dot` → `subtype: decimal`. |
| `textarea` | `textarea` | |
| `select` | `select` | |
| `multiselect` | `select` | `cardinality: multi`. |
| `checkbox` | `checkbox` | `cardinality: multi`. |
| `radio` | `radio` | |
| `date` | `date` | |
| `time` | `time` | |
| `fileupload` | `file` | `allowedExtensions` → `validation.accepts`. `maxFileSize` → `validation.max_size_bytes`. `multipleFiles: true` → `cardinality: multi`. |
| `hidden` | `hidden` | |
| `html`, `section`, `page` | — | `warning: informational` for html/section; `warning: structural` for page (multi-step collapsed). |
| `name` (composite) | `group` with `prefix`/`first`/`middle`/`last`/`suffix` sub-fields | `warning: informational`. |
| `address` (composite) | `group` with street1/street2/city/state/zip/country sub-fields | `warning: informational`. |
| `consent` | `consent` | |
| `password` | `text` with `format: password` | |
| `list` | `group` with `cardinality: multi` | **One of the few clean 1:1 mappings** — Gravity's `list` is a native repeating-row construct. |
| `post_*` (post_title, post_content, post_image, post_tags, post_category, post_custom_field) | — | `warning: structural` — post-creation semantics not portable. |
| `product`, `quantity`, `shipping`, `total`, `option` | — | `warning: structural` — e-commerce semantics not portable. |
| `creditcard` | — | `warning: structural`. Never migrate. |

**Notifications:** Gravity stores N notifications natively → `notifications[]` direct mapping.

**Conditional logic:** native `conditionalLogic: {actionType, logicType, rules:[{fieldId, operator, value}]}` per field → `conditional_rules[]`.

**Mail-tag syntax:** `{Field Label:id}` on Gravity → normalize to `[field-name]` in canonical, using the field's `label` slugified to derive `field-name`.

### Fluent Forms

Field structure is **JSON-serialized** in `wp_fluentform_forms.form_fields`. Fields are objects with `{element, attributes, settings}`.

| Fluent type | Canonical type | Notes |
|---|---|---|
| `input_text` | `text` | |
| `input_email` | `email` | |
| `input_url` | `url` | |
| `input_password` | `text` with `format: password` | |
| `input_number` | `number` | |
| `input_tel` | `tel` | |
| `textarea` | `textarea` | |
| `select` | `select` | |
| `select_country` | `select` | `choices[]` pre-populated with country list. |
| `multi_select` | `select` | `cardinality: multi`. |
| `input_checkbox` | `checkbox` | |
| `input_radio` | `radio` | |
| `input_date` | `date` (or `datetime` based on format option) | |
| `input_file` | `file` | |
| `input_image` (Pro) | `file` | `validation.accepts: ["image/*"]`. |
| `terms_and_condition`, `gdpr_agreement` | `consent` | |
| `ratings` (Pro) | — | `warning: structural`. |
| `signature` (Pro) | — | `warning: structural`. |
| `repeater_field` (Pro) | `group` with `cardinality: multi` | Pro-only — gate in Phase 1. |
| `address` (composite) | `group` | `warning: informational`. |
| `name` (composite) | `group` | `warning: informational`. |
| `payment_*` (Pro) | — | `warning: structural`. |
| `welcome_screen`, `custom_html`, `section_break`, `step_start`, `step_end` | — | `warning: informational` for html/section; `warning: structural` for steps (multi-step → single-page). |

**Notifications:** Fluent stores notifications via the "Settings → Email Notifications" surface → `notifications[]` array.

**Conditional logic:** `conditional_logics: {status, type, conditions: [{field, operator, value}]}` per field → `conditional_rules[]`.

### Formidable Forms

Field structure is row-based — each field is a row in `wp_frm_fields` linked to a form via `form_id`. Field types are stored as strings.

| Formidable type | Canonical type | Notes |
|---|---|---|
| `text` | `text` | |
| `email` | `email` | |
| `url` | `url` | |
| `tel` | `tel` | |
| `password` | `text` with `format: password` | |
| `number` | `number` | |
| `phone` | `tel` | |
| `textarea` | `textarea` | |
| `select` | `select` | |
| `checkbox` | `checkbox` | |
| `radio` | `radio` | |
| `date` | `date` | |
| `time` | `time` | |
| `file` | `file` | |
| `image` (Pro) | `file` | `validation.accepts: ["image/*"]`. |
| `divider` (section / page-break) | — | `warning: informational` for section; `warning: structural` for page-break (multi-step → single-page). |
| `html` | — | `warning: informational`. |
| `address` (Pro composite) | `group` | `warning: informational`. |
| `name` (Pro composite) | `group` | `warning: informational`. |
| `lookup` (Pro) | — | `warning: structural` — cross-form lookup not portable. |
| `rich_text` (Pro) | `textarea` | `warning: informational` (editor lost). |
| `signature` (Pro) | — | `warning: structural`. |
| `payment` (Pro) | — | `warning: structural`. |
| `summary` (Pro) | — | `warning: informational` (rendering only). |
| `tags` (Pro) | `checkbox` with `cardinality: multi` (or `select multi`) | `warning: informational`. |

**Notifications:** Formidable calls them "Form Actions". Email actions → `notifications[]`; other action types (post creation, MailChimp, Stripe) → `warning: informational` (third-party / out of scope).

**Conditional logic:** Formidable has structured rules — `conditional_rules[]`.

**Views (Pro):** Formidable Views are display surfaces, not part of the form schema → ignore on migration.

### Ninja Forms

Ninja Forms is **full Novamira-native** in this framework, symmetric with the other five plugins: Novamira Pro drives it through its own `novamira/ninja-forms-*` abilities (the Ninja PHP model layer — `Ninja_Forms()->form()`, the `nf3_*` tables, the `nf_sub` CPT), with no dependency on Ninja's native `ninjaforms/*` abilities.

- `novamira/ninja-forms-check-setup` (Phase 1) — reports version, `min_satisfied`, NF3 storage readiness (`storage_ready`), forms total, available field-type count, capability flags, anti-spam integrations.
- `novamira/ninja-forms-get-form` (Phase 2, raw shape) and `novamira/ninja-forms-get-form-canonical` (Phase 2, canonical) — the canonical reader reads straight from the model layer, maps the Ninja shape onto the 17-type canonical vocabulary, emits `warnings[]`, and rewrites `{field:KEY}` smart tags to `[KEY]`.

For **Phase 5 writes** when Ninja Forms is the **target**, use the Novamira-native write abilities: `novamira/ninja-forms-create-form` (with `fields[]` + `actions[]`), then `ninja-forms-add-field` / `edit-field` for incremental field work, `ninja-forms-create-notification` / `edit-notification` for email notifications, and `ninja-forms-edit-form` for form-level settings.

**Ninja field type → canonical type:**

| Ninja field type | Canonical type | Notes |
|---|---|---|
| `textbox` | `text` | |
| `textarea` | `textarea` | `input_limit` + `input_limit_type: characters` → `validation.max_length`. |
| `email` | `email` | |
| `number` | `number` (subtype `decimal`) | `num_min` / `num_max` / `num_step` → `validation`. |
| `phone` | `tel` | |
| `date` | `date` | |
| `hidden` | `hidden` | `default_value` rewritten through smart-tag normalisation. |
| `submit` | `submit` | |
| `password`, `passwordconfirm` | `text` (format `password`) | Paired-field semantics lost; `warning: informational` on `passwordconfirm`. |
| `checkbox` | `checkbox` (`cardinality: single`) | Ninja single-checkbox is a yes/no toggle, not a group. |
| `terms` | `consent` | Canonical convention: always `required: true`. |
| `listcheckbox` | `checkbox` (`cardinality: multi`) | Choices from `options[{label,value}]`. |
| `listradio` | `radio` (`cardinality: single`) | |
| `listselect` | `select` (`cardinality: single`) | |
| `listmultiselect` | `select` (`cardinality: multi`) | |
| `listcountry`, `liststate`, `listimage`, `listmodifier` | `select` (`cardinality: single`) | Pre-populated choice lists; UI hint dropped (no warning needed — semantically a select). |
| `address`, `address2`, `city`, `zip`, `firstname`, `lastname` | `text` (flat) | Ninja does not nest composite address / name — fields are flat. `warning: informational` so the target plugin's composite widget (if any) can be reconstructed manually. |
| `repeater` | `group` (`cardinality: multi`) | |

**Ninja types skipped from canonical fields[]** (warning entry only):

| Ninja type | Severity | Reason |
|---|---|---|
| `note`, `html`, `hr`, `confirm` | `informational` | Rendering / UI helpers, not data fields. |
| `starrating`, `signature`, `quantity`, `product`, `shipping`, `total`, `creditcard`, `creditcardnumber`, `creditcardcvc`, `creditcardexpiration`, `creditcardfullname`, `creditcardzip` | `structural` | No canonical equivalent — Ninja-specific UI / payment / e-commerce semantics. |
| `hcaptcha`, `recaptcha`, `recaptchav3`, `turnstile`, `spam`, `timedsubmit` | `informational` | Anti-spam — site-global config in Ninja, must be reconfigured on the target. |

**Actions (= notifications):** Ninja stores notifications + redirects + saves + custom integrations under `actions[]`. Only `type: "email"` actions become canonical:
- The first email-action fills `mail_config{}`.
- Remaining email-actions go to `notifications[]` (with `name` derived from each action's `label`).
- All non-email action types (`save`, `successmessage`, `redirect`, `webhook`, third-party CRM / Mailchimp / Stripe integrations) are echoed verbatim to `meta.ninja_forms_non_email_actions` for round-trip + one `warning: informational` per entry.

**Smart-tag normalisation:** Ninja `{field:KEY}` (e.g. `{field:email}`) → canonical `[KEY]` (matches CF7 syntax adopted as canonical). Other Ninja smart tags (`{all_fields_table}`, `{wp:admin_email}`, `{system:date}`, `{user:user_email}`) are preserved verbatim — the target plugin must provide its own equivalents on import.

**Messages mapping:** Ninja keeps user-facing message strings inline in `settings.*` (no dedicated `messages{}` block like CF7). The canonical reader heuristically captures every setting whose key contains `msg` or `message` (e.g. `not_logged_in_msg`, `changeDateErrorMsg`, `validate_required_field`) so module-supplied / user-customised messages survive on round-trip.

**Quirks specific to Ninja Forms:**
- **Native write surface.** Phase 5 (Ninja as target) uses `novamira/ninja-forms-create-form` + `add-field` + `create-notification` (+ `edit-form` for form-level settings). `create-form` auto-appends a submit field, auto-derives + uniquifies field keys (read the returned keys back), and sets the `key` slug column. Notifications are email actions — non-email actions (redirect / save / successmessage / webhook) are written via `novamira/execute-php` (see the `ninja-forms-integration` skill).
- **NF3 storage gate.** `ninja-forms-check-setup` reports `storage_ready`. When it is `false` the NF3 upgrader has not finished — instruct the user to visit the Ninja Forms admin once to complete the install before retrying.
- **Phase 1/2 gate.** Gate the read path on `check-setup.plugin_active` + `min_satisfied` + `storage_ready` — all three true means the model-layer reader and the canonical reader will work. (There is no native-ability count to check; the integration does not use the native `ninjaforms/*` abilities.)
- **Submissions out of scope.** Submitted entries (the `nf_sub` CPT) are NOT part of the form schema migration. Decline politely if the user asks to migrate submissions. Outside the migration flow they can be read or removed via `novamira/ninja-forms-list-submissions` / `get-submission` / `delete-submission`.

## Output schema for each phase

For consistency the agent emits structured output. Suggested shape:

```json
{
  "mode": "plan",
  "source_plugin": "cf7",
  "target_plugin": "wpforms",
  "source_form_id": 142,
  "canonical_schema": { /* the form-model-schema document */ },
  "migration_plan": [
    {
      "step": "create_form",
      "ability": "novamira/wpforms-create-form",
      "payload": { "title": "Contact us", "fields": [/* … */], "notifications": [/* … */] }
    },
    {
      "step": "edit_form_notifications",
      "ability": "novamira/wpforms-edit-form",
      "payload": { "id": "<created form id>", "notifications": [/* extra entries */] }
    }
  ],
  "warnings": [
    { "path": "fields[5].quiz", "reason": "...", "severity": "structural" }
  ],
  "next_action_required": "user_confirmation_to_execute"
}
```

The `next_action_required` field signals the user what response unblocks the pipeline. Use one of this enum (extend only if a genuinely new case appears):

- `user_confirmation_to_execute` — plan is complete and waiting for go-ahead.
- `user_resolution_for_name_collision` — target already has a form with the same title/slug; user must pick skip / rename / merge.
- `user_decision_on_structural_warnings` — one or more `severity: structural` entries in `warnings[]`; user must accept the loss.
- `user_decision_to_proceed_with_pro_only_types` — payload uses Pro-only field types but the target plugin is Lite.
- `none` — pipeline complete; verification summary present.

**Mode-specific output key inclusion** (do NOT emit absent keys as `null` — leave them out of the JSON entirely):

| Mode | `canonical_schema` | `migration_plan` | `verification_summary` |
|---|---|---|---|
| `audit-only` | ✓ | omit | omit |
| `plan` | ✓ | ✓ | omit |
| `execute` | ✓ | ✓ | ✓ (emitted after Phase 6 runs) |
| `verify` | ✓ (the target's re-translated canonical) | omit | ✓ |

For `audit-only`, `next_action_required` should be `none` if there are no actionable warnings or unresolved decisions, or one of the `user_decision_*` enum values if structural warnings or Pro-only types require user input before any follow-up `plan` / `execute` would proceed.

## Things to NOT do

- Don't promise lossless migration. Even within the same family (e.g. CF7 → CF7 across two sites) round-trip can drift on reserved-name renames and locale-default message expansion.
- Don't translate builder bindings (Elementor form widget references, Bricks contact-form binding) — they reference the source form's numeric ID and will break. Flag for re-binding instead.
- Don't combine schema migration and submission migration in one pass.
- Don't run the pipeline against a production site without `audit-only` first. If the user has only one environment, encourage them to dry-run on a clone.
- Don't market or describe this as "one-click universal import/export". Use "AI-assisted form schema migration" — it sets the right expectation.

## Things that are safe and encouraged

- Run `audit-only` first on every new migration request. Cheap, informative, surfaces all warnings before any commitment.
- Snapshot the canonical schema at the start of execute mode (save to a file the user can re-open) — it's the only authoritative record of what was migrated.
- For multi-form sites, loop the pipeline per form rather than batching — each gets its own audit/plan/confirm cycle, and a failure on one form doesn't taint the others.
- Recommend the source form stay live for a transition window — the user can validate the target side against the source before deleting anything.
- After migration, run a manual front-end submission on the target form to confirm the mail config actually delivers — the schema can be 100% correct and a typo in `mail_config.recipient` still breaks the form.
