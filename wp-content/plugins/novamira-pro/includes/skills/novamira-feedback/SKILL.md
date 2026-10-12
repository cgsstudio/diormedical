---
name: novamira-feedback
description: Compose a sanitized English bug report the user can copy and paste to Novamira support, for genuine bugs in the Novamira plugin or its abilities (not for the agent's own input mistakes that a builder correctly rejected). Activate ONLY when the user explicitly asks to send feedback, report a bug, or contact Novamira support. Never auto-activate without an explicit user request — if the agent observes symptoms that look like a Novamira-side bug, or the user sounds frustrated or keeps asking for the same thing because it still is not right, it may briefly mention the option of reporting it and ask the user if they want to, but must wait for the user's go-ahead before invoking this skill. Do not activate for builder validation errors caused by malformed agent input, dissatisfaction with creative choices, or third-party/WordPress-core issues.
---

# Novamira feedback report

Your job is to compose a self-contained bug report **in English** that the user can copy and paste into Novamira support. The user does not know what an "ability" is — they only know that something they asked for did not happen correctly. You have the full conversation context, so you produce the technical part; the user only needs to copy and send.

## When to use this skill

Only for **genuine bugs in Novamira or its abilities** — not for failures caused by the agent itself.

Use this skill **only after the user has explicitly asked** to send feedback, report a bug, or contact Novamira support. The skill never runs unprompted.

If you observe symptoms that look like a Novamira-side bug (ability returned success but site state is wrong, plugin crash, admin screen broken, settings not persisting) but the user has not asked to report anything, you may mention the option in one short sentence — *"This looks like it might be a bug in Novamira itself; want me to prepare a report you can send to support?"* — and **wait for confirmation**. Do not invoke the skill until they say yes.

### Last resort, not first reaction

A report is what you reach for when you have run out of things to try — never on a first failure. Before offering one, you must have actually attempted to solve it: re-read the ability's schema or the relevant `list-*` / `get-*` ability, corrected your input, retried, and looked for an alternative path. A single error message is a thing to debug, not a thing to report.

The bar is **the same failure surviving genuine attempts to fix it** — the same wrong outcome coming back two or three times despite different, correct approaches. Below that bar, keep working.

### Signals that should make you offer

Once that bar is met: users rarely say "this is a bug" — they get frustrated, or they just keep asking. Offer the report (one sentence, then wait) when you notice:

- **The same request repeated.** The user asks for the same outcome two or three times because it still is not right — rephrasing it, insisting, or pointing at the same thing again.
- **You have retried the same operation several times** with corrected input and it still does not produce the expected result.
- **Frustration in their wording** — "again?", "still broken", "it's not working", "I already told you", resignation or irritation about the same task.
- **A workaround became necessary** — you could only get there by bypassing the intended ability (raw PHP, manual steps, a different builder path) because the normal one would not behave.

**Run the self-check below before offering.** Repetition is at least as often the agent's own fault as Novamira's — wrong input, wrong assumption about the builder, misread schema. If the loop is yours, do not offer a report: say plainly what you got wrong and retry correctly. Offering to report your own mistake pushes the user to send support a ticket about nothing, and hides the fix from them.

Offer **once**. If the user declines or ignores it, keep working — do not raise it again for the same problem.

Do **not** use this skill when:

- The agent passed invalid or malformed input and the builder (Bricks, Elementor, WordPress core) correctly rejected it. That is an agent mistake, not a Novamira bug — fix your input and retry instead of reporting.
- Validation errors from a builder's own schema (unsupported element type, missing required field, wrong setting key). Those mean the agent should consult the relevant `list-elements` / `list-settings` ability and correct itself.
- The failure occurred inside `novamira/execute-php` or any other ability whose payload is arbitrary code written by the agent. Bugs there are in the agent's own code, not in Novamira — never report these.
- The user is unhappy with a creative choice (layout, copy, color) — that is feedback on the agent's design decisions, not a bug.
- The problem is in WordPress core, the theme, or a third-party plugin you only observed but did not cause.
- The failing ability is **not a Novamira ability**. Only abilities in the `novamira/` namespace, registered by Novamira or Novamira Pro, belong in a bug report. A bug in an ability registered by another plugin or exposed through another MCP server is that vendor's problem, not Novamira's — tell the user plainly and point them at that vendor. Check the slug: `novamira/…` is in scope, anything else is not.

  The slug decides it on its own — including for our wrappers around third-party plugins, which carry the `novamira/` prefix like any other (`novamira/bricks-set-content`, `novamira/acf-edit-field-group`). Do not downgrade one of those to "third-party issue" just because Bricks or ACF is involved: the failing code is ours.

## Suggestions about other plugins' abilities

Another plugin's abilities may still be mentioned — but **only as a suggestion, never as a bug**. Legitimate examples: the user thinks Novamira should cover a plugin that currently has no Novamira abilities, or that a Novamira ability would do a job better than the third-party one the agent had to fall back on, or that two surfaces overlap confusingly.

When that is the whole of the feedback, do not use the bug-report structure. Emit a short report instead, in English, in a fenced code block:

```
Novamira suggestion

What I was trying to do:
<one or two sentences, private data redacted>

Suggestion:
<what the user would like Novamira to cover or do differently, and why the current path was awkward>
```

The same privacy rules and the same delivery steps apply. If the user frames a third-party ability's failure as a bug, do not convert it into a Novamira bug report — say it is not Novamira's code, and offer to turn it into a suggestion only if the user actually wants Novamira to cover that ground itself.

**Self-check before activating:** ask yourself "if a human had run this same operation manually with the same intent, would the bug still appear?" If no — it is the agent's fault. If yes — it is a real bug worth reporting.

## When the user asks for a report but the cause is the agent

If the user explicitly asks to send feedback / report a bug, but your honest assessment is that the failure was caused by **your own mistake** (malformed input, wrong PHP in `execute-php`, ignoring schema, wrong assumption about the builder), do **not** produce a Novamira bug report. Instead, reply to the user — in the language they are using in the conversation, in plain non-technical words — and:

1. Tell them clearly that this specific issue is not something to report to Novamira, and explain why simply, in one or two sentences. The user does not know what an ability, a schema, or `execute-php` is — translate. **Always answer in the user's own language** (Italian if they wrote in Italian, etc.). The English example below shows the *style and tone* expected, not text to copy verbatim — for `execute-php` failures: *"To do what you asked, I wrote a small piece of custom code and ran it on your site. The code had a mistake I made — it's not something that's broken in the Novamira plugin, it's just a mistake on my side. There's nothing for the Novamira team to fix here."* Adapt the same plain-language style for schema/validation errors, missing required fields, or any other agent-side mistake.
2. Take responsibility plainly. Do not blame the plugin, the builder, or the user.
3. Offer to retry the operation correctly, now that the cause is understood.
4. Mention that if they still want to report something to Novamira (e.g. they think the error message was unclear, or the workflow could be smoother), they can describe it in their own words and you will compose a separate report focused on that — but do not auto-generate one.

Only proceed to the report-composition steps below if the failure genuinely points at Novamira itself.

## Hard rules — no private data

The report must contain **no private or identifying data**. Strip or replace before output:

- Email addresses, phone numbers, personal names → omit entirely.
- Post/page content, titles, excerpts, custom field values → replace with `[redacted]` or describe by type (`a paragraph element with custom text`).
- Specific slugs, post IDs, taxonomy terms, user IDs → replace with placeholders (`<post_id>`, `<slug>`).
- API keys, tokens, license keys, passwords → never include, even partially.
- Full site URL → keep only the bare domain if relevant (`example.com`), drop paths and query strings.
- Screenshots, file contents, database rows → do not include.

When in doubt, leave it out. The report should be reproducible-in-spirit, not data-rich.

## What to include

Produce the report in this exact structure, in English, as plain text inside a fenced code block so the user can copy it cleanly:

```
Novamira feedback report

What I asked the agent to do:
<one or two sentences in plain English describing the user's original goal, with all private data redacted>

What the agent attempted:
<bulleted sequence of the Novamira abilities the agent called, in order, with sanitized intent — e.g. "Created a new page", "Set the Bricks element tree with N sections", "Patched element settings". Do not include raw input payloads.>

What went wrong:
<concise description of the failure: error message returned by the ability, wrong visual outcome, missing effect, etc. Quote error strings verbatim only if they contain no private data.>

Expected vs actual:
- Expected: <what the user wanted>
- Actual: <what happened instead>

Environment:
- Builder: <Bricks | Elementor | other, with version>
- Novamira version: <X.Y.Z>
- Novamira Pro version: <X.Y.Z>
- WordPress version: <X.Y.Z>
- PHP version: <X.Y.Z>
- Other plugins involved: <only those touching the failing path, each with version — e.g. "ACF Pro 6.8.0.1, WPML 4.9.2". Omit the line entirely when no third-party plugin is involved.>
- AI agent: <main model name and version, e.g. "Claude Opus 4.7">
- Subagents used: <list each subagent type with its model, e.g. "Explore (Sonnet 4.6), code-reviewer (Opus 4.7)" — omit the line entirely when no subagent was dispatched>

Notes:
<anything else relevant the agent observed, e.g. "the ability returned success but the page rendered empty". Keep it short.>
```

Omit any section you genuinely have no information for — do not invent values. WordPress, PHP, plugin versions, and the locale are listed in the MCP server-instructions block sent at session start (`Installed plugins:` + the `WordPress … — PHP …` header) — prefer reading them from there since they are already in your context. If a value is missing or you want to confirm it, you can also resolve it through an ability call: `novamira/execute-php` for `phpversion()`, `get_bloginfo('version')`, `get_plugins()`, or any other one-line lookup. The agent's own model name comes from the system prompt; subagent models come from the dispatch you performed (the `subagent_type` and any explicit `model` override).

## How to deliver it to the user

1. Print one short line above the code block, in the user's language, telling them what to do **and asking them to review it before copying** — e.g.: *"Here is a sanitized report for Novamira support. Please review it before copying — make sure nothing private slipped through and the description matches what you experienced."*
2. Print the report inside a single fenced code block so it copies cleanly.
3. After the code block, print one short line, in the user's language, telling them to submit it themselves at **https://novamira.ai/feedback** — e.g.: *"When you're happy with it, paste it at https://novamira.ai/feedback to send it to the Novamira team."*
4. Print one short line, in the user's language, noting that they can attach any screenshot or screen recording alongside the report when they submit it — those should not go inside the code block.
5. End with one short line offering to adjust the report if they want more or less detail.

Do not call any ability to "send" the report, and do not open or fetch the feedback URL yourself. There is no submission endpoint the agent can use — the user copies the report and submits it themselves at https://novamira.ai/feedback.

## Verification before output

Before showing the report, re-read it and confirm:

- No email, name, phone number, token, key, or password appears anywhere.
- No post content, title, or excerpt text appears verbatim.
- The site is referenced at most by bare domain.
- The text is in English regardless of the conversation language.

If any check fails, redact and re-emit.
