"""Update README badges only after the latest stable release exists on Packagist."""

import argparse
import json
import os
from pathlib import Path
import re
import time
from urllib.error import URLError
from urllib.parse import quote
from urllib.request import Request, urlopen


REPOSITORY = "mcakici/redakte"
PACKAGE = "mcakici/redakte"


def fetch_json(url, token=None):
    headers = {"User-Agent": "redakte-release-badges", "Cache-Control": "no-cache"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    with urlopen(Request(url, headers=headers), timeout=20) as response:
        return json.load(response)


def release_version(release):
    tag = release.get("tag_name", "")
    if release.get("draft") or release.get("prerelease") or not re.fullmatch(r"v?\d+\.\d+\.\d+", tag):
        raise ValueError("Expected a published stable semantic-version release.")
    return tag if tag.startswith("v") else f"v{tag}"


def require_packagist_version(payload, version):
    versions = payload.get("packages", {}).get(PACKAGE, [])
    if not any(item.get("version", "").lstrip("v") == version.lstrip("v") for item in versions):
        raise ValueError(f"Packagist has not indexed {version} yet.")


def render_badges(readme, version):
    for alt, label, target in [
        ("Latest Release", "version", f"https://github.com/{REPOSITORY}/releases/latest"),
        ("Packagist", "packagist", f"https://packagist.org/packages/{PACKAGE}"),
    ]:
        # A different release has a different image URL, so neither Shields nor
        # GitHub's image proxy can reuse an older version's badge for that URL.
        image = f"https://img.shields.io/badge/{label}-{quote(version, safe='')}-blue?style=flat-square"
        replacement = f"[![{alt}]({image})]({target})"
        pattern = rf"(?m)^\[!\[{re.escape(alt)}\]\([^\r\n]+\r?$"
        readme, count = re.subn(pattern, replacement, readme)
        if count != 1:
            raise ValueError(f"Expected exactly one {alt} badge; found {count}.")
    return readme


def sync(readme_path, wait_seconds=900):
    deadline = time.monotonic() + wait_seconds
    while True:
        try:
            # Read the latest release instead of trusting the triggering event:
            # rerunning an older release's workflow must never roll the badges back.
            release = fetch_json(f"https://api.github.com/repos/{REPOSITORY}/releases/latest", os.environ.get("GITHUB_TOKEN"))
            version = release_version(release)
            # repo.packagist.org can retain an old regional edge response even
            # after publication. The origin endpoint honors unique query keys.
            payload = fetch_json(f"https://packagist.org/p2/{PACKAGE}.json?release={quote(version, safe='')}&check={time.time_ns()}")
            require_packagist_version(payload, version)
            break
        except (URLError, TimeoutError, ValueError) as error:
            remaining = deadline - time.monotonic()
            if remaining <= 0:
                raise RuntimeError("Release metadata could not be verified; README was not changed.") from error
            print(f"Waiting for verified release metadata: {error}", flush=True)
            time.sleep(min(15, remaining))

    original = readme_path.read_text(encoding="utf-8")
    updated = render_badges(original, version)
    if updated != original:
        readme_path.write_text(updated, encoding="utf-8", newline="\n")
    return version, updated != original


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--readme", type=Path, default=Path("README.md"))
    parser.add_argument("--wait-seconds", type=int, default=900)
    args = parser.parse_args()
    version, changed = sync(args.readme, args.wait_seconds)
    print(f"Verified {version}; README {'updated' if changed else 'already current'}.")
