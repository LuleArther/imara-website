"""Convert Clifton's original photos into compressed WebP for a low-data site.

Usage: python tools/convert_photos.py <source-folder> <output-folder>
Writes <name>-sm.webp (480px), <name>-md.webp (1100px) and <name>-lg.webp (1920px).
"""
import sys, glob, os
from PIL import Image, ImageOps

SIZES = {"sm": (480, 62), "md": (1100, 64), "lg": (1920, 62)}

def main(src, out):
    os.makedirs(out, exist_ok=True)
    files = sorted(glob.glob(os.path.join(src, "IMG_*.png")) + glob.glob(os.path.join(src, "IMG_*.jpg")))
    for f in files:
        name = os.path.splitext(os.path.basename(f))[0].lower().replace("img_", "imara-")
        im = ImageOps.exif_transpose(Image.open(f)).convert("RGB")
        for key, (w, q) in SIZES.items():
            dest = os.path.join(out, f"{name}-{key}.webp")
            if os.path.exists(dest):
                continue
            c = im.copy()
            c.thumbnail((w, w))
            c.save(dest, "WEBP", quality=q, method=6)
        print("done", name, flush=True)

if __name__ == "__main__":
    main(sys.argv[1], sys.argv[2])
