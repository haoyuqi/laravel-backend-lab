#!/usr/bin/env python3
"""Tag and alias helpers for published container images."""

from __future__ import annotations

import argparse
import json
import re
import sys

TAG_PATTERN = re.compile(
    r'^v?(?P<major>0|[1-9]\d*)\.(?P<minor>0|[1-9]\d*)\.(?P<patch>0|[1-9]\d*)$'
)


def parse_version(value: str) -> tuple[int, int, int]:
    match = TAG_PATTERN.match(value)

    if match is None:
        raise ValueError(f'Expected a stable version X.Y.Z, got {value!r}')

    return int(match['major']), int(match['minor']), int(match['patch'])


def version_string(value: str) -> str:
    return '.'.join(str(part) for part in parse_version(value))


def tags_for(tag: str) -> dict[str, object]:
    major, minor, patch = parse_version(tag)
    version = f'{major}.{minor}.{patch}'

    return {
        'version': version,
        'immutable': [version],
        'aliases': [f'{major}.{minor}', str(major), 'latest'],
    }


def is_newer(candidate: str, current: str) -> bool:
    return parse_version(candidate) > parse_version(current)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest='command', required=True)

    tags = commands.add_parser('tags', help='print immutable and alias tags as JSON')
    tags.add_argument('--tag', required=True)

    newer = commands.add_parser('newer', help='exit 0 when candidate is newer than current')
    newer.add_argument('--candidate', required=True)
    newer.add_argument('--current', required=True)

    arguments = parser.parse_args(argv)

    if arguments.command == 'tags':
        print(json.dumps(tags_for(arguments.tag)))

        return 0

    return 0 if is_newer(arguments.candidate, arguments.current) else 1


if __name__ == '__main__':
    try:
        sys.exit(main())
    except ValueError as error:
        print(error, file=sys.stderr)
        sys.exit(2)
