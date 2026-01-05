#!/usr/bin/env node
const fs = require('fs');
const { PDFDocument } = require('pdf-lib');

(async () => {
  const [,, inA, inB, out] = process.argv;

  if (!inA || !inB || !out) {
    console.error('Usage: node merge-pdf.cjs <a.pdf> <b.pdf> <out.pdf>');
    process.exit(2);
  }

  try {
    const aBytes = fs.readFileSync(inA);
    const bBytes = fs.readFileSync(inB);

    const aDoc = await PDFDocument.load(aBytes);
    const bDoc = await PDFDocument.load(bBytes);

    const merged = await PDFDocument.create();

    const aPages = await merged.copyPages(aDoc, aDoc.getPageIndices());
    aPages.forEach(p => merged.addPage(p));

    const bPages = await merged.copyPages(bDoc, bDoc.getPageIndices());
    bPages.forEach(p => merged.addPage(p));

    const outBytes = await merged.save();
    fs.writeFileSync(out, outBytes);
  } catch (e) {
    console.error(e && e.stack ? e.stack : String(e));
    process.exit(1);
  }
})();
