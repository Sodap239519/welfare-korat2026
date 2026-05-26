// SOP defaults ตามแผนปฏิบัติการในเอกสารโครงการ
// ใช้เป็น fallback เมื่อ phase.details ใน DB ยังว่าง
// Super Admin สามารถแก้/ลบ/เพิ่มได้ผ่านหน้า Settings → ขั้นตอน SOP

export const DEFAULT_SOP_DETAILS = {
  1: {
    color: 'blue', title: 'วิเคราะห์เกณฑ์ผู้มีสิทธิ์',
    summary: 'มรก.มม. ส่ง Briefing 1 หน้า · เกณฑ์สิทธิ + เอกสารที่ต้องเตรียม',
    bullets: [
      { icon: 'fi-rr-id-card-clip-alt', text: 'บัตรประชาชน Smart Card' },
      { icon: 'fi-rr-fingerprint',      text: 'Laser ID หลังบัตร' },
      { icon: 'fi-rr-coins',            text: 'ข้อมูลรายได้ / ทรัพย์สิน' },
      { icon: 'fi-rr-list-check',       text: 'ข้อมูลตามเกณฑ์ที่กำหนด' },
    ],
    footer: 'ให้ทุก อปท./รพ.สต./กำนัน ภายใน 10 วัน ผ่านไลน์กลุ่ม "บัตรสวัสดิการแห่งรัฐ โคราช"',
  },
  2: {
    color: 'sky', title: 'ส่งรายชื่อกลุ่มเป้าหมาย',
    summary: 'มรก.มม. + DSS ส่ง "รายชื่อรายอำเภอ/ตำบล/หมู่บ้าน" พร้อมเลข 13 หลัก',
    bullets: [
      { icon: 'fi-rr-id-card-clip-alt', text: 'เลข 13 หลัก' },
      { icon: 'fi-rr-marker',           text: 'พิกัด GPS' },
      { icon: 'fi-rr-phone-call',       text: 'เบอร์ติดต่อ' },
      { icon: 'fi-rr-chart-pie-alt',    text: 'สถานะ MPI' },
    ],
    footer: 'นำเข้าระบบ NOAH + แบบฟอร์มที่ มรก. ออกแบบให้',
  },
  3: {
    color: 'orange', title: 'จัดเวทีชี้แจงกลุ่มเป้าหมาย',
    summary: 'จัด "เวที 1 ตำบล 1 ครั้ง" ภายใน 10 วันของเดือนมิถุนายน',
    bullets: [
      { icon: 'fi-rr-info',          text: 'ให้ความรู้สิทธิประโยชน์ + ขั้นตอน + เอกสาร' },
      { icon: 'fi-rr-comments',      text: 'การให้คำปรึกษาเฉพาะราย' },
      { icon: 'fi-rr-house-chimney', text: 'เยี่ยมบ้านผู้พิการ/ผู้สูงอายุที่มาเวทีไม่ได้' },
    ],
    footer: 'ใช้โค้ชทีมเดินสาย: นักศึกษา · รพ.สต. · อสม. · สภาองค์กรชุมชน',
  },
  4: {
    color: 'purple', title: 'กลไกลงทะเบียน',
    summary: '3 จุดบริการ ครอบคลุมทุกกลุ่ม — ประจำ / เคลื่อนที่ / เยี่ยมบ้าน',
    bullets: [
      { icon: 'fi-rr-building',    text: 'จุดบริการประจำ — เปิดศูนย์ที่ อบต./เทศบาล ทุกวันราชการ' },
      { icon: 'fi-rr-ambulance',   text: 'จุดบริการเคลื่อนที่ — ทีมนักศึกษา + ธนาคารกรุงไทย CRM ลงพื้นที่ "วันลงทะเบียนรวมหมู่บ้าน" สัปดาห์ละ 2 หมู่/ตำบล' },
      { icon: 'fi-rr-hand-holding-heart', text: 'ทีมพิเศษเยี่ยมบ้าน — สำหรับผู้พิการติดเตียง/ผู้สูงอายุ มีเอกสารมอบอำนาจสำเร็จรูป' },
    ],
    footer: '',
  },
  5: {
    color: 'green', title: 'ติดตามและประเมินผล',
    summary: 'Dashboard ของ มรก.มม. รายงานสถานะรายบุคคล เรียลไทม์',
    bullets: [
      { icon: 'fi-rr-chart-pie',    text: 'รายงานสรุปยอดรายหมู่บ้าน · ทุกวัน' },
      { icon: 'fi-rr-calendar',     text: 'ส่งรายงานให้คลังจังหวัด · ทุกศุกร์ 16:30' },
      { icon: 'fi-rr-user-headset', text: 'ทีม CRM ตามผู้ที่ลงทะเบียนแล้วแต่ยังไม่ยืนยันตัวตน' },
      { icon: 'fi-rr-search-alt',   text: 'วิเคราะห์ Bottleneck รายสัปดาห์เพื่อปรับกลไก' },
    ],
    footer: '',
  },
};

/**
 * คืนค่า details ที่ใช้จริง — DB ก่อน, fallback ไปยัง default ตาม sop_level
 * @param {object} phase - phase object จาก API
 * @returns {object} { summary, footer, bullets: [{icon, text, subtitle?, count?}] }
 */
export function effectiveSopDetails(phase) {
  if (!phase) return { summary: '', footer: '', bullets: [] };
  if (phase.details && (phase.details.summary || phase.details.footer || phase.details.bullets?.length)) {
    return {
      summary: phase.details.summary || '',
      footer:  phase.details.footer  || '',
      bullets: Array.isArray(phase.details.bullets) ? phase.details.bullets : [],
    };
  }
  const def = DEFAULT_SOP_DETAILS[phase.sop_level];
  if (!def) return { summary: '', footer: '', bullets: [] };
  return {
    summary: def.summary || '',
    footer:  def.footer  || '',
    bullets: Array.isArray(def.bullets) ? def.bullets : [],
  };
}
