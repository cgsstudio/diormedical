#!/usr/bin/env python3
"""Dump the .pot's msgids as JSON chunks a translator can work through."""
import json, re, sys, pathlib

pot = pathlib.Path('languages/doc-booker-pro.pot').read_text(encoding='utf-8')
blocks = re.split(r'\n\n+', pot)

def unquote(raw):
    parts = re.findall(r'"((?:[^"\\]|\\.)*)"', raw)
    # Not unicode_escape: it decodes bytes as Latin-1 and turns "–" into "â".
    out = ''.join(parts)
    out = re.sub(r'\\(.)', lambda m: {'n': '\n', 't': '\t'}.get(m.group(1), m.group(1)), out)
    return out

entries = []
for b in blocks:
    if 'msgid "' not in b:
        continue
    ctx = re.search(r'^msgctxt ((?:"(?:[^"\\]|\\.)*"\s*)+)', b, re.M)
    mid = re.search(r'^msgid ((?:"(?:[^"\\]|\\.)*"\s*)+)', b, re.M)
    plu = re.search(r'^msgid_plural ((?:"(?:[^"\\]|\\.)*"\s*)+)', b, re.M)
    if not mid:
        continue
    s = unquote(mid.group(1))
    if s == '':
        continue  # header
    e = {'id': s}
    if ctx:
        e['ctx'] = unquote(ctx.group(1))
    if plu:
        e['plural'] = unquote(plu.group(1))
    entries.append(e)

# Stable order, shortest first: the short UI words are the bulk and go quickly.
entries.sort(key=lambda e: (len(e['id']), e['id']))
size = int(sys.argv[1]) if len(sys.argv) > 1 else 120
out = pathlib.Path('scripts/i18n/chunks')
out.mkdir(parents=True, exist_ok=True)
for f in out.glob('*.json'):
    f.unlink()
for i in range(0, len(entries), size):
    chunk = entries[i:i + size]
    (out / f'{i // size:03d}.json').write_text(
        json.dumps(chunk, ensure_ascii=False, indent=1), encoding='utf-8')
print(f'{len(entries)} strings in {(len(entries) + size - 1) // size} chunks of {size}')
