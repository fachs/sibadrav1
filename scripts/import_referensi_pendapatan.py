"""Extract account/service/tariff references from the supplied workbook (read only)."""

import argparse
import hashlib
import json
import re
from decimal import Decimal
from pathlib import Path
from zipfile import ZipFile
import xml.etree.ElementTree as ET


def extract(source):
    ns = {"m": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}
    rel_ns = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id"
    with ZipFile(source) as archive:
        strings = ["".join(e.itertext()) for e in ET.fromstring(archive.read("xl/sharedStrings.xml")).findall("m:si", ns)]
        sheets = ET.fromstring(archive.read("xl/workbook.xml")).findall("m:sheets/m:sheet", ns)
        assert len(sheets) == 1, "Expected one reference worksheet"
        relationships = {r.attrib["Id"]: r.attrib["Target"] for r in ET.fromstring(archive.read("xl/_rels/workbook.xml.rels"))}
        target = relationships[sheets[0].attrib[rel_ns]]
        target = target.lstrip("/") if target.startswith("/") else "xl/" + target
        accounts = []
        seen = {}
        source_count = 0
        for row in ET.fromstring(archive.read(target)).findall("m:sheetData/m:row", ns):
            number = int(row.attrib["r"])
            values = {}
            for cell in row.findall("m:c", ns):
                value = cell.find("m:v", ns)
                text = value.text if value is not None else ""
                if cell.attrib.get("t") == "s":
                    text = strings[int(text)]
                elif cell.attrib.get("t") == "inlineStr":
                    text = "".join(cell.find("m:is", ns).itertext())
                values[re.sub(r"\d", "", cell.attrib["r"])] = re.sub(r"\s+", " ", text or "").strip()
            if number == 1 or not any(values.values()):
                continue
            if values.get("A"):
                assert values.get("B"), f"Missing account description at row {number}"
                assert values["A"] not in seen, f"Duplicate account at row {number}"
                account = {"kode_rek": values["A"], "uraian": values["B"], "baris_sumber": number, "pelayanan": []}
                accounts.append(account)
                seen[values["A"]] = {}
            if values.get("C"):
                source_count += 1
                assert accounts and values.get("D") != "", f"Incomplete service at row {number}"
                price = Decimal(values["D"])
                assert price >= 0 and price == price.quantize(Decimal("0.01")), f"Invalid tariff at row {number}"
                duplicate = seen[account["kode_rek"]].get(values["C"])
                if duplicate:
                    assert Decimal(duplicate["harga_satuan"]) == price, f"Conflicting tariffs at row {number}"
                    duplicate["baris_sumber"].append(number)
                else:
                    service = {"uraian": values["C"], "harga_satuan": str(price.quantize(Decimal("0.01"))), "baris_sumber": [number]}
                    account["pelayanan"].append(service)
                    seen[account["kode_rek"]][values["C"]] = service
            else:
                assert not values.get("D"), f"Tariff without service at row {number}"
        assert all(a["pelayanan"] for a in accounts), "An account has no services"
        return {"sumber": {"nama_file": source.name, "sheet": sheets[0].attrib["name"], "sha256": hashlib.sha256(source.read_bytes()).hexdigest(), "baris_pelayanan": source_count}, "rekening": accounts}


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("source", type=Path)
    parser.add_argument("output", type=Path)
    args = parser.parse_args()
    data = extract(args.source)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"rekening": len(data["rekening"]), "pelayanan_unik": sum(len(a["pelayanan"]) for a in data["rekening"]), "baris_sumber": data["sumber"]["baris_pelayanan"]}))
