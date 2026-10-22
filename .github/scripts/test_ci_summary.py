import importlib.util
import json
import os
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch


SCRIPT_PATH = Path(__file__).with_name("ci-summary.py")
SPEC = importlib.util.spec_from_file_location("ci_summary", SCRIPT_PATH)
CI_SUMMARY = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(CI_SUMMARY)


class CoverageThresholdTest(unittest.TestCase):
    def test_accepts_complete_coverage(self):
        self.assertTrue(CI_SUMMARY.coverage_meets_minimum(20001, 20001, "100"))

    def test_rejects_one_uncovered_line_even_when_percentage_rounds_to_100(self):
        self.assertFalse(CI_SUMMARY.coverage_meets_minimum(20000, 20001, "100"))

    def test_rejects_missing_or_invalid_line_counts(self):
        self.assertFalse(CI_SUMMARY.coverage_meets_minimum(0, 0, "100"))
        self.assertFalse(CI_SUMMARY.coverage_meets_minimum(1, 0, "100"))
        self.assertFalse(CI_SUMMARY.coverage_meets_minimum(11, 10, "100"))
        self.assertFalse(CI_SUMMARY.coverage_meets_minimum(True, True, "100"))

    def test_compares_fractional_threshold_without_rounding(self):
        self.assertTrue(CI_SUMMARY.coverage_meets_minimum(9999, 10000, "99.99"))
        self.assertFalse(CI_SUMMARY.coverage_meets_minimum(9998, 10000, "99.99"))

    def test_collection_and_final_gate_reject_the_same_rounded_shortfall(self):
        with tempfile.TemporaryDirectory() as directory:
            previous_directory = Path.cwd()
            os.chdir(directory)
            Path("ci-data").mkdir()
            Path("ci-data/coverage.xml").write_text(
                '<coverage><project><metrics statements="20001" coveredstatements="20000" />'
                "</project></coverage>"
            )
            Path("ci-data/junit.xml").write_text('<testsuites tests="1" failures="0" errors="0" skipped="0" />')

            try:
                with patch.dict(os.environ, {"MIN_LINE_COVERAGE": "100"}), patch.object(
                    CI_SUMMARY, "installed_version", return_value=None
                ):
                    with self.assertRaises(SystemExit):
                        CI_SUMMARY.collect("sqlite", "success")

                    metrics = json.loads(Path("ci-data/sqlite.json").read_text())
                    self.assertEqual("100.00%", metrics["coverage"])
                    self.assertEqual(20000, metrics["covered_statements"])
                    self.assertEqual(20001, metrics["total_statements"])
                    self.assertEqual("failure", metrics["result"])

                    with self.assertRaises(SystemExit):
                        CI_SUMMARY.verify_coverage()
            finally:
                os.chdir(previous_directory)

    def test_collection_rejects_malformed_raw_clover_counters(self):
        with tempfile.TemporaryDirectory() as directory:
            previous_directory = Path.cwd()
            os.chdir(directory)
            Path("ci-data").mkdir()
            Path("ci-data/coverage.xml").write_text(
                '<coverage><project><metrics statements="1_000" coveredstatements="1000" />'
                "</project></coverage>"
            )

            try:
                with patch.dict(os.environ, {"MIN_LINE_COVERAGE": "100"}):
                    with self.assertRaisesRegex(SystemExit, "ASCII decimal digits only"):
                        CI_SUMMARY.collect("sqlite", "success")
            finally:
                os.chdir(previous_directory)


if __name__ == "__main__":
    unittest.main()
