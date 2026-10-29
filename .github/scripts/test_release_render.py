import json
import unittest

import release_render as render


class RenderTest(unittest.TestCase):
    def setUp(self):
        self.sources = [{'id': 'pr:1', 'url': 'https://github.com/owner/repo/pull/1'}]
        self.entry = {'category': 'Fixes', 'sources': ['pr:1'], 'en': 'Fix stale requests.', 'zh': '修复旧请求覆盖新结果的问题。'}

    def result(self, entry=None, **kwargs):
        return render.render_notes(json.dumps({'entries': [entry or self.entry]}, ensure_ascii=False), self.sources, **kwargs)

    def test_renders_bilingual_notes_and_program_owned_sources(self):
        result = self.result()
        self.assertIn('### Fixes\n\n- Fix stale requests.', result)
        self.assertIn('<!-- lang:zh -->\n\n### 问题修复', result)
        self.assertEqual(2, result.count('[#1](https://github.com/owner/repo/pull/1)'))

    def test_missing_unknown_and_uncovered_sources_fail(self):
        for ids in [[], ['pr:2']]:
            with self.subTest(ids=ids), self.assertRaises(ValueError):
                self.result({**self.entry, 'sources': ids})
        with self.assertRaises(ValueError):
            render.render_notes(json.dumps({'entries': [self.entry]}), self.sources + [{'id': 'pr:2', 'url': 'url'}])

    def test_empty_one_language_wrong_type_and_unknown_category_fail(self):
        for changed in [{'zh': ''}, {'en': ' '}, {'en': []}, {'sources': 'pr:1'}, {'category': 'Unknown'}, {'zh': 'only English'}]:
            with self.subTest(changed=changed), self.assertRaises(ValueError):
                self.result({**self.entry, **changed})

    def test_invalid_json_fenced_json_empty_and_wrong_shape_fail(self):
        for content in ['bad', '```json\n{}\n```', '{}', '[]', '{"entries":[]}', '{"entries":[1]}']:
            with self.subTest(content=content), self.assertRaises(ValueError):
                render.render_notes(content, self.sources)

    def test_injected_markdown_links_separator_and_html_are_rejected(self):
        for text in ['text\n### New heading', '<!-- lang:zh -->', '[link](https://evil.test)', '<img src=x>', '![image](x)']:
            with self.subTest(text=text), self.assertRaises(ValueError):
                self.result({**self.entry, 'en': text})

    def test_exact_runtime_constraint_and_removal_rendered_in_both_languages(self):
        changes = {'php': {'before': '^8.4', 'after': '~8.4.27 || ^8.5.12'}, 'ext-old': {'before': '*', 'after': None}}
        result = self.result(requirements=changes)
        self.assertEqual(2, result.count('`~8.4.27 || ^8.5.12`'))
        self.assertIn('no longer required', result)
        self.assertIn('不再要求', result)

    def test_source_can_be_combined_without_omission(self):
        entry = {**self.entry, 'sources': ['pr:1', 'commit:' + 'a' * 40]}
        self.sources.append({'id': 'commit:' + 'a' * 40, 'url': 'https://github.com/owner/repo/commit/' + 'a' * 40})
        self.assertIn('[aaaaaaa]', self.result(entry))
