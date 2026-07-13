"""Create deterministic, disposable fixtures for the production file canary."""

from __future__ import annotations

import argparse
import pathlib
import re
import zipfile

from PIL import Image, ImageDraw, ImageFont


def font(size: int) -> ImageFont.FreeTypeFont:
    candidates = [
        pathlib.Path("C:/Windows/Fonts/arial.ttf"),
        pathlib.Path("C:/Windows/Fonts/seguiemj.ttf"),
        pathlib.Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"),
    ]
    for candidate in candidates:
        if candidate.exists():
            return ImageFont.truetype(str(candidate), size)
    raise RuntimeError("A Unicode TrueType font is required for canary fixtures")


def image_fixture(path: pathlib.Path, lines: list[str]) -> Image.Image:
    image = Image.new("RGB", (1800, 700), "white")
    draw = ImageDraw.Draw(image)
    face = font(72)
    for index, line in enumerate(lines):
        y = 90 + index * 150
        if re.search(r"[\u0600-\u06ff]", line):
            try:
                draw.text((1720, y), line, fill="black", font=face, direction="rtl", language="ar", anchor="ra")
            except KeyError:
                draw.text((80, y), line[::-1], fill="black", font=face)
        else:
            draw.text((80, y), line, fill="black", font=face)
    image.save(path, "PNG")
    return image


def text_pdf(path: pathlib.Path) -> None:
    stream = b"BT /F1 20 Tf 72 720 Td (CANARYTEXTPDF84 verified searchable PDF retention evidence for local extraction) Tj ET"
    objects = [
        b"<< /Type /Catalog /Pages 2 0 R >>",
        b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
        b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        b"<< /Length %d >>\nstream\n%s\nendstream" % (len(stream), stream),
    ]
    content = bytearray(b"%PDF-1.4\n")
    offsets = [0]
    for index, obj in enumerate(objects, start=1):
        offsets.append(len(content))
        content.extend(f"{index} 0 obj\n".encode())
        content.extend(obj)
        content.extend(b"\nendobj\n")
    xref = len(content)
    content.extend(f"xref\n0 {len(objects) + 1}\n".encode())
    content.extend(b"0000000000 65535 f \n")
    for offset in offsets[1:]:
        content.extend(f"{offset:010d} 00000 n \n".encode())
    content.extend(f"trailer << /Size {len(objects) + 1} /Root 1 0 R >>\nstartxref\n{xref}\n%%EOF\n".encode())
    path.write_bytes(content)


def docx(path: pathlib.Path) -> None:
    document = """<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Local market evaluation</w:t></w:r></w:p>
<w:tbl><w:tr><w:tc><w:p><w:r><w:t>Metric</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Value</w:t></w:r></w:p></w:tc></w:tr>
<w:tr><w:tc><w:p><w:r><w:t>CANARYDOCX31</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>31 percent margin</w:t></w:r></w:p></w:tc></w:tr></w:tbl>
</w:body></w:document>"""
    with zipfile.ZipFile(path, "w", zipfile.ZIP_DEFLATED) as archive:
        archive.writestr("word/document.xml", document)


def xlsx(path: pathlib.Path) -> None:
    workbook = """<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sales" sheetId="1" r:id="rId1"/></sheets></workbook>"""
    relationships = """<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/></Relationships>"""
    sheet = """<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>
<row r="1"><c r="A1" t="inlineStr"><is><t>Metric</t></is></c><c r="B1" t="inlineStr"><is><t>Value</t></is></c></row>
<row r="4"><c r="A4" t="inlineStr"><is><t>CANARYXLSXQ4</t></is></c><c r="B4"><f>SUM(40,2)</f><v>42</v></c></row>
</sheetData></worksheet>"""
    with zipfile.ZipFile(path, "w", zipfile.ZIP_DEFLATED) as archive:
        archive.writestr("xl/workbook.xml", workbook)
        archive.writestr("xl/_rels/workbook.xml.rels", relationships)
        archive.writestr("xl/worksheets/sheet1.xml", sheet)


def create(output: pathlib.Path) -> None:
    output.mkdir(parents=True, exist_ok=True)
    image_fixture(output / "image.png", [
        "CANARYIMAGE92 Arabic and English market evidence",
        "تحليل السوق العربي ورضا العملاء 92",
    ])
    scanned = image_fixture(output / "scan.png", [
        "CANARYSCANPDF77 scanned local PDF evidence",
        "دليل عربي ممسوح ضوئيا",
    ])
    scanned.save(output / "scan.pdf", "PDF", resolution=200.0)
    (output / "scan.png").unlink()
    text_pdf(output / "text.pdf")
    docx(output / "table.docx")
    xlsx(output / "formula.xlsx")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("output", type=pathlib.Path)
    create(parser.parse_args().output.resolve())
