#!/usr/bin/env python3
"""
Build languages/doc-booker-pro-<locale>.po from the .pot plus a JSON dictionary of
translations, then compile the .mo with msgfmt.

Untranslated strings are left empty, which gettext treats as "fall back to the
original" — a half-finished language is never worse than English.
"""
import json, pathlib, re, subprocess, sys

locale = sys.argv[1]
plural_forms = sys.argv[2] if len(sys.argv) > 2 else 'nplurals=2; plural=(n != 1);'
root = pathlib.Path('.')
pot = (root / 'languages/doc-booker-pro.pot').read_text(encoding='utf-8')

table = {}
for f in sorted((root / 'scripts/i18n' / locale).glob('*.json')):
    table.update(json.loads(f.read_text(encoding='utf-8')))

def esc(s):
    return s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n').replace('\t', '\\t')

def unquote(raw):
    out = ''.join(re.findall(r'"((?:[^"\\]|\\.)*)"', raw))
    out = re.sub(r'\\(.)', lambda m: {'n': '\n', 't': '\t'}.get(m.group(1), m.group(1)), out)
    return out

out, hit, miss = [], 0, 0
for block in re.split(r'\n\n+', pot):
    if 'msgid "' not in block:
        out.append(block)
        continue
    mid = re.search(r'^msgid ((?:"(?:[^"\\]|\\.)*"\s*)+)', block, re.M)
    if not mid or unquote(mid.group(1)) == '':
        out.append(block)
        continue
    key = unquote(mid.group(1))
    value = table.get(key)
    plural = re.search(r'^msgid_plural ((?:"(?:[^"\\]|\\.)*"\s*)+)', block, re.M)

    if plural:
        forms = value if isinstance(value, list) else None
        if forms:
            hit += 1
            repl = '\n'.join(f'msgstr[{i}] "{esc(f)}"' for i, f in enumerate(forms))
        else:
            miss += 1
            repl = 'msgstr[0] ""\nmsgstr[1] ""'
        block = re.sub(r'^msgstr\[0\] "".*$', lambda _m: repl, block, flags=re.M | re.S)
    else:
        # The same msgid can appear both with and without a plural; one table
        # holds one value, so a singular entry takes the first form.
        if isinstance(value, list) and value:
            value = value[0]
        if isinstance(value, str) and value:
            hit += 1
            block = re.sub(r'^msgstr ""$', lambda _m: f'msgstr "{esc(value)}"', block, flags=re.M)
        else:
            miss += 1
    out.append(block)

po = '\n\n'.join(out)
# The .pot carries a generic Plural-Forms line; drop it so the locale's own
# rule below is the only one (gettext and WordPress read the last one).
po = re.sub(r'^"Plural-Forms: [^\n]*\n', '', po, count=1, flags=re.M)
po = po.replace('"MIME-Version: 1.0\\n"',
                f'"Language: {locale}\\n"\n"Plural-Forms: {plural_forms}\\n"\n"MIME-Version: 1.0\\n"')
dest = root / f'languages/doc-booker-pro-{locale}.po'
dest.write_text(po, encoding='utf-8')
mo = str(dest).replace('.po', '.mo')
subprocess.run(['msgfmt', '-o', mo, str(dest)], check=True)
total = hit + miss
print(f'{locale}: {hit}/{total} translated ({hit * 100 // total}%) → {dest.name} + {pathlib.Path(mo).name}')
