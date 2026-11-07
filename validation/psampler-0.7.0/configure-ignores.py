"""Ignore disposable nested Git copies without editing dependency sources."""
from pathlib import Path


def configure_ignores():
    build = Path(__file__).resolve().parent / 'php-src'
    rule = '\n# Disposable isolated PHP build; managed by configure-ignores.py\n/*\n'
    for metadata in sorted(build.rglob('.git')):
        # prepare.py copies repositories, including their private Git metadata.
        # Never follow a gitdir file or symlink to metadata outside this build.
        if metadata.is_symlink() or not metadata.is_dir():
            continue
        metadata.resolve().relative_to(build.resolve())
        exclude = metadata / 'info' / 'exclude'
        exclude.parent.mkdir(exist_ok=True)
        existing = exclude.read_text() if exclude.exists() else ''
        if rule not in existing:
            with exclude.open('a') as stream:
                stream.write(rule)
        print(f'Configured local build exclusions: {metadata.parent}')


if __name__ == '__main__':
    configure_ignores()
