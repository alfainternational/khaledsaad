"""Bounded, structured local extractors for Office Open XML files."""

from __future__ import annotations

import pathlib
import posixpath
import re
import csv
import io
import os
import subprocess
import tempfile
import zipfile
from typing import Any
from xml.etree import ElementTree


WORD_NS = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
SHEET_NS = "http://schemas.openxmlformats.org/spreadsheetml/2006/main"
REL_NS = "http://schemas.openxmlformats.org/package/2006/relationships"
OFFICE_REL_NS = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"


def extract_pdf(path: pathlib.Path, contract: dict[str, Any], runner: Any = None) -> dict[str, Any]:
    runner = runner or run_local
    info = checked(runner(["pdfinfo", str(path)]))
    match = re.search(r"^Pages:\s*(\d+)\s*$", info.stdout, re.MULTILINE | re.IGNORECASE)
    if not match:
        raise RuntimeError("PDF page count is unavailable")
    pages = int(match.group(1))
    if pages < 1 or pages > 500:
        raise RuntimeError("PDF page count exceeds extraction limit")

    chunks: list[dict[str, Any]] = []
    ocr_pages = 0
    min_text = max(10, int(contract.get("pdf_min_text_chars", 40)))
    for page in range(1, pages + 1):
        extracted = checked(runner([
            "pdftotext", "-f", str(page), "-l", str(page), "-layout", str(path), "-"
        ])).stdout.strip()
        if len(extracted) >= min_text:
            add_chunk(chunks, extracted, f"Page {page}", {
                "type": "page", "page": page, "method": "text",
            }, contract)
            continue

        with tempfile.TemporaryDirectory(prefix="private-worker-pdf-") as temporary:
            prefix = str(pathlib.Path(temporary) / "page")
            checked(runner([
                "pdftoppm", "-f", str(page), "-l", str(page), "-png", "-r", "200", str(path), prefix
            ]))
            images = sorted(pathlib.Path(temporary).glob("page-*.png"))
            if not images:
                raise RuntimeError("PDF rasterization produced no page image")
            page_chunks, _ = ocr_chunks(images[0], runner, page=page)
            for chunk in page_chunks:
                add_chunk(chunks, chunk["content"], chunk.get("heading"), chunk["locator"], contract)
            ocr_pages += 1

    return result("pdf", chunks, contract, {"pages": pages, "ocr_pages": ocr_pages})


def extract_image(path: pathlib.Path, contract: dict[str, Any], runner: Any = None) -> dict[str, Any]:
    runner = runner or run_local
    chunks, confidence = ocr_chunks(path, runner)
    bounded: list[dict[str, Any]] = []
    for chunk in chunks:
        add_chunk(bounded, chunk["content"], chunk.get("heading"), chunk["locator"], contract)
    return result("image", bounded, contract, {"mean_confidence": confidence, "regions": len(bounded)})


def ocr_chunks(path: pathlib.Path, runner: Any, page: int | None = None) -> tuple[list[dict[str, Any]], float]:
    languages = os.getenv("AI_WORKER_OCR_LANGUAGE", "ara+eng")
    output = checked(runner(["tesseract", str(path), "stdout", "-l", languages, "tsv"])).stdout
    rows = list(csv.DictReader(io.StringIO(output), delimiter="\t"))
    page_row = next((row for row in rows if row.get("level") == "1"), None)
    width = max(1, int((page_row or {}).get("width", "1") or 1))
    height = max(1, int((page_row or {}).get("height", "1") or 1))
    blocks: dict[tuple[str, str, str], list[dict[str, Any]]] = {}
    for row in rows:
        text = (row.get("text") or "").strip()
        try:
            confidence = float(row.get("conf", "-1") or -1)
        except ValueError:
            confidence = -1
        if not text or confidence < 0:
            continue
        key = (row.get("block_num", "0"), row.get("par_num", "0"), row.get("line_num", "0"))
        blocks.setdefault(key, []).append({**row, "confidence_value": confidence})

    chunks: list[dict[str, Any]] = []
    all_confidences: list[float] = []
    for block_number, words in enumerate(blocks.values(), start=1):
        left = min(int(word["left"]) for word in words)
        top = min(int(word["top"]) for word in words)
        right = max(int(word["left"]) + int(word["width"]) for word in words)
        bottom = max(int(word["top"]) + int(word["height"]) for word in words)
        confidences = [float(word["confidence_value"]) for word in words]
        all_confidences.extend(confidences)
        locator: dict[str, Any] = {
            "type": "image_region",
            "region": block_number,
            "bbox": [round(left / width, 4), round(top / height, 4), round(right / width, 4), round(bottom / height, 4)],
            "confidence": round(sum(confidences) / len(confidences), 2),
            "method": "ocr",
        }
        if page is not None:
            locator["page"] = page
        chunks.append({
            "heading": f"Page {page}" if page is not None else None,
            "content": " ".join(str(word["text"]) for word in words),
            "locator": locator,
        })
    if not chunks:
        raise RuntimeError("OCR found no text")
    mean = round(sum(all_confidences) / len(all_confidences), 2)
    return chunks, mean


