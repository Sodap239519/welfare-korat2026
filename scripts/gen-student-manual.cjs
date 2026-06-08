const fs = require('fs');
const {
  Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
  AlignmentType, LevelFormat, HeadingLevel, BorderStyle, WidthType, ShadingType,
  TableOfContents, PageNumber, Header, Footer, PageBreak, VerticalAlign,
} = require('docx');

const FONT = 'TH Sarabun New';
const BODY = 30;        // 15pt
const CW = 9026;        // content width A4 - 1" margins

// ── numbering refs ──
const numConfig = [
  { reference: 'bullets', levels: [{ level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 460, hanging: 260 } } } }] },
];
for (let i = 0; i < 30; i++) {
  numConfig.push({ reference: 'n' + i, levels: [{ level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 460, hanging: 280 } } } }] });
}
let nIdx = 0;
const nextNum = () => 'n' + (nIdx++);

// ── helpers ──
const h1 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun(t)] });
const h2 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun(t)] });
const h3 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_3, children: [new TextRun(t)] });
const p = (t, opts = {}) => new Paragraph({ spacing: { after: 80 }, children: runs(t), ...opts });
const bullet = (t) => new Paragraph({ numbering: { reference: 'bullets', level: 0 }, spacing: { after: 40 }, children: runs(t) });
const mkNum = (ref) => (t) => new Paragraph({ numbering: { reference: ref, level: 0 }, spacing: { after: 40 }, children: runs(t) });
const spacer = () => new Paragraph({ children: [new TextRun('')], spacing: { after: 60 } });

// runs: supports **bold** segments
function runs(t) {
  const parts = String(t).split('**');
  return parts.map((seg, i) => new TextRun({ text: seg, bold: i % 2 === 1 }));
}

function table(headers, rows, widths) {
  const border = { style: BorderStyle.SINGLE, size: 1, color: 'BBBBBB' };
  const borders = { top: border, bottom: border, left: border, right: border };
  const w = widths || headers.map(() => Math.floor(CW / headers.length));
  const headRow = new TableRow({
    tableHeader: true,
    children: headers.map((hh, i) => new TableCell({
      borders, width: { size: w[i], type: WidthType.DXA },
      shading: { fill: '1D4ED8', type: ShadingType.CLEAR },
      margins: { top: 60, bottom: 60, left: 110, right: 110 },
      verticalAlign: VerticalAlign.CENTER,
      children: [new Paragraph({ children: [new TextRun({ text: hh, bold: true, color: 'FFFFFF' })] })],
    })),
  });
  const bodyRows = rows.map((r, ri) => new TableRow({
    children: r.map((c, i) => new TableCell({
      borders, width: { size: w[i], type: WidthType.DXA },
      shading: { fill: ri % 2 ? 'EEF4FF' : 'FFFFFF', type: ShadingType.CLEAR },
      margins: { top: 50, bottom: 50, left: 110, right: 110 },
      children: [new Paragraph({ children: runs(c) })],
    })),
  }));
  return new Table({ width: { size: CW, type: WidthType.DXA }, columnWidths: w, rows: [headRow, ...bodyRows] });
}

function callout(title, lines) {
  const border = { style: BorderStyle.SINGLE, size: 1, color: 'F59E0B' };
  return new Table({
    width: { size: CW, type: WidthType.DXA }, columnWidths: [CW],
    rows: [new TableRow({ children: [new TableCell({
      borders: { top: border, bottom: border, left: { style: BorderStyle.SINGLE, size: 18, color: 'F59E0B' }, right: border },
      shading: { fill: 'FFF7ED', type: ShadingType.CLEAR },
      margins: { top: 100, bottom: 100, left: 160, right: 140 },
      children: [
        new Paragraph({ spacing: { after: 40 }, children: [new TextRun({ text: title, bold: true, color: '92400E' })] }),
        ...lines.map(l => new Paragraph({ spacing: { after: 20 }, children: runs(l) })),
      ],
    })] })],
  });
}

const children = [];

