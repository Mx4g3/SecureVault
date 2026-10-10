"""Build a shareable source archive without local secrets, database files or sessions."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED

root = Path(__file__).resolve().parent.parent
output = root / 'SecureVault.zip'
items = [root / name for name in ('securevault', 'tools', 'tests', 'docs', 'README.md', 'Start-SecureVault.ps1', 'Stop-SecureVault.ps1', '.gitignore')]
with ZipFile(output, 'w', ZIP_DEFLATED) as archive:
    for item in items:
        for path in sorted(item.rglob('*')) if item.is_dir() else [item]:
            if not path.is_file():
                continue
            relative = path.relative_to(root)
            if path.name in ('local.php', 'mail.local.php') or 'sessions' in relative.parts or '__pycache__' in relative.parts or path.suffix == '.log':
                continue
            archive.write(path, 'SecureVault/' + relative.as_posix())
with ZipFile(output) as archive:
    assert archive.testzip() is None
    assert not any('/local.php' in name or '/mail.local.php' in name or '/sessions/' in name or '/.runtime/' in name for name in archive.namelist())
    print(f'Packaged {len(archive.namelist())} files; verified archive excludes local credentials, sessions and database files.')
