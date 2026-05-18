#!/usr/bin/env python3
"""Convert tweb's schema.ts (layer 222 JSON) → MadelineProto's .tl format."""
import json
import re
import sys

if len(sys.argv) != 3:
    print("Usage: schema2tl.py /path/to/schema.ts /path/to/TL_telegram_v224.tl", file=sys.stderr)
    sys.exit(2)

SCHEMA_TS = sys.argv[1]
OUT_TL = sys.argv[2]

with open(SCHEMA_TS) as f:
    text = f.read()

# Find the JSON object after 'export default' and match braces
start = text.index('export default ') + len('export default ')
depth = 0
end = start
in_str = False
escape = False
for i in range(start, len(text)):
    ch = text[i]
    if in_str:
        if escape:
            escape = False
        elif ch == '\\':
            escape = True
        elif ch == '"':
            in_str = False
    else:
        if ch == '"':
            in_str = True
        elif ch == '{':
            depth += 1
        elif ch == '}':
            depth -= 1
            if depth == 0:
                end = i + 1
                break

json_text = text[start:end]
data = json.loads(json_text)

def hex_id(signed):
    return format(signed & 0xFFFFFFFF, '08x')

def param_str(p):
    return f"{p['name']}:{p['type']}"

def fmt_ctor(c):
    name = c.get('predicate') or c['method']
    h = hex_id(c['id'])
    if name == 'vector':
        return "vector#1cb5c415 {t:Type} # [ t ] = Vector t;"
    params = ' '.join(param_str(p) for p in c.get('params', []))
    typ = c['type']
    if params:
        return f"{name}#{h} {params} = {typ};"
    return f"{name}#{h} = {typ};"

lines = []
# Skip MTProto.* — those live in TL_mtproto_v1.tl (uses pq:string not pq:bytes etc).
# We only override the API schema (layer 222).
for c in data['API']['constructors']:
    lines.append(fmt_ctor(c))
lines.append('---functions---')
for m in data['API']['methods']:
    lines.append(fmt_ctor(m))

with open(OUT_TL, 'w') as f:
    f.write('\n'.join(lines) + '\n')

print(f"Wrote {len(lines)} lines to {OUT_TL}")
print(f"Layer in schema: {data.get('layer', 'unknown')}")
print(f"Constructors: MTProto={len(data['MTProto']['constructors'])}, API={len(data['API']['constructors'])}")
print(f"Methods: MTProto={len(data['MTProto']['methods'])}, API={len(data['API']['methods'])}")