// ───────────────────────── TITLE PAGE ─────────────────────────
children.push(
  new Paragraph({ spacing: { before: 1800, after: 0 }, alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'Welfare Korat 2026', bold: true, size: 30, color: '1D4ED8' })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'บัตรสวัสดิการแห่งรัฐ 2569', size: 24, color: '64748B' })] }),
  new Paragraph({ spacing: { before: 700, after: 0 }, alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'คู่มือการใช้งานระบบ', bold: true, size: 56 })] }),
  new Paragraph({ spacing: { before: 80 }, alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'สำหรับนักศึกษา', bold: true, size: 56, color: '1D4ED8' })] }),
  new Paragraph({ spacing: { before: 200 }, alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'โมดูลรายงานการปฏิบัติงานภาคสนาม', size: 30, color: '334155' })] }),
  new Paragraph({ spacing: { before: 900 }, alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'ระบบติดตามการลงทะเบียนบัตรสวัสดิการแห่งรัฐ 2569', size: 26 })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'จังหวัดนครราชสีมา', size: 26 })] }),
  new Paragraph({ spacing: { before: 120 }, alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'มหาวิทยาลัยราชภัฏนครราชสีมา', size: 26, color: '64748B' })] }),
  new Paragraph({ spacing: { before: 1400 }, alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'ฉบับปรับปรุง มิถุนายน 2569', size: 24, color: '94A3B8' })] }),
  new Paragraph({ children: [new PageBreak()] }),
);

// ───────────────────────── TOC ─────────────────────────
children.push(
  new Paragraph({ spacing: { after: 160 }, children: [new TextRun({ text: 'สารบัญ', bold: true, size: 36 })] }),
  new TableOfContents('สารบัญ', { hyperlink: true, headingStyleRange: '1-2' }),
  new Paragraph({ children: [new PageBreak()] }),
);

// ───────────────────────── 1. ภาพรวม ─────────────────────────
children.push(h1('1. ภาพรวม — นักศึกษาทำอะไรในระบบ'));
children.push(p('นักศึกษาเป็น **ทีมลงพื้นที่ปฏิบัติงานหนุนเสริม** ช่วยประชาชนลงทะเบียนบัตรสวัสดิการแห่งรัฐ ณ จุดบริการเคลื่อนที่ / วันลงทะเบียนรวมหมู่บ้าน หรือที่ธนาคาร แล้ว **บันทึกผลการปฏิบัติงานเข้าระบบ** เพื่อรวบรวมเป็นรายงานของโครงการ'));
children.push(spacer());
children.push(h2('เมนูของนักศึกษา (4 เมนู)'));
children.push(table(
  ['เมนู', 'ใช้ทำอะไร'],
  [
    ['บันทึกการปฏิบัติงาน', 'บันทึกงานที่ทำในแต่ละวัน (งานหลัก) — มี GPS, แนบไฟล์, กรณีปัญหา'],
    ['ประเมินตนเอง', 'ทำแบบประเมินตนเอง 1 ครั้ง หลังปฏิบัติงานเสร็จ'],
    ['สรุปงานของฉัน', 'ดูสถิติ/กราฟผลงานของตัวเอง'],
    ['เอกสารสำหรับนักศึกษา', 'ดาวน์โหลดแบบฟอร์ม/คู่มือ (Google Drive)'],
  ],
  [2800, 6226],
));
children.push(spacer());
children.push(callout('สิ่งที่นักศึกษาทำไม่ได้', [
  '• ดูบันทึกของนักศึกษาคนอื่น (เห็นเฉพาะของตัวเอง)',
  '• จัดการบัญชีผู้ใช้ / ตั้งค่าระบบ / นำเข้าข้อมูล Excel',
]));

