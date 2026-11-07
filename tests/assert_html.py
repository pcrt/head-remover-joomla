from html.parser import HTMLParser
from pathlib import Path
import re, sys

class Head(HTMLParser):
    def __init__(self):
        super().__init__()
        self.inside = False
        self.scripts, self.styles, self.metas = [], [], []
    def handle_starttag(self, tag, attrs):
        if tag == 'head': self.inside = True
        if not self.inside: return
        a = dict(attrs)
        if tag == 'script': self.scripts.append(a)
        if tag == 'link' and a.get('rel') == 'stylesheet': self.styles.append(a)
        if tag == 'meta': self.metas.append(a)
    def handle_endtag(self, tag):
        if tag == 'head': self.inside = False

baseline, enabled, backend = [Path(p).read_text() for p in sys.argv[1:4]]
b, e, a = Head(), Head(), Head()
for parser, text in [(b, baseline), (e, enabled), (a, backend)]: parser.feed(text)
assert any(m.get('name') == 'generator' for m in b.metas), 'baseline lacks generator'
assert any(m.get('name') == 'author' for m in b.metas), 'baseline lacks author fixture'
for marker in ['headremover-remove-legacy.js', 'headremover-remove-legacy.css', 'headremover-remove-wam.js', 'headremover-remove-wam.css', 'headremover-base.js', 'headremover-dependant.js']:
    assert marker in baseline, 'fixture absent: ' + marker
    assert marker not in enabled, 'removal failed: ' + marker
assert not any(m.get('name', '').lower() in ['generator', 'author'] for m in e.metas)
assert 'headremover-keep-legacy.js' in enabled and 'headremover-keep-wam.css' in enabled
scripts = [s for s in e.scripts if 'headremover-added' in s.get('src', '')]
assert len(scripts) == 2 and 'first.js' in scripts[0]['src'] and 'second.js' in scripts[1]['src']
assert 'defer' in scripts[0] and scripts[1].get('type') == 'module'
styles = [s for s in e.styles if 'headremover-added' in s.get('href', '')]
assert len(styles) == 2 and styles[0]['media'] == 'screen' and styles[1]['media'] == 'print'
assert 'first.css' in styles[0]['href'] and 'second.css' in styles[1]['href']
meta = [m for m in e.metas if any('headremover' in str(v) or v == 'hr-escape' for v in m.values())]
assert [m.get('name') or m.get('property') or m.get('http-equiv') for m in meta] == ['headremover-test', 'og:headremover', 'x-headremover', 'hr-escape']
assert meta[0]['content'] == 'test-ok' and meta[1]['content'] == 'property-ok' and meta[2]['content'] == 'equiv-ok'
assert meta[3]['content'] == '\"><script>alert(1)</script>'
assert not any(s.get('src', '').startswith('javascript:') for s in e.scripts)
match = re.search(r'<head\b[^>]*>\s*<!-- (.*?) -->', enabled, re.S | re.I)
assert match and 'HEADREMOVER-MOTD' in match[1] and '-->' not in match[1] and '<script' not in match[1]
assert 'headremover-added' not in backend and 'HEADREMOVER-MOTD' not in backend and 'headremover-test' not in backend
assert any(m.get('name') == 'generator' for m in a.metas), 'backend generator should be unchanged'
print('PASS: additions, order, attributes, WAM/Document removals, dependants, metadata, escaping, MOTD and backend exclusion')
