"""HTML assertions for the Docker test: `full baseline enabled backend error404`, `same a b` or `uisave page`."""
from html.parser import HTMLParser
from pathlib import Path
import re, sys


class Head(HTMLParser):
    def __init__(self):
        super().__init__()
        self.inside = False
        self.scripts, self.styles, self.metas = [], [], []

    def handle_starttag(self, tag, attrs):
        if tag == 'head':
            self.inside = True
        if not self.inside:
            return
        a = dict(attrs)
        if tag == 'script':
            self.scripts.append(a)
        if tag == 'link' and a.get('rel') == 'stylesheet':
            self.styles.append(a)
        if tag == 'meta':
            self.metas.append(a)

    def handle_endtag(self, tag):
        if tag == 'head':
            self.inside = False


def parse(text):
    head = Head()
    head.feed(text)
    return head


def mask(text):
    """Hide the values Joomla legitimately changes between requests: CSRF tokens and media versions."""
    text = re.sub(r'[0-9a-f]{32}', 'TOKEN', text)
    return re.sub(r'\?[0-9a-f]{6}"', '?MEDIAVERSION"', text)


def no_php_errors(name, text):
    assert not re.search(r'(Deprecated|Warning|Notice|Fatal error|Parse error):', text), name + ' contains PHP messages'


mode = sys.argv[1]

if mode == 'same':
    a, b = [Path(p).read_text() for p in sys.argv[2:4]]
    no_php_errors(sys.argv[3], b)
    assert mask(a) == mask(b), sys.argv[2] + ' and ' + sys.argv[3] + ' differ (tokens and media versions masked)'
    print('PASS: ' + Path(sys.argv[3]).name + ' is identical to ' + Path(sys.argv[2]).name)
    sys.exit(0)

if mode == 'uisave':
    text = Path(sys.argv[2]).read_text()
    no_php_errors(sys.argv[2], text)
    head = parse(text)
    match = re.search(r'<head\b[^>]*>\s*<!-- (.*?) -->', text, re.S | re.I)
    assert match and 'UI-SAVE-TEST' in match[1] and '-->' not in match[1], 'MOTD saved from the form'
    assert not any(m.get('name') == 'generator' for m in head.metas), 'generator still present'
    assert any(m.get('name') == 'author' for m in head.metas), 'author should be kept'
    assert [m for m in head.metas if m.get('property') == 'og:ui' and m.get('content', '').strip() == 'saved'], 'og:ui meta (tags stripped by the form filter)'
    scripts = [s for s in head.scripts if 'headremover-added-first.js' in s.get('src', '')]
    assert len(scripts) == 1 and 'defer' in scripts[0], 'script row saved from the form'
    styles = [s for s in head.styles if 'headremover-added-first.css' in s.get('href', '')]
    assert len(styles) == 1 and styles[0].get('media') == 'print', 'style row saved from the form'
    for marker in ['headremover-remove-', 'vendor/bootstrap/js/collapse', 'vendor/bootstrap/css/bootstrap']:
        assert marker not in text, 'removal from form settings failed: ' + marker
    assert 'headremover-keep-legacy.js' in text, 'unselected asset removed'
    print('PASS: parameters saved through the backend form are applied on the frontend')
    sys.exit(0)

baseline, enabled, backend, error404 = [Path(p).read_text() for p in sys.argv[2:6]]
for name, text in zip(sys.argv[2:6], [baseline, enabled, backend, error404]):
    no_php_errors(name, text)
b, e, a, x = parse(baseline), parse(enabled), parse(backend), parse(error404)

assert any(m.get('name') == 'generator' for m in b.metas), 'baseline lacks generator'
assert any(m.get('name') == 'author' for m in b.metas), 'baseline lacks author fixture'
removed = ['headremover-remove-legacy.js', 'headremover-remove-legacy.css', 'headremover-remove-wam.js',
           'headremover-remove-wam.css', 'headremover-base.js', 'headremover-dependant.js',
           'vendor/bootstrap/js/collapse', 'vendor/bootstrap/css/bootstrap']
for marker in removed:
    assert marker in baseline, 'fixture absent from baseline: ' + marker
    assert marker not in enabled, 'removal failed: ' + marker
assert not any(m.get('name', '').lower() in ['generator', 'author'] for m in e.metas), 'generator/author still present'
for marker in ['headremover-keep-legacy.js', 'headremover-keep-wam.css', 'system/js/core.min.js', 'joomla-script-options']:
    assert marker in enabled, 'unselected asset was removed: ' + marker
assert '//media/' not in enabled, 'protocol-relative media URL in output'

scripts = [s for s in e.scripts if 'headremover-added' in s.get('src', '')]
assert len(scripts) == 2 and 'first.js' in scripts[0]['src'] and 'second.js' in scripts[1]['src'], 'script order'
assert 'defer' in scripts[0] and scripts[1].get('type') == 'module', 'script attributes'
styles = [s for s in e.styles if 'headremover-added' in s.get('href', '')]
assert len(styles) == 2 and styles[0]['media'] == 'screen' and styles[1]['media'] == 'print', 'style attributes'
assert 'first.css' in styles[0]['href'] and 'second.css' in styles[1]['href'], 'style order'
assert not any(s.get('src', '').startswith('javascript:') for s in e.scripts), 'javascript: URL accepted'

meta = [m for m in e.metas if any('headremover' in str(v) or v == 'hr-escape' for v in m.values())]
assert [m.get('name') or m.get('property') or m.get('http-equiv') for m in meta] == ['headremover-test', 'og:headremover', 'x-headremover', 'hr-escape'], 'meta order'
assert meta[0]['content'] == 'test-ok' and meta[1]['content'] == 'property-ok' and meta[2]['content'] == 'equiv-ok'
assert meta[3]['content'] == '"><script>alert(1)</script>', 'meta escaping'

motd = re.compile(r'<head\b[^>]*>\s*<!-- (.*?) -->', re.S | re.I)
match = motd.search(enabled)
assert match and 'HEADREMOVER-MOTD' in match[1] and '-->' not in match[1] and '<script' not in match[1], 'MOTD'

assert 'headremover-added' not in backend and 'HEADREMOVER-MOTD' not in backend and 'headremover-test' not in backend, 'backend not excluded'
assert any(m.get('name') == 'generator' for m in a.metas), 'backend generator should be unchanged'

match = motd.search(error404)
assert match and 'HEADREMOVER-MOTD' in match[1], 'MOTD missing on the error page'
assert not any(m.get('name') == 'generator' for m in x.metas), 'generator still present on the error page'
assert any(m.get('name') == 'headremover-test' for m in x.metas), 'custom meta missing on the error page'
print('PASS: additions, order, attributes, WAM/Document removals, dependants, bootstrap family, metadata, escaping, MOTD, backend exclusion and error page')
