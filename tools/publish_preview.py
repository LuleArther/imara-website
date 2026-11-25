"""Publish the static PREVIEW of the website to GitHub Pages (branch gh-pages).

    python tools/publish_preview.py

GitHub Pages cannot run PHP, so the preview leaves out public/api and public/admin.
On the preview, forms show a "call or WhatsApp us" message instead of submitting
(see assets/js/forms.js). The real site, with the patient request system and staff
dashboard, runs on PHP hosting - see docs/HANDOVER.md.

The preview is marked noindex so search engines do not list it before the real domain is live.
"""
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PUBLIC = ROOT / "public"


def git(*args, cwd):
    subprocess.run(["git", *args], cwd=cwd, check=True)


def main():
    remote = subprocess.run(["git", "remote", "get-url", "origin"], cwd=ROOT, capture_output=True, text=True, check=True).stdout.strip()
    name = subprocess.run(["git", "config", "user.name"], cwd=ROOT, capture_output=True, text=True).stdout.strip() or "Imara"
    email = subprocess.run(["git", "config", "user.email"], cwd=ROOT, capture_output=True, text=True).stdout.strip() or "noreply@example.com"

    subprocess.run([sys.executable, str(ROOT / "tools" / "build.py")], check=True)

    with tempfile.TemporaryDirectory() as tmp:
        site = Path(tmp) / "site"
        shutil.copytree(PUBLIC, site, ignore=shutil.ignore_patterns("api", "admin", ".htaccess"))
        for page in site.glob("*.html"):
            html = page.read_text(encoding="utf-8")
            html = html.replace('<meta name="theme-color"', '<meta name="robots" content="noindex, nofollow">\n    <meta name="theme-color"', 1)
            page.write_text(html, encoding="utf-8", newline="\n")
        (site / "robots.txt").write_text("User-agent: *\nDisallow: /\n", encoding="utf-8")
        (site / ".nojekyll").write_text("", encoding="utf-8")

        git("init", "-q", "-b", "gh-pages", cwd=site)
        git("config", "user.name", name, cwd=site)
        git("config", "user.email", email, cwd=site)
        git("config", "core.autocrlf", "false", cwd=site)
        git("add", "-A", cwd=site)
        git("commit", "-q", "-m", "Publish website preview", cwd=site)
        git("push", "-q", "--force", remote, "gh-pages", cwd=site)
    print("Preview published to the gh-pages branch. GitHub Pages updates in about a minute.")


if __name__ == "__main__":
    main()