// ───────────────────────── 2. สมัครสมาชิก ─────────────────────────
children.push(h1('2. การสมัครสมาชิก (นักศึกษา)'));
children.push(p('เข้าเว็บไซต์ → ที่หน้าเข้าสู่ระบบ กดแท็บ **"ลงทะเบียน"** → เลือกประเภทบัญชี **"นักศึกษา"** (ปุ่มด้านขวา)'));
children.push(spacer());
children.push(h2('ข้อมูลที่ต้องกรอก'));
children.push(h3('ข้อมูลพื้นฐาน (บังคับ *)'));
[
  '**ชื่อ-สกุล** — ชื่อจริงของนักศึกษา',
  '**เบอร์โทร** — ใช้เป็น Username เข้าระบบ (9–10 หลัก เช่น 0812345678)',
  '**รหัสนักศึกษา** — รหัสประจำตัวนักศึกษา',
  '**คณะ** — คณะที่สังกัด',
  '**สาขาวิชา** — สาขาวิชาที่เรียน',
  '**รหัสผ่าน** + **ยืนยันรหัสผ่าน** — อย่างน้อย 6 ตัวอักษร',
].forEach(t => children.push(bullet(t)));
children.push(h3('ข้อมูลเพิ่มเติม (ไม่บังคับ)'));
['**ID LINE** — สำหรับติดต่อ', '**อีเมล**'].forEach(t => children.push(bullet(t)));

children.push(h3('เลือกหน่วยปฏิบัติงาน (บังคับ — เลือก 1 ใน 2)'));
children.push(table(
  ['หน่วยปฏิบัติงาน', 'ต้องเลือก/กรอกเพิ่ม'],
  [
    ['🏛️ ที่ว่าการอำเภอ', 'เลือก "อำเภอ" ที่ปฏิบัติงาน'],
    ['🏦 ธนาคาร', 'เลือกธนาคาร (กรุงไทย / ออมสิน / ธ.ก.ส. / อาคารสงเคราะห์ / อิสลาม) + กรอกชื่อสาขา'],
  ],
  [2800, 6226],
));
children.push(spacer());
let nref = nextNum();
children.push(p('**ขั้นตอนสรุป:**'));
['กรอกข้อมูลให้ครบ', 'เลือกหน่วยปฏิบัติงาน (อำเภอ หรือ ธนาคาร)', 'กด "ลงทะเบียน"', 'รอ Super Admin อนุมัติบัญชี → จึงจะ login เข้าใช้งานได้'].forEach(t => children.push(mkNum(nref)(t)));
children.push(spacer());
children.push(callout('สำคัญ', ['บัญชีใหม่ต้องรอ **Super Admin อนุมัติ** ก่อน จึงจะเข้าใช้งานได้ — ถ้ายัง login ไม่ได้ ให้ติดต่ออาจารย์/ผู้ดูแลโครงการ']));

// ───────────────────────── 3. เข้าสู่ระบบ ─────────────────────────
children.push(h1('3. การเข้าสู่ระบบ'));
nref = nextNum();
['ที่หน้าเข้าสู่ระบบ กรอก **เบอร์โทร** (ที่ใช้สมัคร) และ **รหัสผ่าน**', 'กด **"เข้าสู่ระบบ"**', 'ระบบจะพาไปหน้า **"บันทึกการปฏิบัติงาน"** อัตโนมัติ'].forEach(t => children.push(mkNum(nref)(t)));
children.push(spacer());
children.push(p('บนมือถือ เมนูหลักอยู่แถบล่าง: **บันทึก · สรุปงาน · เอกสาร · ประเมิน**'));

// ───────────────────────── 4. บันทึกการปฏิบัติงาน ─────────────────────────
children.push(new Paragraph({ pageBreakBefore: true, heading: HeadingLevel.HEADING_1, children: [new TextRun('4. บันทึกการปฏิบัติงาน (งานหลัก)')] }));
children.push(p('บันทึกผลงานที่ทำในแต่ละวัน — **1 บันทึกต่อ 1 วัน** หน้าแรกแสดงรายการบันทึกที่ผ่านมา กดปุ่ม **"เพิ่มบันทึกวันนี้"** เพื่อสร้างใหม่'));
children.push(spacer());
children.push(callout('GPS บังคับ ⚠️', ['บันทึกจะ **บันทึกไม่ได้** ถ้ายังไม่ได้ระบุตำแหน่ง GPS — ต้องกด "ระบุตำแหน่ง" และอนุญาตให้เบราว์เซอร์เข้าถึงตำแหน่งก่อน']));
children.push(spacer());

