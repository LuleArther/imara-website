"""Build the public HTML pages for the Imara website.

    python tools/build.py

Reads   src/partials/*.html   (shared head, header, footer, logo)
        src/pages/*.html      (one file per page; first line is a META comment)
        config/site.json      (phone, email, address ...)
        config/form-options.json (every dropdown / radio list)
Writes  public/<page>.html

Tokens available inside pages and partials
    {{PHONE}} {{PHONE_TEL}} {{WHATSAPP}} {{EMAIL}} {{ADDRESS}} {{DOMAIN}} {{ORG}} {{TAGLINE}} {{YEAR}}
    {{select:KEY}}                 <option> list (default from the options file is pre-selected)
    {{select:KEY|Placeholder}}     same, with an empty first option
    {{radios:KEY:name}}            radio pills ({{radios:KEY:name:required}} to require one)
    {{checks:KEY:name}}            checkbox pills (name[] array)
    {{phonecodes}}                 country code <option>s
"""
import html
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "src"
OUT = ROOT / "public"

site = json.loads((ROOT / "config" / "site.json").read_text(encoding="utf-8"))
opts = json.loads((ROOT / "config" / "form-options.json").read_text(encoding="utf-8"))
partials = {p.stem: p.read_text(encoding="utf-8") for p in (SRC / "partials").glob("*.html")}

CONSTANTS = {
    "PHONE": site["phone_display"],
    "PHONE_TEL": site["phone_tel"],
    "WHATSAPP": site["whatsapp"],
    "EMAIL": site["email"],
    "ADDRESS": site["address"],
    "DOMAIN": site["domain"],
    "ORG": site["org_name"],
    "TAGLINE": site["tagline"],
    "YEAR": site["year"],
    "FOUNDER": site["founder"],
}

e = lambda s: html.escape(str(s), quote=True)


def slug(text):
    return re.sub(r"[^a-z0-9]+", "-", text.lower()).strip("-")


def render_select(key, placeholder=None):
    data = opts[key]
    default = data.get("default", "")
    out = []
    if placeholder:
        sel = " selected" if not default else ""
        out.append(f'<option value=""{sel} disabled>{e(placeholder)}</option>')
    if "groups" in data:
        for g in data["groups"]:
            out.append(f'<optgroup label="{e(g["label"])}">')
            for o in g["options"]:
                sel = " selected" if o == default else ""
                out.append(f'<option value="{e(o)}"{sel}>{e(o)}</option>')
            out.append("</optgroup>")
    else:
        for o in data["options"]:
            sel = " selected" if o == default else ""
            out.append(f'<option value="{e(o)}"{sel}>{e(o)}</option>')
    return "\n".join(out)


def render_choices(key, name, kind, required=False):
    data = opts[key]
    default = data.get("default", "")
    out = []
    for i, o in enumerate(data["options"]):
        oid = f"{name}-{slug(o)}"
        chk = " checked" if o == default else ""
        req = " required" if (required and kind == "radio" and i == 0) else ""
        nm = name + ("[]" if kind == "checkbox" else "")
        out.append(
            f'<label class="choice-pill" for="{oid}"><input type="{kind}" id="{oid}" name="{nm}" value="{e(o)}"{chk}{req}><span>{e(o)}</span></label>'
        )
    return "\n".join(out)


def render_phonecodes():
    data = opts["phone_countries"]
    return "\n".join(
        f'<option value="{e(o["code"])}"{" selected" if o["code"] == data["default"] else ""}>{e(o["label"])}</option>'
        for o in data["options"]
    )


def render_breadcrumb(m):
    """{{breadcrumb:PHOTO|Small heading|Big title}} -> the template's breadcrumb banner with an Imara photo."""
    photo, heading, title = [x.strip() for x in m.group(1).split("|")]
    base = f"assets/images/photos/imara-{photo}"
    return f"""<!-- rts breadcrumb area start -->
    <section class="rts-breadcrumb-area ptb_sm--100 photo-bc">
        <img class="bg-photo" src="{base}-md.webp" srcset="{base}-md.webp 1100w, {base}-lg.webp 1600w" sizes="100vw" alt="" fetchpriority="high">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="breadcrumb-inner text-center">
                        <div class="banner-shape-area" data-sal="slide-up" data-sal-delay="120" data-sal-duration="800">
                            <span class="shape"></span>
                            <span class="shape"></span>
                            <span class="shape"></span>
                        </div>
                        <p class="heading-title" data-sal="slide-up" data-sal-delay="120" data-sal-duration="800">{heading}</p>
                        <h1 class="main-title" data-sal="slide-up" data-sal-delay="120" data-sal-duration="800">{title}</h1>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- rts breadcrumb area end -->"""


def expand_tokens(text, meta):
    text = text.replace("{{logo}}", partials["logo"].strip())
    text = re.sub(r"\{\{breadcrumb:([^}]+)\}\}", render_breadcrumb, text)

    def form_token(m):
        kind, rest = m.group(1), m.group(2)
        if kind == "select":
            key, _, ph = rest.partition("|")
            return render_select(key, ph or None)
        parts = rest.split(":")
        key, name = parts[0], parts[1]
        required = len(parts) > 2 and parts[2] == "required"
        return render_choices(key, name, "radio" if kind == "radios" else "checkbox", required)

    text = re.sub(r"\{\{(select|radios|checks):([^}]+)\}\}", form_token, text)
    text = text.replace("{{phonecodes}}", render_phonecodes())

    nav = meta.get("nav", "")
    text = re.sub(r"\{\{active:(\w+)\}\}", lambda m: "active" if m.group(1) == nav else "", text)

    for k, v in CONSTANTS.items():
        text = text.replace("{{" + k + "}}", v)

    scripts = "\n".join(f'    <script src="{e(s)}" defer></script>' for s in meta.get("scripts", []))
    text = (
        text.replace("{{title}}", e(meta["title"]))
        .replace("{{description}}", e(meta.get("description", "")))
        .replace("{{body_class}}", e(meta.get("body_class", "")))
        .replace("{{scripts}}", scripts)
    )
    return text


def add_cache_busting(doc):
    """Append ?v=<content hash> to local CSS/JS so browsers never show an old stylesheet after an update."""
    import hashlib

    def ver(m):
        attr, path = m.group(1), m.group(2)
        f = OUT / path
        if not f.exists():
            return m.group(0)
        h = hashlib.md5(f.read_bytes()).hexdigest()[:8]
        return f'{attr}="{path}?v={h}"'

    return re.sub(r'(href|src)="(assets/(?:css|js)/[^"?]+\.(?:css|js))"', ver, doc)


def build():
    pages = sorted((SRC / "pages").glob("*.html"))
    if not pages:
        sys.exit("No pages found in src/pages")
    for page in pages:
        raw = page.read_text(encoding="utf-8")
        m = re.match(r"\s*<!--META\s*(\{.*?\})\s*-->\s*", raw, re.S)
        if not m:
            sys.exit(f"{page.name}: first line must be <!--META {{...}} -->")
        meta = json.loads(m.group(1))
        body = raw[m.end():]
        doc = partials["head"] + partials["header"] + body + partials["footer"]
        doc = expand_tokens(doc, meta)
        doc = add_cache_busting(doc)
        leftover = re.findall(r"\{\{[^}]+\}\}", doc)
        if leftover:
            sys.exit(f"{page.name}: unknown tokens {sorted(set(leftover))}")
        (OUT / page.name).write_text(doc, encoding="utf-8", newline="\n")
        print("built", page.name)

    # The API reads the same option lists to validate submissions.
    print("done:", len(pages), "pages")


if __name__ == "__main__":
    build()
