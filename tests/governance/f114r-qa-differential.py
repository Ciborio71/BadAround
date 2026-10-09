#!/usr/bin/env python3
"""F1.14R QA-only frozen differential acceptance. No release track changes."""
import argparse
import pathlib
import subprocess
import sys

TRACK = "f114r_qa"
REF = "fix/f1-14r-native-publication-contract"
SHA = "cd2acad23a16e916ececb7a244b5c4543f02e836"
TREE = "a945465562b8b3d91890d1dcd4f2688be6dcd790"
BASE = "27be97f7468a9fe1ac41b4482e22e98307ac0c0a"
FROZEN = "beb33ec3325480f77a266b3b6356f5860b00e16e"
SERVICE = "wordpress/plugins/badaround-core/includes/class-badaround-publication-service.php"
DISCOVERY = "wordpress/plugins/badaround-core/includes/class-badaround-discovery-query.php"
TERRITORY = "wordpress/themes/badaround-child/template-parts/territory-layout.php"
TEST = "tests/f1-14r-publication-contract.php"
CONSUMERS = "tests/f1-14r-consumers.php"
BLOBS = {
    SERVICE: "b92224d39e6860578e1993db9a57d296800728dc",
    DISCOVERY: "d224ac5a6571b28b89e07e3be895faedf6533ca1",
    TERRITORY: "f5a35eb6e2097dd63ef5bdc46992fa9fdc4c981a",
    TEST: "a8df692e5d4bb332d0748ea49fedec75979b9c7a",
    CONSUMERS: "3b0d22b2e82141b2df98cb1226b864b2ff7b32ef",
}

def git(repo, *args):
    return subprocess.check_output(["git", "-C", str(repo), *args], text=True).strip()

def validate(repo, track, ref, sha, tree, core, schema, theme):
    assert track == TRACK, "unauthorized track"
    assert ref == REF, "wrong branch"
    assert sha == SHA, "wrong candidate SHA"
    assert tree == TREE, "wrong candidate tree"
    assert (core, schema, theme) == ("0.18.0", "1.9.0", "0.1.0"), "wrong versions"
    assert git(repo, "rev-parse", "HEAD") == SHA, "checked-out SHA mismatch"
    assert git(repo, "rev-parse", "HEAD^{tree}") == TREE, "checked-out tree mismatch"
    # Four and only four scope changes relative to frozen canonical release.
    changed = git(repo, "diff", "--name-only", BASE, "HEAD").splitlines()
    assert set(changed) == set(BLOBS), "unexpected or missing application change: " + str(changed)
    for path, blob in BLOBS.items():
        assert git(repo, "hash-object", path) == blob, "unauthorized content change: " + path
    # Original F1.7 Core freeze is enforced without weakening media allowlists.
    extra_core = git(repo, "diff", "--name-only", FROZEN, "HEAD", "--", "wordpress/plugins/badaround-core").splitlines()
    assert set(extra_core).issubset({SERVICE, DISCOVERY}), "F1.7 core freeze violated: " + str(extra_core)
    # Existing F1.7 theme differential: preserve original allowed files, add only exact territory layout.
    allowed_theme = {
        "assets/css/native-report.css", "assets/js/native-report/api.js",
        "assets/js/native-report/errors.js", "assets/js/native-report/media.js",
        "assets/js/native-report/media-view.js", "assets/js/native-report/wizard.js",
        "functions.php", "inc/native-report.php", "template-parts/native-report/step.php",
        "template-parts/territory-layout.php",
    }
    prefix = "wordpress/themes/badaround-child/"
    changed_theme = git(repo, "diff", "--name-only", FROZEN, "HEAD", "--", prefix).splitlines()
    assert all(p.startswith(prefix) and p[len(prefix):] in allowed_theme for p in changed_theme), "F1.7 theme freeze violated"
    # Existing CSS frozen-prefix check retained unchanged.
    css = prefix + "assets/css/native-report.css"
    frozen_css = subprocess.check_output(["git", "-C", str(repo), "show", FROZEN + ":" + css])
    assert (pathlib.Path(repo) / css).read_bytes().startswith(frozen_css), "F1.6 base CSS changed"
    return True

def main():
    p = argparse.ArgumentParser()
    p.add_argument("--repo", default="candidate")
    p.add_argument("--track", default=TRACK)
    p.add_argument("--ref", default=REF)
    p.add_argument("--sha", default=SHA)
    p.add_argument("--tree", default=TREE)
    p.add_argument("--core", default="0.18.0")
    p.add_argument("--schema", default="1.9.0")
    p.add_argument("--theme", default="0.1.0")
    args = p.parse_args()
    try:
        validate(pathlib.Path(args.repo), args.track, args.ref, args.sha, args.tree, args.core, args.schema, args.theme)
    except (AssertionError, subprocess.CalledProcessError) as error:
        print("F1.14R DIFFERENTIAL REJECT:", error, file=sys.stderr)
        return 1
    print("F1.14R DIFFERENTIAL PASS: exact immutable candidate, F1.7 frozen contract unchanged")
    return 0

if __name__ == "__main__":
    sys.exit(main())
