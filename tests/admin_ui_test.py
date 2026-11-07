"""Backend check for the Docker test: logs in, lists the plugin, opens its edit form and saves parameters
through the real form. Base URL, credentials and extension id come from HR_BASE, HR_USER, HR_PASS, HR_EXT."""
import html, http.cookiejar, os, re, urllib.parse, urllib.request
from html.parser import HTMLParser

base = os.environ['HR_BASE'].rstrip('/')
user, password, ext_id = os.environ['HR_USER'], os.environ['HR_PASS'], os.environ['HR_EXT']
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
opener.addheaders = [('User-Agent', 'headremover-admin-test')]


def get(url):
    with opener.open(url, timeout=60) as r:
        return r.read().decode('utf-8', 'replace')


def post(url, fields):
    data = urllib.parse.urlencode(fields, doseq=True).encode()
    with opener.open(urllib.request.Request(url, data=data), timeout=60) as r:
        return r.read().decode('utf-8', 'replace')


def no_php_messages(name, text):
    found = re.findall(r'(?:Deprecated|Warning|Notice|Fatal error|Parse error): .{0,120}', text)
    assert not found, name + ' shows PHP messages: ' + str(found[:3])


class Fields(HTMLParser):
    """Collects the fields of adminForm the way a browser would submit them."""

    def __init__(self):
        super().__init__()
        self.inside, self.fields, self.select, self.textarea = False, [], None, None

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'form' and a.get('name') == 'adminForm':
            self.inside = True
        if not self.inside:
            return
        if tag == 'input' and a.get('name') and a.get('type') not in ('submit', 'button', 'file'):
            if a.get('type') in ('checkbox', 'radio') and 'checked' not in a:
                return
            self.fields.append((a['name'], a.get('value', '')))
        if tag == 'select' and a.get('name'):
            self.select = [a['name'], None, None]
        if tag == 'option' and self.select is not None:
            if self.select[1] is None:
                self.select[1] = a.get('value', '')
            if 'selected' in a:
                self.select[2] = a.get('value', '')
        if tag == 'textarea' and a.get('name'):
            self.textarea = [a['name'], '']

    def handle_data(self, data):
        if self.textarea is not None:
            self.textarea[1] += data

    def handle_endtag(self, tag):
        if tag == 'select' and self.select is not None:
            self.fields.append((self.select[0], self.select[2] if self.select[2] is not None else (self.select[1] or '')))
            self.select = None
        if tag == 'textarea' and self.textarea is not None:
            self.fields.append((self.textarea[0], html.unescape(self.textarea[1])))
            self.textarea = None
        if tag == 'form' and self.inside:
            self.inside = False


# Login
page = get(base + '/administrator/index.php')
token = re.search(r'name="([0-9a-f]{32})" value="1"', page).group(1)
ret = re.search(r'name="return" value="([^"]*)"', page).group(1)
page = post(base + '/administrator/index.php', {'username': user, 'passwd': password, 'option': 'com_login', 'task': 'login', 'return': ret, token: '1'})
assert 'name="passwd"' not in page, 'login failed'
no_php_messages('dashboard', page)

# Plugin list
page = get(base + '/administrator/index.php?option=com_plugins&view=plugins&filter[search]=Head%20Remover&filter[folder]=&filter[enabled]=')
no_php_messages('plugin list', page)
assert 'System - Head Remover' in page, 'plugin name not listed'

# Edit form
page = get(base + '/administrator/index.php?option=com_plugins&task=plugin.edit&extension_id=' + ext_id)
no_php_messages('edit form', page)
assert 'System - Head Remover' in page, 'edit form title'
untranslated = sorted(set(re.findall(r'PLG_SYSTEM_HEADREMOVER_[A-Z_]+', re.sub(r'<(input|select|textarea)[^>]*>', '', page))))
assert not untranslated, 'untranslated strings: ' + str(untranslated)
for label in ['Scope', 'Remove assets', 'Custom assets', 'Metadata and comment', 'Asset names to disable', 'URLs or URL fragments to remove',
              'Scripts in output order', 'Styles in output order', 'Custom metadata in order', 'MOTD comment']:
    assert label in page, 'label missing: ' + label
assert page.count('joomla-field-subform') >= 4, 'subform fields missing'

# Save through the form, including subform rows as the browser would post them
parser = Fields()
parser.feed(page)
fields = [(n, v) for n, v in parser.fields if 'X]' not in n]
names = [n for n, _ in fields]
assert 'jform[params][core_assets]' in names and 'jform[params][motd]' in names, 'expected fields missing: ' + str(names[:40])
override = {
    'task': 'plugin.apply', 'jform[enabled]': '1', 'jform[params][frontend_only]': '1', 'jform[params][exclude_backend]': '1',
    'jform[params][core_assets]': 'bootstrap.*', 'jform[params][remove_generator]': '1', 'jform[params][remove_author]': '0',
    'jform[params][motd]': 'UI-SAVE-TEST -->',
}
fields = [(n, override.pop(n, v)) for n, v in fields] + list(override.items())
fields += [('jform[params][remove_urls][remove_urls0][type]', 'both'), ('jform[params][remove_urls][remove_urls0][url]', 'headremover-remove'),
           ('jform[params][scripts][scripts0][url]', 'media/headremover-added-first.js'), ('jform[params][scripts][scripts0][mode]', 'defer'),
           ('jform[params][styles][styles0][url]', '/media/headremover-added-first.css'), ('jform[params][styles][styles0][media]', 'print'),
           ('jform[params][metas][metas0][kind]', 'property'), ('jform[params][metas][metas0][name]', 'og:ui'), ('jform[params][metas][metas0][content]', 'saved <b>')]
page = post(base + '/administrator/index.php?option=com_plugins&layout=edit&extension_id=' + ext_id, fields)
no_php_messages('after save', page)
messages = ' '.join(re.sub(r'<[^>]+>', ' ', m) for m in re.findall(r'<div class="alert[^"]*"[^>]*>(.*?)</div>', page, re.S))
assert 'Plugin saved' in messages, 'save message not found: ' + re.sub(r'\s+', ' ', messages)[:300]
print('PASS: backend login, plugin list, edit form (tabs, labels, 4 subforms, no untranslated keys) and save through the form')
