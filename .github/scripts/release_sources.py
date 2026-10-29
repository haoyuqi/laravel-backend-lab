"""Collect complete public release sources; never shorten repository text."""

import json
import re
import subprocess


def github_pulls(repository, sha):
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_.-]*/[A-Za-z0-9][A-Za-z0-9_.-]*', repository) or not re.fullmatch(r'[0-9a-f]{40}', sha):
        raise ValueError('Invalid repository or commit')
    pages = json.loads(subprocess.check_output([
        'gh', 'api', f'repos/{repository}/commits/{sha}/pulls?per_page=100',
        '--paginate', '--slurp',
    ], stderr=subprocess.DEVNULL, timeout=60))
    if not isinstance(pages, list) or any(not isinstance(page, list) for page in pages):
        raise ValueError('Invalid PR pages')
    rows = [row for page in pages for row in page]
    for row in rows:
        if (not isinstance(row, dict) or type(row.get('number')) is not int
                or row['number'] <= 0 or not isinstance(row.get('title'), str)
                or not isinstance(row.get('body'), (str, type(None)))
                or not isinstance(row.get('base'), dict)
                or not isinstance(row['base'].get('repo'), dict)
                or not isinstance(row['base']['repo'].get('full_name'), str)
                or 'merged_at' not in row or 'merge_commit_sha' not in row
                or not isinstance(row['merged_at'], (str, type(None)))
                or not isinstance(row['merge_commit_sha'], (str, type(None)))):
            raise ValueError('Invalid PR record')
    return rows


def collect_sources(revision, repository, run_git):
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_.-]*/[A-Za-z0-9][A-Za-z0-9_.-]*', repository):
        raise ValueError('Invalid repository')
    commits = run_git('log', revision, '--reverse', '--format=%H').splitlines()
    commit_set = set(commits)
    result, by_number = [], {}
    for sha in commits:
        if not re.fullmatch(r'[0-9a-f]{40}', sha):
            raise ValueError('Invalid history')
        matched = False
        for pr in github_pulls(repository, sha):
            if (not pr['merged_at'] or pr['base']['repo']['full_name'] != repository
                    or pr['merge_commit_sha'] not in commit_set):
                continue
            matched = True
            number = pr['number']
            if number not in by_number:
                row = {'id': f'pr:{number}', 'title': pr['title'], 'body': pr['body'] or '',
                       'url': f'https://github.com/{repository}/pull/{number}', 'commits': []}
                by_number[number] = row
                result.append(row)
            by_number[number]['commits'].append(sha)
        if not matched:
            subject, body = run_git('show', '-s', '--format=%s%x00%b', sha).split('\0', 1)
            result.append({'id': f'commit:{sha}', 'title': subject, 'body': body,
                           'url': f'https://github.com/{repository}/commit/{sha}'})
    if not result:
        raise ValueError('Empty release range')
    return result


def runtime_changes(previous, sha, run_git):
    def requirements(revision):
        manifest = json.loads(run_git('show', f'{revision}:composer.json'))
        if not isinstance(manifest, dict) or not isinstance(manifest.get('require'), dict):
            raise ValueError('Invalid runtime manifest')
        require = manifest['require']
        if any(not isinstance(value, str) or not value.strip() for value in require.values()):
            raise ValueError('Invalid runtime constraint')
        return {name: value for name, value in require.items()
                if name in {'php', 'php-64bit', 'php-ipv6', 'hhvm'} or name.startswith(('ext-', 'lib-'))}
    before = requirements(previous) if previous else {}
    after = requirements(sha)
    return {name: {'before': before.get(name), 'after': after.get(name)}
            for name in sorted(before.keys() | after.keys()) if before.get(name) != after.get(name)}
