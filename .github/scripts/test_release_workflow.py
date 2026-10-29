"""Release gating checks, including Actions' skipped-job propagation."""
import unittest
from pathlib import Path


class WorkflowTest(unittest.TestCase):
    def test_notes_waits_for_checks_and_allows_dispatch_preview(self):
        text = Path(__file__).parents[1].joinpath('workflows/release.yml').read_text()
        notes = text.split('  notes:\n', 1)[1].split('  publish:\n', 1)[0]
        self.assertIn('needs: [validate, checks]', notes)
        expression = "!cancelled() && needs.validate.result == 'success' && (github.event_name == 'workflow_dispatch' || needs.checks.result == 'success')"
        self.assertIn('${{ ' + expression + ' }}', notes)
        self.assertIn('pull-requests: read', notes)
        self.assertIn('GH_TOKEN: ${{ github.token }}', notes)
        self.assertNotIn('contents: write', notes)
        cases = [
            (False, 'success', 'success', 'push', True),
            (False, 'success', 'failure', 'push', False),
            (False, 'success', 'skipped', 'push', False),
            (False, 'success', 'cancelled', 'push', False),
            (False, 'failure', 'success', 'push', False),
            (False, 'success', 'skipped', 'workflow_dispatch', True),
            (False, 'failure', 'skipped', 'workflow_dispatch', False),
            (False, 'skipped', 'skipped', 'workflow_dispatch', False),
            (True, 'success', 'success', 'push', False),
            (True, 'success', 'skipped', 'workflow_dispatch', False),
        ]
        for cancelled, validation, checks, event, expected in cases:
            condition = expression.replace('!cancelled()', str(not cancelled)).replace('needs.validate.result', repr(validation)).replace('needs.checks.result', repr(checks)).replace('github.event_name', repr(event)).replace('&&', 'and').replace('||', 'or')
            self.assertEqual(expected, eval(condition, {'__builtins__': {}}, {}))
        publish = text.split('  publish:\n', 1)[1]
        self.assertIn('needs: [validate, checks, notes]', publish)
        self.assertIn("if: github.event_name == 'push'", publish)
