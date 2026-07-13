import pathlib
import tempfile
import unittest
import zipfile
import subprocess
from types import SimpleNamespace

from document_extractors import extract_docx, extract_image, extract_pdf, extract_xlsx


CONTRACT = {"max_chunks": 100, "max_text_chars": 350000, "max_chunk_chars": 3500}


class StructuredOfficeExtractionTest(unittest.TestCase):
    def test_pdf_preserves_pages_and_uses_ocr_only_for_scanned_pages(self):
        path = self.plain_file(b"%PDF-fixture", ".pdf")
        calls = []

        def runner(command):
            calls.append(command)
            if command[0] == "pdfinfo":
                return SimpleNamespace(stdout="Pages: 2\n", stderr="", returncode=0)
            if command[0] == "pdftotext" and command[2] == "1":
                return SimpleNamespace(stdout="Page one contains enough searchable market evidence for indexing.", stderr="", returncode=0)
            if command[0] == "pdftotext":
                return SimpleNamespace(stdout="x", stderr="", returncode=0)
            if command[0] == "pdftoppm":
                pathlib.Path(command[-1] + "-2.png").write_bytes(b"png")
                return SimpleNamespace(stdout="", stderr="", returncode=0)
            if command[0] == "tesseract":
                return SimpleNamespace(stdout=self.ocr_tsv("Scanned Arabic evidence", 91), stderr="", returncode=0)
            raise AssertionError(command)

        result = extract_pdf(path, CONTRACT, runner=runner)

        self.assertEqual([1, 2], [chunk["locator"]["page"] for chunk in result["chunks"]])
        self.assertEqual("page", result["chunks"][0]["locator"]["type"])
        self.assertEqual("image_region", result["chunks"][1]["locator"]["type"])
        self.assertEqual("ocr", result["chunks"][1]["locator"]["method"])
        self.assertEqual(91.0, result["chunks"][1]["locator"]["confidence"])
        self.assertEqual(1, result["metadata"]["ocr_pages"])
        self.assertEqual(1, sum(1 for call in calls if call[0] == "tesseract"))

    def test_image_ocr_returns_normalized_regions_and_confidence(self):
        path = self.plain_file(b"png", ".png")
        runner = lambda command: SimpleNamespace(
            stdout=self.ocr_tsv("Arabic market evidence", 87), stderr="", returncode=0
        )

        result = extract_image(path, CONTRACT, runner=runner)

        locator = result["chunks"][0]["locator"]
        self.assertEqual("image_region", locator["type"])
        self.assertEqual([0.1, 0.2, 0.5, 0.3], locator["bbox"])
        self.assertEqual(87.0, locator["confidence"])
        self.assertEqual(87.0, result["metadata"]["mean_confidence"])

    def test_pdf_reports_missing_tools_and_local_command_timeouts(self):
        path = self.plain_file(b"%PDF-fixture", ".pdf")

        with self.assertRaises(FileNotFoundError):
            extract_pdf(path, CONTRACT, runner=lambda command: (_ for _ in ()).throw(FileNotFoundError("pdfinfo")))
        with self.assertRaises(subprocess.TimeoutExpired):
            extract_pdf(path, CONTRACT, runner=lambda command: (_ for _ in ()).throw(subprocess.TimeoutExpired(command, 1)))

    def test_docx_preserves_headings_paragraphs_and_table_rows(self):
        document = """<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Market Report</w:t></w:r></w:p>
<w:p><w:r><w:t>Annual growth reached 12 percent.</w:t></w:r></w:p>
<w:tbl>
<w:tr><w:tc><w:p><w:r><w:t>Name</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Revenue</w:t></w:r></w:p></w:tc></w:tr>
<w:tr><w:tc><w:p><w:r><w:t>Acme</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>42</w:t></w:r></w:p></w:tc></w:tr>
</w:tbl></w:body></w:document>"""
        path = self.office_file({"word/document.xml": document}, ".docx")

        result = extract_docx(path, CONTRACT)

        self.assertEqual("v2", result["contract_version"])
        self.assertEqual(
            ["docx_paragraph", "docx_paragraph", "docx_table", "docx_table"],
            [chunk["locator"]["type"] for chunk in result["chunks"]],
        )
        self.assertEqual("Market Report", result["chunks"][0]["heading"])
        self.assertEqual(2, result["chunks"][1]["locator"]["paragraph"])
        self.assertEqual("Name | Revenue", result["chunks"][2]["content"])
        self.assertEqual({"table": 1, "row": 2}, {
            "table": result["chunks"][3]["locator"]["table"],
            "row": result["chunks"][3]["locator"]["row"],
        })

    def test_xlsx_preserves_sheet_rows_cells_formulas_and_merged_ranges(self):
        workbook = """<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sales" sheetId="1" r:id="rId1"/></sheets></workbook>"""
        relationships = """<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/></Relationships>"""
        shared = """<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Name</t></si><si><t>Acme</t></si></sst>"""
        sheet = """<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>
<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="inlineStr"><is><t>Revenue</t></is></c></row>
<row r="2"><c r="A2" t="s"><v>1</v></c><c r="B2"><f>SUM(40,2)</f><v>42</v></c></row>
<row r="3"><c r="A3" t="inlineStr"><is><t>Total</t></is></c></row>
</sheetData><mergeCells><mergeCell ref="A3:B3"/></mergeCells></worksheet>"""
        path = self.office_file({
            "xl/workbook.xml": workbook,
            "xl/_rels/workbook.xml.rels": relationships,
            "xl/sharedStrings.xml": shared,
            "xl/worksheets/sheet1.xml": sheet,
        }, ".xlsx")

        result = extract_xlsx(path, CONTRACT)

        self.assertEqual("v2", result["contract_version"])
        self.assertEqual([1, 2, 3], [chunk["locator"]["row"] for chunk in result["chunks"]])
        self.assertEqual("Sales", result["chunks"][1]["locator"]["sheet"])
        self.assertEqual(["A2", "B2"], result["chunks"][1]["locator"]["cells"])
        self.assertIn("B2=42 [formula: SUM(40,2)]", result["chunks"][1]["content"])
        self.assertEqual(["A3:B3"], result["chunks"][2]["locator"]["merged_ranges"])

    def test_office_archives_reject_unsafe_paths_and_expansion_bombs(self):
        unsafe = self.office_file({"../word/document.xml": "bad"}, ".docx")
        with self.assertRaisesRegex(RuntimeError, "unsafe archive path"):
            extract_docx(unsafe, CONTRACT)

        huge_contract = {**CONTRACT, "max_text_chars": 10}
        normal = self.office_file({
            "word/document.xml": "<w:document xmlns:w=\"http://schemas.openxmlformats.org/wordprocessingml/2006/main\"><w:body><w:p><w:r><w:t>more than ten characters</w:t></w:r></w:p></w:body></w:document>"
        }, ".docx")
        with self.assertRaisesRegex(RuntimeError, "exceeds extraction limit"):
            extract_docx(normal, huge_contract)

    def office_file(self, members: dict[str, str], suffix: str) -> pathlib.Path:
        handle = tempfile.NamedTemporaryFile(suffix=suffix, delete=False)
        handle.close()
        path = pathlib.Path(handle.name)
        with zipfile.ZipFile(path, "w") as archive:
            for name, content in members.items():
                archive.writestr(name, content)
        self.addCleanup(path.unlink, missing_ok=True)
        return path

    def plain_file(self, content: bytes, suffix: str) -> pathlib.Path:
        handle = tempfile.NamedTemporaryFile(suffix=suffix, delete=False)
        handle.write(content)
        handle.close()
        path = pathlib.Path(handle.name)
        self.addCleanup(path.unlink, missing_ok=True)
        return path

    def ocr_tsv(self, text: str, confidence: int) -> str:
        words = text.split()
        rows = [
            "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext",
            "1\t1\t0\t0\t0\t0\t0\t0\t1000\t1000\t-1\t",
        ]
        for index, word in enumerate(words, start=1):
            rows.append(f"5\t1\t1\t1\t1\t{index}\t100\t200\t400\t100\t{confidence}\t{word}")
        return "\n".join(rows)


if __name__ == "__main__":
    unittest.main()