children.push(h2('กรอกข้อมูลในบันทึก (ทีละส่วน)'));

children.push(h3('ส่วนที่ 1 · ตำแหน่งการปฏิบัติงาน (GPS) *'));
['กดปุ่ม **"ระบุตำแหน่ง"** → อนุญาตการเข้าถึงตำแหน่ง', 'ระบบแสดงพิกัด + ความแม่นยำ (เมตร) + สถานะ "ปกติ" หรือ "สัญญาณอ่อน"', 'กด **"ดูแผนที่"** เพื่อตรวจตำแหน่งบน Google Maps · กด **"ระบุใหม่"** ถ้าต้องการอัปเดต'].forEach(t => children.push(bullet(t)));
children.push(p('ถ้าถูกปฏิเสธ GPS: เปิดสิทธิ์ตำแหน่งใน Settings ของเบราว์เซอร์แล้วลองใหม่', { spacing: { after: 100 } }));

children.push(h3('ส่วนที่ 2 · วันที่และเวลา'));
['**วันที่ปฏิบัติงาน** (บังคับ) — ค่าเริ่มต้น = วันนี้', '**เวลาเริ่ม / เวลาสิ้นสุด** (ไม่บังคับ)'].forEach(t => children.push(bullet(t)));

children.push(h3('ส่วนที่ 3 · กิจกรรมที่ดำเนินการ'));
children.push(p('เพิ่มได้หลายแถว (กด "เพิ่มกิจกรรม") แต่ละแถวกรอก:'));
children.push(table(
  ['ช่อง', 'รายละเอียด'],
  [
    ['ช่วงเวลา', 'เช้า / บ่าย / เพิ่มเติม'],
    ['ประเภทกิจกรรม', 'เช่น ให้คำแนะนำการลงทะเบียน · ช่วยกรอกข้อมูล · ตรวจสอบเอกสาร · คัดกรองกลุ่มตกหล่น'],
    ['รายละเอียด', 'อธิบายสั้นๆ'],
    ['จำนวนผู้รับบริการ', 'กี่ราย'],
  ],
  [2400, 6626],
));
children.push(p('ระบบรวม **"ผู้รับบริการรวม"** จากทุกกิจกรรมให้อัตโนมัติ', { spacing: { before: 60, after: 100 } }));

children.push(h3('ส่วนที่ 4 · ผลการลงทะเบียน'));
['**ลงทะเบียนสำเร็จ** (ราย)', '**ลงทะเบียนไม่สำเร็จ** (ราย)'].forEach(t => children.push(bullet(t)));
children.push(callout('ตรวจสอบอัตโนมัติ', ['สำเร็จ + ไม่สำเร็จ ต้อง **เท่ากับ** ผู้รับบริการรวมพอดี — ถ้าตรงจะขึ้นสีเขียว ถ้าไม่ตรงขึ้นสีส้มเตือน']));

children.push(h3('ส่วนที่ 5 · ไฟล์แนบ (3 หมวด · สูงสุด 10 ไฟล์/หมวด)'));
children.push(table(
  ['หมวด', 'ชนิดไฟล์', 'ปุ่ม'],
  [
    ['📋 ใบบันทึกการปฏิบัติงาน', 'PDF / รูป / Doc / Excel', 'เลือกไฟล์ / ถ่ายรูป'],
    ['🧾 เอกสารเบิกจ่าย', 'PDF / รูป / Doc / Excel', 'เลือกไฟล์'],
    ['📷 ภาพการปฏิบัติงาน', 'รูปภาพเท่านั้น', 'ถ่ายรูป / คลังภาพ'],
  ],
  [3400, 3226, 2400],
));

children.push(h3('ส่วนที่ 6 · ปัญหาการลงทะเบียนรายกรณี'));
children.push(p('สำหรับคนที่ลงทะเบียน **ไม่สำเร็จ** — เพิ่มทีละแถว: ชื่อ-สกุล · เบอร์โทร · หมู่บ้าน/ตำบล · **ปัญหาที่เกิดขึ้น** (เพื่อให้ทีมตามแก้ต่อ)'));

