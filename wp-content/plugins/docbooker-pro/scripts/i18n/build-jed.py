#!/usr/bin/env python3
"""
Generate the JED JSON that @wordpress/i18n reads.

A .mo is invisible to JavaScript: wp_set_script_translations() makes WordPress
look for languages/<domain>-<locale>-<handle>.json, and without it every React
string stays English however complete the .mo is.

Only strings that actually come from JS sources are included — the .pot records
where each one was found.
"""
import json, pathlib, re, subprocess, sys

locale = sys.argv[1]
root = pathlib.Path('.')
po = (root / f'languages/doc-booker-pro-{locale}.po').read_text(encoding='utf-8')

# What the JavaScript actually asks for, straight from the sources — the .pot's
# references cannot be trusted here, see scripts/i18n/js-strings.js.
js_ids = set(json.loads((root / 'scripts/i18n/js-strings.json').read_text(encoding='utf-8')))

# Every handle that calls wp_set_script_translations() — the admin and
# front-end screens (ScriptsController, AppPages) and each block script (the
# blocks' bootstrap.php) — with the built file it loads. Each handle's JSON
# holds only the strings found in that file, so a block does not carry the
# whole plugin's text. Run after a build (`npm run dev`/`prod`, blocks too).
HANDLE_FILES = json.loads((root / 'scripts/i18n/handle-files.json').read_text(encoding='utf-8'))

def unquote(raw):
    out = ''.join(re.findall(r'"((?:[^"\\]|\\.)*)"', raw))
    return re.sub(r'\\(.)', lambda m: {'n': '\n', 't': '\t'}.get(m.group(1), m.group(1)), out)

data, js_count = {}, 0
for block in re.split(r'\n\n+', po):
    if 'msgid "' not in block:
        continue
    mid = re.search(r'^msgid ((?:"(?:[^"\\]|\\.)*"\s*)+)', block, re.M)
    key = unquote(mid.group(1))
    if not key or key not in js_ids:
        continue
    plural = re.search(r'^msgid_plural ((?:"(?:[^"\\]|\\.)*"\s*)+)', block, re.M)
    if plural:
        forms = [unquote(m) for m in re.findall(r'^msgstr\[\d\] ((?:"(?:[^"\\]|\\.)*"\s*)+)', block, re.M)]
        if not any(forms):
            continue
        data[key] = forms
    else:
        val = re.search(r'^msgstr ((?:"(?:[^"\\]|\\.)*"\s*)+)', block, re.M)
        translated = unquote(val.group(1)) if val else ''
        if not translated:
            continue
        data[key] = [translated]
    js_count += 1

# Tannin (what @wordpress/i18n runs on) reads `plural_forms` with an underscore
# and takes the locale's own rule from the .po header. A hyphenated key is
# ignored and every locale silently falls back to `n != 1`, which is the wrong
# boundary for French, Portuguese and anything with more than two forms.
header = re.search(r'"Plural-Forms: *(.*?)\\n"', po)
plural_forms = header.group(1).strip() if header else 'nplurals=2; plural=n != 1;'

payload = {
    'translation-revision-date': subprocess.run(
        ['date', '+%Y-%m-%d %H:%M:%S%z'], capture_output=True, text=True).stdout.strip(),
    'generator': 'doc-booker-pro/scripts/i18n/build-jed.py',
    'domain': 'messages',
    'locale_data': {
        'messages': dict(
            [('', {'domain': 'messages', 'lang': locale, 'plural_forms': plural_forms})]
            + sorted(data.items())
        )
    },
}

def in_file(text, key):
    # The built file holds the English as a JS string literal.
    esc = json.dumps(key)[1:-1]
    return key in text or esc in text or esc.replace("'", "\\'") in text

written, sizes = [], []
for handle, rel in HANDLE_FILES.items():
    src = root / rel
    if not src.exists():
        print(f'  missing build file for {handle}: {rel} (build first)')
        continue
    text = src.read_text(encoding='utf-8', errors='ignore')
    msgs = payload['locale_data']['messages']
    sub = {k: v for k, v in msgs.items() if k == '' or in_file(text, k)}
    out = dict(payload, locale_data={'messages': sub})
    dest = root / f'languages/doc-booker-pro-{locale}-{handle}.json'
    dest.write_text(json.dumps(out, ensure_ascii=False), encoding='utf-8')
    written.append(dest.name); sizes.append(len(sub) - 1)

print(f'{locale}: {js_count} JS strings → {len(written)} handle files ({min(sizes)}–{max(sizes)} strings each)')
