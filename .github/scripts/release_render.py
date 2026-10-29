"""Validate one bilingual model response and render program-owned Markdown."""

import json
import re

CATEGORIES = {
    'Features': '新增功能', 'Fixes': '问题修复', 'Security': '安全更新',
    'Compatibility': '兼容性', 'Testing and CI': '测试与持续集成',
    'Documentation and Maintenance': '文档与维护',
}


def render_notes(content, sources, requirements=None):
    data = json.loads(content)
    if not isinstance(data, dict) or set(data) != {'entries'} or not isinstance(data['entries'], list) or not data['entries']:
        raise ValueError('Invalid notes schema')
    known = {source['id']: source for source in sources}
    covered = set()
    for entry in data['entries']:
        if not isinstance(entry, dict) or set(entry) != {'category', 'sources', 'en', 'zh'}:
            raise ValueError('Invalid entry')
        if not isinstance(entry['category'], str) or entry['category'] not in CATEGORIES:
            raise ValueError('Invalid category')
        ids = entry['sources']
        if not isinstance(ids, list) or not ids or any(not isinstance(item, str) or item not in known for item in ids):
            raise ValueError('Invalid source references')
        if len(ids) != len(set(ids)):
            raise ValueError('Duplicate source references')
        covered.update(ids)
        for language in ('en', 'zh'):
            text = entry[language]
            if (not isinstance(text, str) or not text.strip()
                    or any(ord(char) < 32 for char in text)
                    or re.search(r'[<>\[\]]|!\[|^\s*[#>-]|```|https?://', text)):
                raise ValueError('Invalid entry text')
        if not re.search(r'[\u3400-\u9fff]', entry['zh']):
            raise ValueError('Missing Chinese text')
    if covered != set(known):
        raise ValueError('Incomplete source coverage')
    sections = []
    for language in ('en', 'zh'):
        groups = []
        for category, translated in CATEGORIES.items():
            bullets = []
            for entry in data['entries']:
                if entry['category'] != category:
                    continue
                links = []
                for source_id in entry['sources']:
                    source = known[source_id]
                    label = '#' + source_id[3:] if source_id.startswith('pr:') else source_id[7:14]
                    links.append(f'[{label}]({source["url"]})')
                bullets.append('- ' + entry[language].strip() + ' (' + ', '.join(links) + ')')
            if category == 'Compatibility':
                for name, change in (requirements or {}).items():
                    before, after = change['before'], change['after']
                    if after is None:
                        text = f'`{name}` is no longer required (previously `{before}`).' if language == 'en' else f'不再要求 `{name}`（原要求为 `{before}`）。'
                    elif before is None:
                        text = f'`{name}` now requires `{after}`.' if language == 'en' else f'新增 `{name}` 要求：`{after}`。'
                    else:
                        text = f'`{name}` requirement changed from `{before}` to `{after}`.' if language == 'en' else f'`{name}` 要求由 `{before}` 调整为 `{after}`。'
                    bullets.append('- ' + text)
            if bullets:
                groups.append('### ' + (category if language == 'en' else translated) + '\n\n' + '\n'.join(bullets))
        sections.append('\n\n'.join(groups))
    return '\n\n<!-- lang:zh -->\n\n'.join(sections) + '\n'
