import json
import subprocess
import unittest
from unittest.mock import patch

import release_sources as sources


REPO = 'haoyuqi/laravel-backend-lab'
A, B, C = 'a' * 40, 'b' * 40, 'c' * 40


def pull(number=1, merge=B, body='full description'):
    return {'number': number, 'title': 'fix: behavior', 'body': body,
            'merged_at': '2026-10-29T00:00:00Z', 'merge_commit_sha': merge,
            'base': {'repo': {'full_name': REPO}}}


class SourcesTest(unittest.TestCase):
    def history(self, *args):
        if args[0] == 'log':
            return A + '\n' + B
        return 'fix: behavior\0' + 'complete body ' * 500

    def test_collects_full_pr_once_and_preserves_unmatched_commit(self):
        description = 'complete PR body ' * 1000
        with patch.object(sources, 'github_pulls', side_effect=[[], [pull(body=description)]]):
            result = sources.collect_sources('v1..v2', REPO, self.history)
        self.assertEqual(['commit:' + A, 'pr:1'], [row['id'] for row in result])
        self.assertEqual('complete body ' * 500, result[0]['body'])
        self.assertEqual(description, result[1]['body'])
        self.assertEqual('https://github.com/' + REPO + '/pull/1', result[1]['url'])

    def test_deduplicates_pr_associated_with_multiple_commits(self):
        with patch.object(sources, 'github_pulls', return_value=[pull()]):
            result = sources.collect_sources('range', REPO, self.history)
        self.assertEqual(1, len(result))
        self.assertEqual('pr:1', result[0]['id'])
        self.assertEqual([A, B], result[0]['commits'])

    def test_unmerged_later_and_foreign_prs_remain_direct_commits(self):
        unmerged = pull(); unmerged['merged_at'] = None
        foreign = pull(); foreign['base']['repo']['full_name'] = 'other/repo'
        with patch.object(sources, 'github_pulls', return_value=[unmerged, foreign, pull(merge=C)]):
            result = sources.collect_sources('range', REPO, self.history)
        self.assertEqual(['commit:' + A, 'commit:' + B], [row['id'] for row in result])

    def test_includes_merge_commits_in_history_and_rejects_api_failure(self):
        with patch.object(sources, 'github_pulls', side_effect=ValueError('failure')):
            with self.assertRaises(ValueError):
                sources.collect_sources('range', REPO, self.history)
        calls = []
        def history(*args):
            calls.append(args)
            return self.history(*args)
        with patch.object(sources, 'github_pulls', return_value=[]):
            sources.collect_sources('range', REPO, history)
        self.assertNotIn('--no-merges', calls[0])

    def test_github_api_uses_all_pages_and_discards_stderr(self):
        pages = json.dumps([[pull()], [pull(number=2)]]).encode()
        with patch.object(sources.subprocess, 'check_output', return_value=pages) as run:
            self.assertEqual(2, len(sources.github_pulls(REPO, A)))
        command = run.call_args.args[0]
        self.assertIn('--paginate', command)
        self.assertIn('--slurp', command)
        self.assertEqual(subprocess.DEVNULL, run.call_args.kwargs['stderr'])

    def test_bad_api_response_and_repository_are_rejected(self):
        for raw in [b'{}', b'[{}]', b'[[1]]']:
            with patch.object(sources.subprocess, 'check_output', return_value=raw):
                with self.assertRaises(ValueError):
                    sources.github_pulls(REPO, A)
        with patch.object(sources.subprocess, 'check_output') as run:
            with self.assertRaises(ValueError):
                sources.collect_sources('range', '../unsafe', self.history)
            run.assert_not_called()

    def test_runtime_requirements_include_add_change_and_removal(self):
        before = {'require': {'php': '^8.4', 'ext-old': '*', 'vendor/package': '^1'}}
        after = {'require': {'php': '~8.4.27 || ^8.5.12', 'ext-new': '*', 'vendor/package': '^2'}}
        def history(*args):
            return json.dumps(before if args[-1].startswith('old:') else after)
        result = sources.runtime_changes('old', 'new', history)
        self.assertEqual({'before': '^8.4', 'after': '~8.4.27 || ^8.5.12'}, result['php'])
        self.assertEqual({'before': '*', 'after': None}, result['ext-old'])
        self.assertEqual({'before': None, 'after': '*'}, result['ext-new'])
        self.assertNotIn('vendor/package', result)

    def test_runtime_manifest_is_required_and_strict(self):
        for raw in ['invalid', '[]', '{"require": []}', '{"require":{"php":8.4}}']:
            with self.subTest(raw=raw), self.assertRaises(ValueError):
                sources.runtime_changes(None, 'new', lambda *args: raw)
        with self.assertRaises(subprocess.CalledProcessError):
            sources.runtime_changes('old', 'new', lambda *args: (_ for _ in ()).throw(subprocess.CalledProcessError(1, 'git')))

    def test_initial_release_and_unchanged_runtime(self):
        manifest = lambda *args: '{"require":{"php":"^8.4"}}'
        self.assertEqual({'php': {'before': None, 'after': '^8.4'}}, sources.runtime_changes(None, 'new', manifest))
        self.assertEqual({}, sources.runtime_changes('old', 'new', manifest))


class GitFixtureTest(unittest.TestCase):
    def test_merge_squash_and_rebased_commits_use_actual_range(self):
        import tempfile
        from pathlib import Path
        with tempfile.TemporaryDirectory() as directory:
            def git(*args):
                return subprocess.check_output(['git', '-C', directory, *args], text=True, stderr=subprocess.DEVNULL).rstrip('\n')
            git('init', '-b', 'master')
            git('config', 'user.name', 'Fixture')
            git('config', 'user.email', 'fixture@example.test')
            git('commit', '--allow-empty', '-m', 'initial')
            git('tag', 'v1.0.0')
            git('checkout', '-b', 'feature')
            Path(directory, 'a').write_text('feature')
            git('add', 'a'); git('commit', '-m', 'feature commit', '-m', 'full commit body')
            feature = git('rev-parse', 'HEAD')
            git('checkout', 'master'); git('merge', '--no-ff', 'feature', '-m', 'Merge PR')
            merge = git('rev-parse', 'HEAD')
            git('commit', '--allow-empty', '-m', 'squashed feature', '-m', 'squash body')
            squash = git('rev-parse', 'HEAD')
            git('commit', '--allow-empty', '-m', 'rebased direct', '-m', 'rebase full body')
            direct = git('rev-parse', 'HEAD')
            def prs(repo, sha):
                if sha in (feature, merge):
                    return [pull(1, merge=merge)]
                if sha == squash:
                    return [pull(2, merge=squash)]
                return []
            with patch.object(sources, 'github_pulls', side_effect=prs):
                rows = sources.collect_sources('v1.0.0..HEAD', REPO, git)
            self.assertEqual(['pr:1', 'pr:2', 'commit:' + direct], [row['id'] for row in rows])
            self.assertEqual([feature, merge], rows[0]['commits'])
            self.assertEqual('rebase full body', rows[2]['body'])
