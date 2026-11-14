import tempfile
from pathlib import Path
import unittest
from unittest.mock import patch

from scripts.sync_release_badges import render_badges, release_version, sync


README = """# Redakte
[![Latest Release](https://img.shields.io/github/v/release/mcakici/redakte.svg)](https://github.com/mcakici/redakte/releases/latest)
[![Packagist](https://img.shields.io/packagist/v/mcakici/redakte.svg)](https://packagist.org/packages/mcakici/redakte)
[![Tests](tests.svg)](tests)
"""


class ReleaseBadgeTests(unittest.TestCase):
    def test_versioned_urls_replace_both_badges_and_leave_other_content_unchanged(self):
        rendered = render_badges(README, "v1.0.8")
        self.assertIn("/badge/version-v1.0.8-blue?", rendered)
        self.assertIn("/badge/packagist-v1.0.8-blue?", rendered)
        self.assertIn("[![Tests](tests.svg)](tests)", rendered)
        self.assertNotIn("/packagist/v/", rendered)

    def test_sync_is_idempotent_and_accepts_packagist_versions_without_v_prefix(self):
        with tempfile.TemporaryDirectory() as directory:
            readme = Path(directory) / "README.md"
            readme.write_text(README, encoding="utf-8")
            replies = [{"tag_name": "v1.0.8"}, {"package": {"name": "mcakici/redakte", "versions": {"1.0.8": {}}}}]
            with patch("scripts.sync_release_badges.fetch_json", side_effect=replies * 2):
                self.assertEqual(("v1.0.8", True), sync(readme, 0))
                self.assertEqual(("v1.0.8", False), sync(readme, 0))

    def test_packagist_lag_cannot_publish_an_unverified_badge(self):
        with tempfile.TemporaryDirectory() as directory:
            readme = Path(directory) / "README.md"
            readme.write_text(README, encoding="utf-8")
            replies = [{"tag_name": "v1.0.8"}, {"package": {"name": "mcakici/redakte", "versions": {"v1.0.7": {}}}}]
            with patch("scripts.sync_release_badges.fetch_json", side_effect=replies):
                with self.assertRaisesRegex(RuntimeError, "README was not changed"):
                    sync(readme, 0)
            self.assertEqual(README, readme.read_text(encoding="utf-8"))

    def test_retry_reads_a_fresh_origin_url_instead_of_reusing_stale_metadata(self):
        with tempfile.TemporaryDirectory() as directory:
            readme = Path(directory) / "README.md"
            readme.write_text(README, encoding="utf-8")
            replies = [{"tag_name": "v1.0.8"}, {"package": {"name": "mcakici/redakte", "versions": {"v1.0.7": {}}}},
                       {"tag_name": "v1.0.8"}, {"package": {"name": "mcakici/redakte", "versions": {"v1.0.8": {}}}}]
            with patch("scripts.sync_release_badges.fetch_json", side_effect=replies) as fetch, \
                 patch("scripts.sync_release_badges.time.sleep"), \
                 patch("scripts.sync_release_badges.time.time_ns", side_effect=[1, 2]):
                self.assertEqual(("v1.0.8", True), sync(readme, 30))
            urls = [call.args[0] for call in fetch.call_args_list if "packagist.org" in call.args[0]]
            self.assertEqual(2, len(urls))
            self.assertTrue(all(url.startswith("https://packagist.org/packages/") for url in urls))
            self.assertNotEqual(urls[0], urls[1])

    def test_prereleases_drafts_and_unsafe_tags_are_rejected(self):
        for release in [{"tag_name": "v2.0.0-beta"}, {"tag_name": "v2.0.0", "prerelease": True},
                        {"tag_name": "v2.0.0", "draft": True}, {"tag_name": "not-a-version"}]:
            with self.subTest(release=release), self.assertRaises(ValueError):
                release_version(release)

    def test_missing_badge_fails_instead_of_silently_skipping_the_update(self):
        with self.assertRaisesRegex(ValueError, "exactly one Packagist"):
            render_badges(README.replace("[![Packagist]", "[![Other]"), "v1.0.8")


if __name__ == "__main__":
    unittest.main()
