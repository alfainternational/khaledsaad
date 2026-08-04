from __future__ import annotations

import json
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont, ImageOps


ROOT = Path(__file__).resolve().parents[1]
ASSETS = ROOT / "public" / "assets" / "content" / "marketing-course"
SOURCE = ASSETS / "source"
MANIFEST = ROOT / "database" / "data" / "content" / "marketing-course" / "manifest.json"
FONT = ROOT / "public" / "assets" / "fonts" / "Hacen-Tunisia.ttf"


def font(size: int) -> ImageFont.FreeTypeFont:
    return ImageFont.truetype(str(FONT), size=size)


def text_width(draw: ImageDraw.ImageDraw, value: str, face: ImageFont.FreeTypeFont) -> float:
    box = draw.textbbox((0, 0), value, font=face, direction="rtl", language="ar")
    return box[2] - box[0]


def wrap_rtl(draw: ImageDraw.ImageDraw, value: str, face: ImageFont.FreeTypeFont, width: int) -> list[str]:
    lines: list[str] = []
    current: list[str] = []

    for word in value.split():
        candidate = " ".join(current + [word])
        if current and text_width(draw, candidate, face) > width:
            lines.append(" ".join(current))
            current = [word]
        else:
            current.append(word)

    if current:
        lines.append(" ".join(current))

    return lines


def gradient_overlay(image: Image.Image, start_ratio: float) -> None:
    overlay = Image.new("RGBA", image.size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)
    start = int(image.height * start_ratio)
    distance = max(image.height - start, 1)

    for y in range(start, image.height):
        alpha = int(225 * ((y - start) / distance) ** 0.65)
        draw.line(((0, y), (image.width, y)), fill=(4, 18, 48, alpha))

    image.alpha_composite(overlay)


def render(source: Path, output: Path, title: str, order: int, size: tuple[int, int], quality: int) -> None:
    image = ImageOps.fit(Image.open(source).convert("RGBA"), size, method=Image.Resampling.LANCZOS)
    width, height = size
    gradient_overlay(image, 0.48)
    draw = ImageDraw.Draw(image)
    pad = int(width * 0.055)
    title_size = max(28, int(width * 0.043))
    max_title_width = width - (pad * 2)

    while title_size > 26:
        title_font = font(title_size)
        lines = wrap_rtl(draw, title, title_font, max_title_width)
        if len(lines) <= 3:
            break
        title_size -= 2

    label_font = font(max(18, int(width * 0.022)))
    line_height = int(title_size * 1.34)
    title_height = line_height * len(lines)
    bottom = height - int(height * 0.075)
    title_top = bottom - title_height

    label = f"سلسلة تعلم التسويق  ·  الدرس {order:02d}"
    draw.text(
        (width - pad, title_top - int(label_font.size * 1.45)),
        label,
        font=label_font,
        fill=(255, 177, 66, 255),
        anchor="ra",
        direction="rtl",
        language="ar",
    )

    y = title_top
    for line in lines:
        draw.text(
            (width - pad, y),
            line,
            font=title_font,
            fill=(255, 255, 255, 255),
            anchor="ra",
            direction="rtl",
            language="ar",
            stroke_width=max(1, width // 900),
            stroke_fill=(5, 22, 55, 190),
        )
        y += line_height

    rgb = image.convert("RGB")
    if output.suffix == ".webp":
        rgb.save(output, format="WEBP", quality=quality, method=6)
    else:
        rgb.save(output, format="PNG", optimize=True)


def main() -> None:
    manifest = json.loads(MANIFEST.read_text(encoding="utf-8"))
    written = 0

    for lesson in manifest["lessons"]:
        order = int(lesson["order"])
        stem = f"lesson-{order:02d}"
        source = SOURCE / f"{stem}.png"
        if not source.is_file():
            raise SystemExit(f"Missing source image: {source}")

        render(source, ASSETS / f"{stem}-hero.webp", lesson["title"], order, (1600, 900), 90)
        render(source, ASSETS / f"{stem}-card.webp", lesson["title"], order, (800, 450), 88)
        render(source, ASSETS / f"{stem}-og.png", lesson["title"], order, (1200, 630), 95)
        written += 3

    print(f"{written} derivatives written; {len(manifest['lessons'])} source images validated.")


if __name__ == "__main__":
    main()
