#!/usr/bin/env bash
# Builds dist/plg_system_headremover-2.0.0.zip reproducibly from src/ and writes the SHA-256
# both next to the archive and into updateserver/extension.xml (Joomla verifies <sha256>).
set -euo pipefail
cd "$(dirname "$0")"
python3 -I - <<'PY'
from pathlib import Path
import hashlib, re, zipfile
root = Path('src')
target = Path('dist/plg_system_headremover-2.0.0.zip')
target.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(target, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for source in sorted(root.rglob('*')):
        if source.is_file() and source.name != '.DS_Store':
            info = zipfile.ZipInfo(source.relative_to(root).as_posix(), (2026, 11, 7, 0, 0, 0))
            info.external_attr = 0o100644 << 16
            info.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(info, source.read_bytes())
checksum = hashlib.sha256(target.read_bytes()).hexdigest()
Path(str(target) + '.sha256').write_text(checksum + '  ' + target.name + '\n')
update = Path('updateserver/extension.xml')
text, count = re.subn(r'<sha256>[0-9a-f]{64}</sha256>', '<sha256>' + checksum + '</sha256>', update.read_text())
if count != 2:
    raise SystemExit('updateserver/extension.xml: expected two <sha256> tags, found %d' % count)
update.write_text(text)
print(checksum, target)
PY