children.push(h3('ส่วนที่ 7 · ผู้ควบคุมงาน (ไม่บังคับ)'));
children.push(p('ชื่อ-สกุล · ตำแหน่ง · วันที่ ของผู้ควบคุมงาน/พี่เลี้ยงในพื้นที่'));
children.push(spacer());
children.push(p('เมื่อครบแล้วกด **"บันทึก"** (สีน้ำเงิน) — แก้ไขภายหลังได้ที่ปุ่ม ✏️ และลบได้ที่ปุ่ม 🗑️ ในรายการ'));

// ───────────────────────── 5. ประเมินตนเอง ─────────────────────────
children.push(new Paragraph({ pageBreakBefore: true, heading: HeadingLevel.HEADING_1, children: [new TextRun('5. ประเมินตนเอง')] }));
children.push(p('ทำ **1 ครั้ง** หลังปฏิบัติงานเสร็จ (แก้ไขได้) — เมนู "ประเมินตนเอง" แล้วกด **"บันทึกแบบประเมิน"**'));
children.push(spacer());
children.push(table(
  ['ข้อ', 'หัวข้อ', 'รูปแบบ'],
  [
    ['1', 'ระดับความเข้าใจงาน', 'เลือก 1 (มาก/ปานกลาง/น้อย)'],
    ['2', 'ทักษะที่ได้รับ', 'เลือกได้หลายข้อ'],
    ['3', 'ลักษณะกลุ่มประชาชนที่พบส่วนใหญ่', 'เลือกได้หลายข้อ'],
    ['4', 'บทเรียนที่ได้รับ', 'พิมพ์ข้อความ'],
    ['5', 'ข้อสังเกตการเข้าถึงเทคโนโลยีของประชาชน', 'หลายข้อย่อย (5.1–5.6)'],
  ],
  [800, 5826, 2400],
));
children.push(p('ข้อ 5 ครอบคลุม: ระดับการใช้เทคโนโลยี · การพึ่งพาผู้อื่น · ปัญหาที่พบ · บทบาทนักศึกษา · ผลลัพธ์การเข้าถึง · และถ้าไม่มีนักศึกษาช่วยจะเป็นอย่างไร', { spacing: { before: 60 } }));

// ───────────────────────── 6. สรุปงานของฉัน ─────────────────────────
children.push(h1('6. สรุปงานของฉัน'));
children.push(p('ดูภาพรวมผลงานของตัวเอง — การ์ดสถิติ + กราฟ'));
children.push(table(
  ['การ์ด', 'ความหมาย'],
  [
    ['วันที่ปฏิบัติงาน', 'จำนวนวันที่บันทึก'],
    ['ผู้รับบริการรวม', 'รวมทุกวัน'],
    ['ลงทะเบียนสำเร็จ', 'จำนวนสำเร็จ'],
    ['ไม่สำเร็จ', 'จำนวนไม่สำเร็จ'],
    ['กรณีปัญหา', 'จำนวนเคสที่มีปัญหา'],
  ],
  [3000, 6026],
));
children.push(spacer());
children.push(bullet('**กราฟแนวโน้มผู้รับบริการรายวัน** (เส้น/พื้นที่)'));
children.push(bullet('**กราฟสัดส่วนตามประเภทกิจกรรม** (โดนัท)'));
children.push(bullet('ถ้ายังไม่ได้ทำแบบประเมินตนเอง จะมีแถบสีส้มเตือน + ลิงก์ไปทำ'));

// ───────────────────────── 7. เอกสาร ─────────────────────────
children.push(h1('7. เอกสารสำหรับนักศึกษา'));
children.push(p('รวมแบบฟอร์ม/คู่มือใน Google Drive — กดปุ่ม **"เปิดใน Google Drive"** เพื่อเข้าโฟลเดอร์ดาวน์โหลดเอกสารที่ต้องใช้'));

