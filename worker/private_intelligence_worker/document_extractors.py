"""Bounded, structured local extractors for Office Open XML files."""

from __future__ import annotations

import pathlib
import posixpath
import re
import zipfile
from typing import Any
from xml.etree import ElementTree


WORD_NS = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
SHEET_NS = "http://schemas.openxmlformats.org/spreadsheetml/2006/main"
REL_NS = "http://schemas.openxmlformats.org/package/2006/relationships"
OFFICE_REL_NS = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"


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
