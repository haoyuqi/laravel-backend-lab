"""Collect measured CI data and render one GitHub Actions run summary."""

import json
import os
import subprocess
import sys
import xml.etree.ElementTree as ET
from decimal import Decimal, InvalidOperation
from pathlib import Path


def xml_root(path):
    try:
        return ET.parse(path).getroot()
    except (FileNotFoundError, ET.ParseError):
        return None


def installed_version(code):
    if not Path("vendor/autoload.php").exists():
        return None
    try:
        return subprocess.check_output(["php", "-r", code], text=True).strip()
    except (OSError, subprocess.CalledProcessError):
        return None


def coverage_meets_minimum(covered, total, minimum):
    if type(covered) is not int or type(total) is not int:
        return False
    if total <= 0 or covered < 0 or covered > total:
        return False
    try:
        threshold = Decimal(str(minimum))
    except InvalidOperation:
        return False
    return Decimal(covered) * 100 >= Decimal(total) * threshold


def collect(name, result):
    junit = xml_root("ci-data/junit.xml")
    suites = [junit] if junit is not None and junit.get("tests") else list(junit.findall("./testsuite")) if junit is not None else []
    count = lambda key: sum(int(suite.get(key, "0")) for suite in suites) if suites else None
    clover = xml_root("ci-data/coverage.xml")
    metrics = clover.find("./project/metrics") if clover is not None else None
    raw_statements = metrics.get("statements", "0") if metrics is not None else "0"
    raw_covered = metrics.get("coveredstatements", "0") if metrics is not None else "0"
    if any(not value.isascii() or not value.isdecimal() for value in (raw_statements, raw_covered)):
        raise SystemExit("Clover statement counts must contain ASCII decimal digits only")
    statements = int(raw_statements)
    covered = int(raw_covered)
    coverage = covered / statements * 100 if statements else None
    minimum = os.environ.get("MIN_LINE_COVERAGE") if name == "sqlite" else None
    below_minimum = minimum is not None and not coverage_meets_minimum(covered, statements, minimum)
    data = {
        "name": name,
        "result": "failure" if below_minimum else result,
        "php": installed_version('echo PHP_VERSION;'),
        "laravel": installed_version('require "vendor/autoload.php"; echo Illuminate\\Foundation\\Application::VERSION;'),
        "tests": count("tests"),
        "failures": count("failures") + count("errors") if suites else None,
        "skipped": count("skipped"),
        "covered_statements": covered,
        "total_statements": statements,
        "coverage": f"{coverage:.2f}%" if coverage is not None else None,
    }
    Path("ci-data").mkdir(exist_ok=True)
    Path(f"ci-data/{name}.json").write_text(json.dumps(data))
    if below_minimum:
        raise SystemExit(
            f"SQLite app/ line coverage is {covered}/{statements} executable lines "
            f"({display(data['coverage'])}); minimum is {minimum}%"
        )


def display(value):
    return "Not available" if value is None else str(value)


def verify_coverage():
    minimum = os.environ.get("MIN_LINE_COVERAGE")
    data = json.loads(Path("ci-data/sqlite.json").read_text())
    covered = data.get("covered_statements")
    total = data.get("total_statements")
    if minimum is None or not coverage_meets_minimum(covered, total, minimum):
        raise SystemExit(
            f"SQLite app/ line coverage is {display(covered)}/{display(total)} executable lines; "
            f"minimum is {display(minimum)}%"
        )


def status(value):
    return {"success": "Passed", "failure": "Failed", "cancelled": "Cancelled", "skipped": "Skipped"}.get(value, "Not available")


