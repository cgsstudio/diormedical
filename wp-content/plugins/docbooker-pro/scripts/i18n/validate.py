#!/usr/bin/env python3
"""
Check one locale's dictionary against the chunks before building:
every string translated, placeholders and HTML tags kept, the right number
of plural forms.

    python3 scripts/i18n/validate.py <locale> <nplurals>
"""
import json, pathlib, re, sys
from collections import Counter

locale = sys.argv[1]
nplurals = int(sys.argv[2]) if len(sys.argv) > 2 else 2
root = pathlib.Path('scripts/i18n')

entries = []
for f in sorted((root / 'chunks').glob('*.json')):
    entries += [(f.name, e) for e in json.loads(f.read_text(encoding='utf-8'))]

table = {}
for f in sorted((root / locale).glob('*.json')):
    table.update(json.loads(f.read_text(encoding='utf-8')))

# %s, %1$s, %d, %2$d, %%, {{name}}, <tag …>, </tag>, <tag/>
TOKEN = re.compile(r'%(?:\d+\$)?[sdfu]|%%|\{\{\s*\w+\s*\}\}|</?[a-zA-Z][^<>]*>')

def tokens(s):
    # Tag attributes may be translated (title, aria-label); compare tag names only.
    out = []
    for t in TOKEN.findall(s):
        if t.startswith('<'):
            m = re.match(r'</?\s*([a-zA-Z0-9]+)', t)
            out.append(('/' if t.startswith('</') else '') + m.group(1).lower())
        else:
            out.append(t.replace(' ', ''))
    return Counter(out)

problems = []
done = 0
for chunk, e in entries:
    key = e['id']
    val = table.get(key)
    if val is None or val == '' or val == []:
        problems.append(f'{chunk} MISSING: {key!r}')
        continue
    sources = [key, e.get('plural', key)] if 'plural' in e else [key]
    if 'plural' in e:
        if not isinstance(val, list) or len(val) != nplurals or not all(isinstance(x, str) and x for x in val):
            problems.append(f'{chunk} PLURAL needs {nplurals} non-empty forms: {key!r} → {val!r}')
            continue
        forms = val
    else:
        if not isinstance(val, str):
            problems.append(f'{chunk} NOT A STRING: {key!r} → {val!r}')
            continue
        forms = [val]
    want = tokens(sources[-1]) if 'plural' in e else tokens(key)
    for i, f in enumerate(forms):
        got = tokens(f)
        # Any plural form but the last may say its number in words and drop
        # the count ("one booking", Arabic "two bookings"); nothing else may differ.
        if 'plural' in e and i < len(forms) - 1:
            ok = got == want or any(got == want - Counter({t: 1}) for t in want if re.fullmatch(r'%(?:\d+\$)?d', t))
            if not ok:
                problems.append(f'{chunk} TOKENS {dict(want)} vs {dict(got)}: {key!r} → {f!r}')
        elif got != want:
            problems.append(f'{chunk} TOKENS {dict(want)} vs {dict(got)}: {key!r} → {f!r}')
    done += 1

extra = set(table) - {e['id'] for _, e in entries}
print(f'{locale}: {done}/{len(entries)} OK, {len(problems)} problems, {len(extra)} keys not in the .pot')
for p in problems[:60]:
    print('  ' + p)
if len(problems) > 60:
    print(f'  … and {len(problems) - 60} more')
sys.exit(1 if problems else 0)