def run_local(command: list[str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        command,
        capture_output=True,
        text=True,
        encoding="utf-8",
        timeout=int(os.getenv("AI_WORKER_COMMAND_TIMEOUT", "180")),
        check=False,
    )


def checked(completed: Any) -> Any:
    if completed.returncode != 0:
        raise RuntimeError(f"local extractor exited with code {completed.returncode}")
    return completed


def extract_docx(path: pathlib.Path, contract: dict[str, Any]) -> dict[str, Any]:
    with safe_archive(path, contract) as archive:
        root = safe_xml(archive, "word/document.xml")
    body = root.find(f"{{{WORD_NS}}}body")
    if body is None:
        raise RuntimeError("DOCX document body is missing")

    chunks: list[dict[str, Any]] = []
    paragraph_number = 0
    table_number = 0
    active_heading: str | None = None
    for child in body:
        if child.tag == f"{{{WORD_NS}}}p":
            paragraph_number += 1
            text = node_text(child)
            if not text:
                continue
            style = child.find(f"{{{WORD_NS}}}pPr/{{{WORD_NS}}}pStyle")
            style_name = style.attrib.get(f"{{{WORD_NS}}}val", "") if style is not None else ""
            if style_name.lower().startswith("heading"):
                active_heading = text
            add_chunk(chunks, text, active_heading, {
                "type": "docx_paragraph",
                "paragraph": paragraph_number,
                "style": style_name or None,
            }, contract)
        elif child.tag == f"{{{WORD_NS}}}tbl":
            table_number += 1
            for row_number, row in enumerate(child.findall(f"{{{WORD_NS}}}tr"), start=1):
                cells = [node_text(cell) for cell in row.findall(f"{{{WORD_NS}}}tc")]
                content = " | ".join(cells)
                if content.strip(" |"):
                    add_chunk(chunks, content, active_heading, {
                        "type": "docx_table",
                        "table": table_number,
                        "row": row_number,
                        "columns": len(cells),
                    }, contract)

    return result("docx", chunks, contract, {"paragraphs": paragraph_number, "tables": table_number})


def extract_xlsx(path: pathlib.Path, contract: dict[str, Any]) -> dict[str, Any]:
    with safe_archive(path, contract) as archive:
        workbook = safe_xml(archive, "xl/workbook.xml")
        relationships = safe_xml(archive, "xl/_rels/workbook.xml.rels")
        relationship_targets = {
            node.attrib.get("Id", ""): node.attrib.get("Target", "")
            for node in relationships.findall(f"{{{REL_NS}}}Relationship")
        }
        shared = shared_strings(archive)
        chunks: list[dict[str, Any]] = []
        sheet_count = 0
        for sheet in workbook.findall(f".//{{{SHEET_NS}}}sheet"):
            sheet_name = sheet.attrib.get("name", f"Sheet{sheet_count + 1}")
            relationship_id = sheet.attrib.get(f"{{{OFFICE_REL_NS}}}id", "")
            target = relationship_targets.get(relationship_id, "")
            member = normalize_member(posixpath.join("xl", target))
            if not member.startswith("xl/worksheets/"):
                raise RuntimeError("unsafe worksheet relationship")
            worksheet = safe_xml(archive, member)
            sheet_count += 1
            merged_ranges = [
                node.attrib.get("ref", "")
                for node in worksheet.findall(f".//{{{SHEET_NS}}}mergeCell")
                if node.attrib.get("ref")
            ]
            for row in worksheet.findall(f".//{{{SHEET_NS}}}sheetData/{{{SHEET_NS}}}row"):
                row_number = int(row.attrib.get("r", "0") or 0)
                values: list[str] = []
                cells: list[str] = []
                for cell in row.findall(f"{{{SHEET_NS}}}c"):
                    reference = cell.attrib.get("r", "")
                    rendered = cell_value(cell, shared)
                    if not reference or rendered == "":
                        continue
                    formula = cell.find(f"{{{SHEET_NS}}}f")
                    display = f"{reference}={rendered}"
                    if formula is not None and formula.text:
                        display += f" [formula: {formula.text}]"
                    cells.append(reference)
                    values.append(display)
                if not values:
                    continue
                row_merges = [item for item in merged_ranges if range_intersects_row(item, row_number)]
                add_chunk(chunks, " | ".join(values), sheet_name, {
                    "type": "xlsx_row",
                    "sheet": sheet_name,
                    "row": row_number,
                    "cells": cells,
                    "merged_ranges": row_merges,
                }, contract)

    return result("xlsx", chunks, contract, {"sheets": sheet_count})


def safe_archive(path: pathlib.Path, contract: dict[str, Any]) -> zipfile.ZipFile:
    archive = zipfile.ZipFile(path)
    max_expanded = max(1_048_576, int(contract.get("max_text_chars", 350000)) * 20)
    expanded = 0
    for info in archive.infolist():
        normalized = normalize_member(info.filename)
        if normalized != info.filename.replace("\\", "/").lstrip("./"):
            archive.close()
            raise RuntimeError("unsafe archive path")
        expanded += info.file_size
        if expanded > max_expanded:
            archive.close()
            raise RuntimeError("archive exceeds extraction limit")
    return archive


def normalize_member(name: str) -> str:
    replaced = name.replace("\\", "/")
    normalized = posixpath.normpath(replaced).lstrip("/")
    if replaced.startswith(("/", "\\")) or normalized == ".." or normalized.startswith("../") or re.match(r"^[A-Za-z]:", replaced):
        raise RuntimeError("unsafe archive path")
    return normalized


def safe_xml(archive: zipfile.ZipFile, member: str) -> ElementTree.Element:
    normalized = normalize_member(member)
    try:
        raw = archive.read(normalized)
    except KeyError as error:
        raise RuntimeError(f"required archive member is missing: {normalized}") from error
    upper = raw[:4096].upper()
    if b"<!DOCTYPE" in upper or b"<!ENTITY" in upper:
        raise RuntimeError("unsafe XML declaration")
    return ElementTree.fromstring(raw)


def shared_strings(archive: zipfile.ZipFile) -> list[str]:
    if "xl/sharedStrings.xml" not in archive.namelist():
        return []
    root = safe_xml(archive, "xl/sharedStrings.xml")
    return [node_text(item) for item in root.findall(f"{{{SHEET_NS}}}si")]


def node_text(node: ElementTree.Element) -> str:
    return " ".join("".join(node.itertext()).split())


def cell_value(cell: ElementTree.Element, shared: list[str]) -> str:
    cell_type = cell.attrib.get("t", "")
    if cell_type == "inlineStr":
        inline = cell.find(f"{{{SHEET_NS}}}is")
        return node_text(inline) if inline is not None else ""
    value_node = cell.find(f"{{{SHEET_NS}}}v")
    value = value_node.text if value_node is not None and value_node.text is not None else ""
    if cell_type == "s" and value.isdigit() and int(value) < len(shared):
        return shared[int(value)]
    if cell_type == "b":
        return "true" if value == "1" else "false"
    return value


def range_intersects_row(reference: str, row: int) -> bool:
    numbers = [int(value) for value in re.findall(r"\d+", reference)]
    return bool(numbers) and min(numbers) <= row <= max(numbers)


def add_chunk(
    chunks: list[dict[str, Any]],
    content: str,
    heading: str | None,
    locator: dict[str, Any],
    contract: dict[str, Any],
) -> None:
    max_chars = max(1, int(contract.get("max_chunk_chars", 3500)))
    for part_number, offset in enumerate(range(0, len(content), max_chars), start=1):
        part = content[offset: offset + max_chars].strip()
        if not part:
            continue
        part_locator = dict(locator)
        if len(content) > max_chars:
            part_locator["part"] = part_number
        chunks.append({"heading": heading, "content": part, "locator": part_locator})
        if len(chunks) > int(contract.get("max_chunks", 100)):
            raise RuntimeError("document exceeds extraction limit")


def result(format_name: str, chunks: list[dict[str, Any]], contract: dict[str, Any], metadata: dict[str, Any]) -> dict[str, Any]:
    text = "\n".join(chunk["content"] for chunk in chunks).strip()
    if not text or len(text) > int(contract.get("max_text_chars", 350000)):
        raise RuntimeError("document exceeds extraction limit")
    return {
        "contract_version": "v2",
        "text": text,
        "chunks": chunks,
        "language": "ara+eng",
        "metadata": {"format": format_name, "chunk_count": len(chunks), **metadata},
    }
