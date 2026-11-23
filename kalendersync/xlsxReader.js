async function parseXlsxRows(arrayBuffer) {
  const bytes = new Uint8Array(arrayBuffer);
  const view = new DataView(arrayBuffer);

  // Find End of Central Directory (0x06054b50)
  let eocd = -1;
  for (let p = bytes.length - 22; p >= Math.max(0, bytes.length - 65557); p--) {
    if (view.getUint32(p, true) === 0x06054b50) {
      eocd = p;
      break;
    }
  }
  if (eocd === -1) throw new Error("Ugyldig Excel-fil (fant ikke ZIP-katalog).");

  const cdOffset = view.getUint32(eocd + 16, true);
  const totalEntries = view.getUint16(eocd + 10, true);
  const entries = {};
  let ptr = cdOffset;
  for (let e = 0; e < totalEntries && ptr + 46 <= bytes.length; e++) {
    if (view.getUint32(ptr, true) !== 0x02014b50) break;
    const method = view.getUint16(ptr + 10, true);
    const compSize = view.getUint32(ptr + 20, true);
    const nameLen = view.getUint16(ptr + 28, true);
    const extraLen = view.getUint16(ptr + 30, true);
    const commentLen = view.getUint16(ptr + 32, true);
    const localOffset = view.getUint32(ptr + 42, true);
    const name = new TextDecoder().decode(bytes.subarray(ptr + 46, ptr + 46 + nameLen));
    ptr += 46 + nameLen + extraLen + commentLen;

    if (name === "xl/sharedStrings.xml" || name === "xl/worksheets/sheet1.xml") {
      const lNameLen = view.getUint16(localOffset + 26, true);
      const lExtraLen = view.getUint16(localOffset + 28, true);
      const dataStart = localOffset + 30 + lNameLen + lExtraLen;
      const compData = bytes.subarray(dataStart, dataStart + compSize);
      if (method === 0) {
        entries[name] = new TextDecoder().decode(compData);
      } else if (method === 8) {
        const ds = new DecompressionStream("deflate-raw");
        const writer = ds.writable.getWriter();
        writer.write(compData);
        writer.close();
        entries[name] = await new Response(ds.readable).text();
      }
    }
  }

  if (!entries["xl/worksheets/sheet1.xml"]) throw new Error("Fant ikke sheet1.xml i Excel-filen.");
  const parser = new DOMParser();
  const shared = [];
  if (entries["xl/sharedStrings.xml"]) {
    const sDoc = parser.parseFromString(entries["xl/sharedStrings.xml"], "text/xml");
    for (const si of sDoc.getElementsByTagNameNS("*", "si")) {
      shared.push(Array.from(si.getElementsByTagNameNS("*", "t")).map((t) => t.textContent).join(""));
    }
  }
  const sheetDoc = parser.parseFromString(entries["xl/worksheets/sheet1.xml"], "text/xml");
  const rows = [];
  for (const rowEl of sheetDoc.getElementsByTagNameNS("*", "row")) {
    const row = [];
    for (const c of rowEl.getElementsByTagNameNS("*", "c")) {
      const ref = c.getAttribute("r") || "";
      const colMatch = ref.match(/^([A-Z]+)/);
      let colIdx = row.length;
      if (colMatch) {
        colIdx = colMatch[1].split("").reduce((acc, ch) => acc * 26 + (ch.charCodeAt(0) - 64), 0) - 1;
      }
      while (row.length < colIdx) row.push("");
      const type = c.getAttribute("t");
      const vEl = c.getElementsByTagNameNS("*", "v")[0];
      const isEl = c.getElementsByTagNameNS("*", "is")[0];
      let val = "";
      if (type === "s" && vEl) val = shared[Number(vEl.textContent)] || "";
      else if (type === "inlineStr" && isEl) val = isEl.textContent || "";
      else if (vEl) val = vEl.textContent || "";
      row[colIdx] = String(val || "").trim();
    }
    rows.push(row);
  }
  return rows;
}