def render(repo):
    needs = json.loads(Path("ci-needs.json").read_text())
    data = {}
    for path in Path("ci-data").glob("*.json"):
        item = json.loads(path.read_text())
        data[item["name"]] = item

    if repo == "api":
        jobs = [("Code Quality & Lint", "lint"), ("SQLite PHPUnit", "test"),
                ("PostgreSQL 18 persistence and rollback", "postgres-test"),
                ("PostgreSQL 18 production container smoke", "container")]
        suites = [("SQLite", "sqlite"), ("PostgreSQL 18", "postgres-18")]
        version_source = data.get("sqlite", {})
    else:
        jobs = [("Laravel Pint", "pint"), ("Production installation smoke", "production-smoke"),
                ("SQLite tests", "sqlite-tests"), ("Database matrix", "php-tests"),
                ("Security checks", "security-checks"), ("Browser tests", "browser-tests")]
        suites = [("SQLite", "sqlite"), ("MySQL 8.0", "mysql-8.0"),
                  ("MySQL 8.4", "mysql-8.4"), ("PostgreSQL 17", "postgres-17"),
                  ("PostgreSQL 18", "postgres-18")]
        version_source = data.get("sqlite", {})

    failed = [(label, key) for label, key in jobs if needs.get(key, {}).get("result") != "success"]
    sqlite = data.get("sqlite", {})
    total, failures, skipped = sqlite.get("tests"), sqlite.get("failures"), sqlite.get("skipped")
    passed = total - failures - skipped if None not in (total, failures, skipped) else None
    minimum = os.environ.get("MIN_LINE_COVERAGE")
    measured = version_source.get("coverage")
    covered = version_source.get("covered_statements")
    statements = version_source.get("total_statements")
    coverage_result = (
        "Not available"
        if minimum is None or covered is None or statements is None
        else "Passed"
        if coverage_meets_minimum(covered, statements, minimum)
        else "Failed"
    )
    lines = [f"# {'🔴 CI failed' if failed else '🟢 CI passed'}", "",
             "| Tests · SQLite | Line coverage | Checks |", "| :--- | :--- | :--- |",
             f"| **{display(passed)} / {display(total)} passed**<br>{display(skipped)} skipped | **{display(measured)}**<br>`app/` · {display(covered)} / {display(statements)} executable lines · minimum {minimum + '%' if minimum else 'Not available'} · {coverage_result} | **{len(jobs) - len(failed)} / {len(jobs)} passed** |",
             "", f"**Environment:** PHP {display(version_source.get('php'))} · Laravel {display(version_source.get('laravel'))}",
             "", "## Tested databases", "", "| Target | Result | Passed / total | Skipped |", "| --- | --- | ---: | ---: |"]
    for label, key in suites:
        item = data.get(key, {})
        total, failures, skipped = item.get("tests"), item.get("failures"), item.get("skipped")
        passed = total - failures - skipped if None not in (total, failures, skipped) else None
        lines.append(f"| {label} | {status(item.get('result'))} | {display(passed)} / {display(total)} | {display(skipped)} |")
    if failed:
        lines += ["", "## Checks needing attention", "", "| Job | Result |", "| --- | --- |"]
        lines += [f"| {label} | {status(needs.get(key, {}).get('result'))} |" for label, key in failed]
    if repo == "lab":
        lines += ["", "The database matrix lists each reported test run. Browser tests use PostgreSQL 18."]
    lines += ["", f"[Download test and coverage reports]({os.environ.get('GITHUB_SERVER_URL', 'https://github.com')}/{os.environ.get('GITHUB_REPOSITORY', '')}/actions/runs/{os.environ.get('GITHUB_RUN_ID', '')}#artifacts). These are files saved by this CI run."]
    Path(os.environ["GITHUB_STEP_SUMMARY"]).write_text("\n".join(lines) + "\n")


if __name__ == "__main__":
    if sys.argv[1] == "collect":
        collect(sys.argv[2], sys.argv[3])
    elif sys.argv[1] == "render":
        render(sys.argv[2])
    elif sys.argv[1] == "verify":
        verify_coverage()
    else:
        raise SystemExit("Expected collect, render, or verify")