// ───────────────────────── 8. ข้อควรจำ ─────────────────────────
children.push(h1('8. ข้อควรจำ + เคล็ดลับภาคสนาม'));
[
  '**เปิด GPS ก่อนเสมอ** — บันทึกไม่ได้ถ้าไม่มีพิกัด · เปิดสิทธิ์ตำแหน่งในเบราว์เซอร์ไว้',
  '**ตัวเลขต้องตรง** — สำเร็จ + ไม่สำเร็จ = ผู้รับบริการรวม',
  '**ถ่ายรูปหน้างานทันที** — แนบในหมวด "ภาพการปฏิบัติงาน" ระบบเก็บพิกัดให้',
  '**คนที่ไม่สำเร็จ ให้บันทึกกรณีปัญหา** — ทีมจะตามแก้ต่อได้',
  '**บันทึกทุกวันที่ลงพื้นที่** — 1 วัน 1 บันทึก กรอกตอนจบงานเลยจะจำได้ครบ',
  '**อย่าลืมประเมินตนเอง** เมื่อจบโครงการ',
].forEach(t => children.push(bullet(t)));

// ───────────────────────── 9. ปัญหาที่พบบ่อย ─────────────────────────
children.push(h1('9. ปัญหาที่พบบ่อย + วิธีแก้'));
children.push(table(
  ['ปัญหา', 'วิธีแก้'],
  [
    ['login ไม่ได้', 'บัญชียังไม่ได้รับอนุมัติ — ติดต่ออาจารย์/ผู้ดูแลโครงการ'],
    ['กด "ระบุตำแหน่ง" แล้วไม่ขึ้นพิกัด', 'เปิดสิทธิ์ "ตำแหน่ง/Location" ในเบราว์เซอร์ แล้วกดใหม่ · ออกที่โล่งสัญญาณดีขึ้น'],
    ['บันทึกไม่ได้ (ปุ่มกดไม่ผ่าน)', 'ตรวจ GPS + ตัวเลขสำเร็จ/ไม่สำเร็จให้ตรงผู้รับบริการรวม'],
    ['แนบไฟล์ไม่ได้', 'แต่ละหมวดสูงสุด 10 ไฟล์ · หมวดภาพรับเฉพาะรูป'],
    ['ลืมรหัสผ่าน', 'ติดต่อผู้ดูแลระบบ (ไม่มีรีเซ็ตเอง)'],
  ],
  [3200, 5826],
));

// ───────────────────────── 10. ติดต่อ ─────────────────────────
children.push(h1('10. ติดต่อ'));
children.push(p('หากพบปัญหาการใช้งานหรือบัญชีถูกระงับ ติดต่อ **อาจารย์ผู้ดูแลโครงการ / ผู้ดูแลระบบ (Super Admin)**'));
children.push(spacer());
children.push(p('— จบคู่มือ —', { alignment: AlignmentType.CENTER }));

// ───────────────────────── BUILD ─────────────────────────
const doc = new Document({
  styles: {
    default: { document: { run: { font: FONT, size: BODY } } },
    paragraphStyles: [
      { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true,
        run: { size: 38, bold: true, font: FONT, color: '1D4ED8' },
        paragraph: { spacing: { before: 280, after: 140 }, outlineLevel: 0 } },
      { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true,
        run: { size: 32, bold: true, font: FONT, color: '0F172A' },
        paragraph: { spacing: { before: 180, after: 90 }, outlineLevel: 1 } },
      { id: 'Heading3', name: 'Heading 3', basedOn: 'Normal', next: 'Normal', quickFormat: true,
        run: { size: 30, bold: true, font: FONT, color: '1E40AF' },
        paragraph: { spacing: { before: 120, after: 60 }, outlineLevel: 2 } },
    ],
  },
  numbering: { config: numConfig },
  sections: [{
    properties: { page: { size: { width: 11906, height: 16838 }, margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 } } },
    footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'คู่มือนักศึกษา · Welfare Korat 2026   |   หน้า ', size: 22, color: '94A3B8' }), new TextRun({ children: [PageNumber.CURRENT], size: 22, color: '94A3B8' })] })] }) },
    children,
  }],
});

Packer.toBuffer(doc).then(buf => {
  const out = 'docs/manual/คู่มือการใช้งานสำหรับนักศึกษา.docx';
  fs.writeFileSync(out, buf);
  console.log('WROTE', out, buf.length, 'bytes');
});
