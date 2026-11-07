#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
python3 - <<'PY'
from pathlib import Path
import hashlib, zipfile
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
print(checksum, target)
PY
