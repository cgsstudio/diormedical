<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Cf7;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/cf7-get-tags-schema', [
    'label' => __('Get Contact Form 7 Tag Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the Contact Form 7 form-tag vocabulary: every tag type CF7 accepts in a form template (`text`, `email`, `tel`, `url`, `number`, `date`, `textarea`, `select`, `checkbox`, `radio`, `file`, `acceptance`, `quiz`, `submit`, `hidden`, `response`, plus module tags `recaptcha`), what each one renders, which modifiers it accepts, and a short example. Pass `type` to receive only that single entry. Call this once before composing a form template so cf7-create-form and cf7-edit-form receive valid syntax on the first try. NOTE: the `label` / `description` / `example` strings are English-only (this is a static reference, not a translated catalogue) — translate them on the agent side if needed.',
        domain: 'novamira-pro',
    ),
    'category' => 'cf7',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'type' => [
                'type' => 'string',
                'description' => 'Optional. When provided, return only that single tag entry under `tags`. Pass one of the slugs listed in the full output (text, email, tel, url, number, date, textarea, select, checkbox, radio, file, acceptance, quiz, submit, hidden, response, recaptcha).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'tags' => [
                'type' => 'object',
                'description' => 'Map of tag slug -> {label, category, description, modifiers[], options[], example}. `modifiers` are the trailing characters appended to the tag type (e.g. `*` for required); `options` are the named attributes the tag accepts (e.g. `placeholder`, `maxlength`).',
            ],
            'syntax' => [
                'type' => 'object',
                'description' => 'High-level recap of CF7 form-tag syntax (square-bracket structure, required modifier, id/class options, mail-tag echo).',
            ],
            'error' => [
                'type' => 'string',
                'description' => 'Present when an unknown `type` filter was supplied; lists known tags in the message.',
            ],
        ],
        'required' => ['tags', 'syntax'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Cf7\\cf7_get_tags_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Static reference (stable across the 5.x and 6.x CF7 line we support). To collect a name and an email, use `[text* your-name]` + `[email* your-email]` + `[submit "Send"]`. The trailing `*` after the type marks the field as required. The token after the type is the field name — it is the key the agent will receive in submission data AND the key used to interpolate values into mail bodies via `[your-name]`.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function cf7_get_tags_schema(array $input = []): array
{
    $all = cf7_tags_reference();
    // Defensive coerce: a direct PHP caller bypassing the schema could
    // pass an array / object / NUL byte payload (round 2 audit L2-A4 /
    // L3-A4). `isset($all[$filter])` throws TypeError on PHP 8 when
    // `$filter` is an array, so coerce to string and strip NUL bytes
    // before the array probe.
    /** @var mixed $filter_raw */
    $filter_raw = $input['type'] ?? '';
    $filter = is_string($filter_raw) ? str_replace(search: "\x00", replace: '', subject: $filter_raw) : '';

    if ($filter !== '') {
        if (!isset($all[$filter])) {
            return [
                // Cast to stdClass so the empty `tags` map serialises as a
                // JSON `{}` (matching the declared object schema), not the
                // PHP array default which would emit `[]` and trip the
                // output validator.
                'tags' => new \stdClass(),
                'syntax' => cf7_tags_syntax_recap(),
                // Truncate + strip control chars from the reflected
                // filter so a hostile caller can't smuggle long /
                // ANSI-laden strings into the agent's error context
                // (round 2 audit L2-A4 / L3-A4).
                'error' => sprintf(
                    'Unknown tag type "%s". Known types: %s.',
                    substr(
                        preg_replace(pattern: '/[\x00-\x1F\x7F]+/', replacement: '', subject: $filter) ?? $filter,
                        offset: 0,
                        length: 64,
                    ),
                    implode(', ', array_keys($all)),
                ),
            ];
        }
        return [
            'tags' => [$filter => $all[$filter]],
            'syntax' => cf7_tags_syntax_recap(),
        ];
    }

    return [
        'tags' => $all,
        'syntax' => cf7_tags_syntax_recap(),
    ];
}

/**
 * High-level CF7 form-tag syntax recap. Surfaced as a sibling to the
 * per-tag map so an agent reading the schema for the first time picks
 * up the bracket structure, required-modifier, and mail-tag echo
 * without reading the CF7 manual.
 *
 * @return array<string, string>
 */
function cf7_tags_syntax_recap(): array
{
    return [
        'structure' => '[<type><modifier?> <name> "label"? <options...>] — square brackets, type slug, optional modifier (* for required), then a unique field name, optional default value in quotes, then space-separated options.',
        'required_modifier' => 'Append `*` to the type to mark the field required (e.g. `[email* your-email]`). The plugin emits the configured `validation_error` message when a required field is missing.',
        'id_class' => 'Inject HTML attributes by appending `id:<value>` or `class:<value>` to the option list (e.g. `[text your-name id:fullname class:large]`). Multiple class values: repeat `class:<value>`.',
        'mail_tag_echo' => 'Form tags double as mail-tag references. A field named `your-name` is interpolated into a mail body as `[your-name]`. Special tags `[_serial_number]`, `[_remote_ip]`, `[_user_agent]`, `[_date]`, `[_time]`, `[_url]`, `[_post_title]`, `[_user_email]` are also available without declaring them in the form.',
        'submit' => 'Every form needs exactly one `[submit "<label>"]` tag. Without it the form has no submit button.',
    ];
}

/**
 * Hard-coded reference of the CF7 form-tag vocabulary. Mirrors the tags
 * shipped by `WPCF7_FormTagsManager` in the 5.x and 6.x lines plus the
 * `[recaptcha]` module tag (separate module but bundled in the official
 * download). Stable across the version range we support.
 *
 * Split across three helpers — text-input / choice / action+meta — so
 * each function stays under the linter's Halstead-volume threshold.
 *
 * @return array<string, array<string, mixed>>
 */
function cf7_tags_reference(): array
{
    return array_merge(cf7_tags_text_inputs(), cf7_tags_choice_and_file(), cf7_tags_action_and_meta());
}

/**
 * Free-form text / numeric / date input tags.
 *
 * @return array<string, array<string, mixed>>
 */
function cf7_tags_text_inputs(): array
{
    return [
        'text' => [
            'label' => 'Single-line text',
            'category' => 'text-input',
            'description' => 'Standard single-line `<input type="text">` field. Common for names, subjects, free-form short answers.',
            'modifiers' => ['*'],
            'options' => [
                'id:',
                'class:',
                'placeholder',
                'autocomplete:',
                'minlength:',
                'maxlength:',
                'size:',
                'default:',
            ],
            'example' => '[text* your-name placeholder "Full name"]',
        ],
        'email' => [
            'label' => 'Email address',
            'category' => 'text-input',
            'description' => 'Email field with built-in RFC validation. Use as the canonical "who is sending this?" field — wire it into `mail.sender` as `[your-email]`.',
            'modifiers' => ['*'],
            'options' => ['id:', 'class:', 'placeholder', 'autocomplete:', 'default:'],
            'example' => '[email* your-email]',
        ],
        'tel' => [
            'label' => 'Telephone number',
            'category' => 'text-input',
            'description' => 'Phone-number field. CF7 validates loosely (digits / spaces / `+()-`); use a custom JS validator for stricter rules.',
            'modifiers' => ['*'],
            'options' => ['id:', 'class:', 'placeholder', 'autocomplete:', 'default:'],
            'example' => '[tel your-phone]',
        ],
        'url' => [
            'label' => 'URL',
            'category' => 'text-input',
            'description' => 'URL field with scheme validation.',
            'modifiers' => ['*'],
            'options' => ['id:', 'class:', 'placeholder', 'autocomplete:', 'default:'],
            'example' => '[url your-website]',
        ],
        'number' => [
            'label' => 'Number',
            'category' => 'text-input',
            'description' => 'Numeric input. Use `min:` / `max:` for a server-side bound; CF7 enforces both.',
            'modifiers' => ['*'],
            'options' => ['id:', 'class:', 'placeholder', 'min:', 'max:', 'step:', 'default:'],
            'example' => '[number your-quantity min:1 max:99]',
        ],
        'date' => [
            'label' => 'Date',
            'category' => 'text-input',
            'description' => 'Date picker (`<input type="date">`). Bounds: `min:YYYY-MM-DD`, `max:YYYY-MM-DD`.',
            'modifiers' => ['*'],
            'options' => ['id:', 'class:', 'min:', 'max:', 'default:'],
            'example' => '[date your-appointment min:today]',
        ],
        'textarea' => [
            'label' => 'Multi-line text',
            'category' => 'text-input',
            'description' => 'Multi-line text area. Standard for the "message" body of contact forms.',
            'modifiers' => ['*'],
            'options' => ['id:', 'class:', 'placeholder', 'minlength:', 'maxlength:', 'cols:', 'rows:', 'default:'],
            'example' => '[textarea* your-message rows:8 placeholder "How can we help?"]',
        ],
    ];
}

/**
 * Choice, file-upload, consent, and spam-protection input tags.
 *
 * @return array<string, array<string, mixed>>
 */
function cf7_tags_choice_and_file(): array
{
    return [
        'select' => [
            'label' => 'Drop-down',
            'category' => 'choice',
            'description' => 'Drop-down menu. The first quoted token is the value; subsequent tokens are extra options. `include_blank` adds an empty first row; `multiple` switches to a multi-select.',
            'modifiers' => ['*'],
            'options' => ['id:', 'class:', 'include_blank', 'multiple', 'first_as_label', 'default:'],
            'example' => '[select* your-topic include_blank "Sales" "Support" "Billing"]',
        ],
        'checkbox' => [
            'label' => 'Checkboxes',
            'category' => 'choice',
            'description' => 'Group of checkboxes. Each quoted token after the name is one option. `exclusive` enforces single-pick (like radios). `use_label_element` wraps each input in `<label>`.',
            'modifiers' => ['*'],
            'options' => ['id:', 'class:', 'use_label_element', 'exclusive', 'default:'],
            'example' => '[checkbox newsletter "Subscribe to the weekly digest"]',
        ],
        'radio' => [
            'label' => 'Radio buttons',
            'category' => 'choice',
            'description' => 'Radio button group. Exactly one of the listed options may be selected.',
            'modifiers' => [],
            'options' => ['id:', 'class:', 'use_label_element', 'default:'],
            'example' => '[radio plan "Basic" "Pro" "Enterprise"]',
        ],
        'file' => [
            'label' => 'File upload',
            'category' => 'file',
            'description' => 'File upload field. The uploaded file is attached to the mail when listed in `mail.attachments` as `[your-file]`. `limit:` and `filetypes:` enforce server-side bounds.',
            'modifiers' => ['*'],
            'options' => ['id:', 'class:', 'filetypes:', 'limit:'],
            'example' => '[file your-attachment limit:2mb filetypes:pdf|jpg|png]',
        ],
        'acceptance' => [
            'label' => 'Acceptance checkbox',
            'category' => 'consent',
            'description' => 'GDPR/terms-acceptance checkbox. By default unchecked + required. Pair with `optional` for an opt-in. Submit is blocked until the box is ticked.',
            'modifiers' => [],
            'options' => ['id:', 'class:', 'default:on', 'optional'],
            'example' => '[acceptance accept-terms] I have read and agree to the terms.[/acceptance]',
        ],
        'quiz' => [
            'label' => 'Quiz challenge',
            'category' => 'spam-protection',
            'description' => 'Question/answer spam gate. Each option is `"Question|Answer"`. CF7 picks one at random per render and rejects submissions whose answer does not match (case-insensitive).',
            'modifiers' => [],
            'options' => ['id:', 'class:'],
            'example' => '[quiz captcha "1+1=?|2" "Capital of Italy?|Rome"]',
        ],
    ];
}

/**
 * Action (submit), meta (hidden, response), and module (recaptcha) tags.
 *
 * @return array<string, array<string, mixed>>
 */
function cf7_tags_action_and_meta(): array
{
    return [
        'submit' => [
            'label' => 'Submit button',
            'category' => 'action',
            'description' => 'The submit button. Exactly one per form. The quoted token is the button label.',
            'modifiers' => [],
            'options' => ['id:', 'class:'],
            'example' => '[submit "Send message"]',
        ],
        'hidden' => [
            'label' => 'Hidden field',
            'category' => 'meta',
            'description' => 'Hidden input. Useful for tracking referrers / campaign IDs that the agent wants to surface in mail without showing them to the visitor.',
            'modifiers' => [],
            'options' => ['id:', 'class:', 'default:'],
            'example' => '[hidden campaign-id default:"summer-2026"]',
        ],
        'response' => [
            'label' => 'Response output',
            'category' => 'meta',
            'description' => 'Renders the response message area (validation errors, mail-sent confirmation). CF7 inserts one automatically when missing — declare it explicitly only when you need it in a non-standard position.',
            'modifiers' => [],
            'options' => ['id:', 'class:'],
            'example' => '[response]',
        ],
        'recaptcha' => [
            'label' => 'Google reCAPTCHA',
            'category' => 'spam-protection',
            'description' => 'reCAPTCHA v3 challenge (CF7 5.1+). Requires the Integration module configured with site/secret keys via the CF7 Integration screen — keys are NOT stored on the form itself.',
            'modifiers' => [],
            'options' => ['id:', 'class:', 'action:'],
            'example' => '[recaptcha action:contact_form]',
        ],
    ];
}
