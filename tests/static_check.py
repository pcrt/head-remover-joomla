"""Validate package structure, languages and reproducible build; no runtime claims."""
from pathlib import Path
import hashlib, json, re, subprocess, xml.etree.ElementTree as ET, zipfile
r = Path(__file__).resolve().parents[1]
src = r/'src'
m = ET.parse(src/'headremover.xml').getroot()
assert m.attrib == {'type':'plugin','version':'5.0','group':'system','method':'upgrade'}
assert m.findtext('version') == '2.0.0'
assert m.find('namespace').text == r'Pcrt\Plugin\System\HeadRemover'
assert m.find('files/folder[@plugin="headremover"]').text == 'services'
assert m.findtext('license') == 'GPL-2.0-or-later'
xmls = list(src.rglob('*.xml'))
keys = set()
for p in xmls:
    ET.parse(p)
    keys.update(re.findall(r'PLG_SYSTEM_HEADREMOVER(?:_[A-Z_]+)?', p.read_text()))
    for sub in ET.parse(p).getroot().iter('field'):
        if sub.get('type') == 'subform':
            assert (src/'forms'/Path(sub.get('formsource')).name).is_file()
for lang in ['en-GB','it-IT']:
    path = src/'language'/lang
    values = {}
    for p in path.glob('*.ini'):
        for line in p.read_text().splitlines():
            if line.startswith(';') or not line.strip(): continue
            assert re.fullmatch(r'PLG_SYSTEM_HEADREMOVER(?:_[A-Z_]+)?="[^"\n]*"', line), (p,line)
            k,v = line.split('=',1)
            if p.name.endswith('.sys.ini'): assert k in {'PLG_SYSTEM_HEADREMOVER','PLG_SYSTEM_HEADREMOVER_XML_DESCRIPTION'}
            values[k] = v
    assert keys <= values.keys(), (lang, keys-values.keys())
    # JED naming convention for plugins: "{Type} - {Extension Name}" (System - Head Remover).
    assert values['PLG_SYSTEM_HEADREMOVER'].endswith(' - Head Remover"'), (lang, values['PLG_SYSTEM_HEADREMOVER'])
for p in src.rglob('*.php'):
    text=p.read_text()
    assert "defined('_JEXEC') or die" in text
    assert not re.search(r'\b(?:JFactory|JPlugin|JURI)\b|\$doc->_scripts', text)
assert 'Version 2, June 1991' in (r/'LICENSE').read_text()
assert (r/'LICENSE').read_bytes() == (src/'LICENSE.txt').read_bytes()
updates = ET.parse(r/'updateserver/extension.xml').getroot()
assert [u.find('targetplatform').get('version') for u in updates] == ['5.*','6.*']
for u in updates:
    assert u.findtext('element') == 'headremover' and u.findtext('php_minimum') == '8.1'
    assert u.findtext('downloads/downloadurl') == 'https://github.com/pcrt/head-remover-joomla/releases/download/v2.0.0/plg_system_headremover-2.0.0.zip'
subprocess.run([str(r/'build.sh')],check=True,cwd=r)
z = r/'dist/plg_system_headremover-2.0.0.zip'
first = z.read_bytes()
with zipfile.ZipFile(z) as archive:
    assert archive.testzip() is None
    assert set(archive.namelist()) == {p.relative_to(src).as_posix() for p in src.rglob('*') if p.is_file()}
    for name in archive.namelist():
        assert archive.read(name) == (src/name).read_bytes()
subprocess.run([str(r/'build.sh')],check=True,cwd=r)
assert first == z.read_bytes(), 'non-reproducible ZIP'
checksum = hashlib.sha256(first).hexdigest()
assert (Path(str(z)+'.sha256')).read_text().split()[0] == checksum
assert re.findall(r'<sha256>([0-9a-f]{64})</sha256>', (r/'updateserver/extension.xml').read_text()) == [checksum, checksum], 'update server checksum'
print('PASS: XML, metadata, translations, subforms, forbidden APIs, license, update server, archive integrity and deterministic build')
