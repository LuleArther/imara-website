"""Publish the static PREVIEW of the website to GitHub Pages (branch gh-pages).

    python tools/publish_preview.py              build and publish
    python tools/publish_preview.py --out DIR    build into DIR only (for testing), no publish

GitHub Pages cannot run PHP, so the preview leaves out every .php file and adds
TEST MODE from preview/: forms are saved in the visitor's own browser and a test
dashboard (demo-admin/) shows them, clearly labelled as a test that never reaches Imara.
The real site, with the shared patient request system and staff dashboard, runs on
PHP hosting - see docs/HANDOVER.md.

The preview is marked noindex so search engines do not list it before the real domain is live.
"""
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PUBLIC = ROOT / "public"
PREVIEW = ROOT / "preview"

NOINDEX = '<meta name="robots" content="noindex, nofollow">\n    <meta name="theme-color"'
TEST_SCRIPT = '    <script src="assets/js/demo-store.js" defer></script>\n</body>'


def git(*args, cwd):
    subprocess.run(["git", *args], cwd=cwd, check=True)


def out_of(cmd):
    return subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True).stdout.strip()


def build_into(site):
    shutil.copytree(PUBLIC, site, ignore=shutil.ignore_patterns("api", "*.php", ".htaccess"))
    shutil.copytree(PREVIEW, site, dirs_exist_ok=True)  # test mode + test dashboard
    for page in site.glob("*.html"):
        html = page.read_text(encoding="utf-8")
        html = html.replace('<meta name="theme-color"', NOINDEX, 1)
        html = html.replace("</body>", TEST_SCRIPT, 1)
        page.write_text(html, encoding="utf-8", newline="\n")
    (site / "robots.txt").write_text("User-agent: *\nDisallow: /\n", encoding="utf-8")
    (site / ".nojekyll").write_text("", encoding="utf-8")
    leaked = list(site.rglob("*.php"))
    if leaked:
        sys.exit(f"Refusing to publish: PHP files found in the preview: {leaked[:3]}")


def main():
    subprocess.run([sys.executable, str(ROOT / "tools" / "build.py")], check=True)

    if "--out" in sys.argv:
        site = Path(sys.argv[sys.argv.index("--out") + 1])
        if site.exists():
            shutil.rmtree(site)
        build_into(site)
        print("Preview built in", site, "(not published)")
        return

    remote = out_of(["git", "remote", "get-url", "origin"])
    name = out_of(["git", "config", "user.name"]) or "Imara"
    email = out_of(["git", "config", "user.email"]) or "noreply@example.com"

    with tempfile.TemporaryDirectory() as tmp:
        site = Path(tmp) / "site"
        build_into(site)
        git("init", "-q", "-b", "gh-pages", cwd=site)
        git("config", "user.name", name, cwd=site)
        git("config", "user.email", email, cwd=site)
        git("config", "core.autocrlf", "false", cwd=site)
        git("add", "-A", cwd=site)
        git("commit", "-q", "-m", "Publish website preview (test mode)", cwd=site)
        git("push", "-q", "--force", remote, "gh-pages", cwd=site)
    print("Preview published to the gh-pages branch. GitHub Pages updates in about a minute.")


if __name__ == "__main__":
    main()
