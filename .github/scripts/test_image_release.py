import importlib.util
import json
import subprocess
import sys
import unittest
from pathlib import Path

SCRIPT_PATH = Path(__file__).with_name("image-release.py")
SPEC = importlib.util.spec_from_file_location("image_release", SCRIPT_PATH)
IMAGE_RELEASE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(IMAGE_RELEASE)


class VersionParsingTest(unittest.TestCase):
    def test_parses_stable_tags_with_and_without_prefix(self):
        self.assertEqual((3, 0, 9), IMAGE_RELEASE.parse_version("v3.0.9"))
        self.assertEqual((3, 0, 9), IMAGE_RELEASE.parse_version("3.0.9"))
        self.assertEqual("3.0.9", IMAGE_RELEASE.version_string("v3.0.9"))

    def test_rejects_non_stable_or_partial_versions(self):
        for value in ("3.0", "v3", "v3.0.9-rc.1", "release-3.0.9", ""):
            with self.subTest(value=value):
                with self.assertRaises(ValueError):
                    IMAGE_RELEASE.parse_version(value)


class TagPolicyTest(unittest.TestCase):
    def test_only_full_version_is_immutable(self):
        tags = IMAGE_RELEASE.tags_for("v3.0.9")

        self.assertEqual("3.0.9", tags["version"])
        self.assertEqual(["3.0.9"], tags["immutable"])
        self.assertEqual(["3.0", "3", "latest"], tags["aliases"])

    def test_aliases_follow_the_released_major_and_minor(self):
        self.assertEqual(["4.2", "4", "latest"], IMAGE_RELEASE.tags_for("v4.2.10")["aliases"])


class MonotonicPromotionTest(unittest.TestCase):
    def test_accepts_a_newer_patch(self):
        self.assertTrue(IMAGE_RELEASE.is_newer("3.0.10", "3.0.9"))

    def test_accepts_a_newer_minor_across_digit_boundaries(self):
        self.assertTrue(IMAGE_RELEASE.is_newer("3.1.0", "3.0.9"))
        self.assertTrue(IMAGE_RELEASE.is_newer("10.0.0", "9.9.9"))

    def test_rejects_equal_or_older_candidates(self):
        self.assertFalse(IMAGE_RELEASE.is_newer("3.0.9", "3.0.9"))
        self.assertFalse(IMAGE_RELEASE.is_newer("3.0.8", "3.0.9"))


class CommandLineTest(unittest.TestCase):
    def run_script(self, *arguments):
        return subprocess.run(
            [sys.executable, str(SCRIPT_PATH), *arguments],
            capture_output=True,
            text=True,
        )

    def test_tags_command_prints_json(self):
        result = self.run_script("tags", "--tag", "v3.0.9")

        self.assertEqual(0, result.returncode)
        self.assertEqual(
            {"version": "3.0.9", "immutable": ["3.0.9"], "aliases": ["3.0", "3", "latest"]},
            json.loads(result.stdout),
        )

    def test_newer_command_encodes_comparison_in_the_exit_code(self):
        self.assertEqual(0, self.run_script("newer", "--candidate", "3.0.10", "--current", "3.0.9").returncode)
        self.assertEqual(1, self.run_script("newer", "--candidate", "3.0.8", "--current", "3.0.9").returncode)

    def test_invalid_tag_exits_with_configuration_error(self):
        result = self.run_script("tags", "--tag", "latest")

        self.assertEqual(2, result.returncode)
        self.assertIn("Expected a stable version", result.stderr)


if __name__ == "__main__":
    unittest.main()
