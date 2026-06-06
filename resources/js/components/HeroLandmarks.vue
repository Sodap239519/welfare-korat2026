<script setup>
// ลายพื้นหลัง Landmark จังหวัดนครราชสีมา — ย่าโม+ประตูชุมพล · ปราสาทหินพิมาย · เขาใหญ่ · ด่านเกวียน
</script>

<template>
  <svg class="absolute inset-x-0 bottom-0 w-full pointer-events-none"
       viewBox="0 0 800 480" preserveAspectRatio="xMidYEnd slice"
       xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="height: 70%;">
    <!-- Layer 1: เขาใหญ่ -->
    <g stroke="#67e8f9" stroke-width="1.2" fill="none" opacity="0.22">
      <path d="M 0,340 L 60,260 L 130,310 L 210,220 L 290,290 L 370,200 L 450,280 L 540,210 L 620,290 L 710,240 L 800,310"/>
      <path d="M 0,380 L 90,310 L 180,360 L 280,270 L 380,340 L 480,260 L 580,330 L 680,280 L 800,360"/>
    </g>

    <!-- Layer 2: ปราสาทหินพิมาย -->
    <g stroke="#a5f3fc" stroke-width="1.6" fill="none" opacity="0.55" transform="translate(540,180)">
      <path d="M -3,200 L -3,80 Q -3,30 0,15 Q 3,30 3,80 L 3,200 Z" fill="rgba(165,243,252,0.08)"/>
      <path d="M -20,200 L -20,100 Q -20,40 -10,25 Q 0,15 10,25 Q 20,40 20,100 L 20,200 Z" fill="rgba(165,243,252,0.05)"/>
      <path d="M -35,200 L -35,140 Q -35,90 -25,75 Q -15,65 0,60 Q 15,65 25,75 Q 35,90 35,140 L 35,200 Z" fill="rgba(165,243,252,0.04)"/>
      <rect x="-50" y="200" width="100" height="15"/>
      <rect x="-58" y="215" width="116" height="20"/>
      <rect x="-65" y="235" width="130" height="25"/>
      <line x1="0" y1="15" x2="0" y2="-5"/>
      <circle cx="0" cy="-10" r="3" fill="rgba(165,243,252,0.4)"/>
      <line x1="-20" y1="80" x2="20" y2="80"/>
      <line x1="-25" y1="110" x2="25" y2="110"/>
      <line x1="-30" y1="140" x2="30" y2="140"/>
      <line x1="-35" y1="170" x2="35" y2="170"/>
    </g>

    <!-- Layer 3: ประตูชุมพล + อนุสาวรีย์ย่าโม -->
    <g stroke="#a5f3fc" stroke-width="1.6" fill="none" opacity="0.55" transform="translate(220,200)">
      <g transform="translate(0,-30)">
        <ellipse cx="0" cy="-10" rx="6" ry="8" fill="rgba(165,243,252,0.15)"/>
        <path d="M -3,-2 L 3,-2 L 11,35 L -11,35 Z" fill="rgba(165,243,252,0.12)"/>
        <line x1="11" y1="5" x2="20" y2="-15"/>
        <line x1="20" y1="-15" x2="20" y2="-25"/>
        <rect x="-18" y="35" width="36" height="55" fill="rgba(165,243,252,0.06)"/>
        <line x1="-18" y1="50" x2="18" y2="50"/>
        <line x1="-18" y1="75" x2="18" y2="75"/>
        <rect x="-25" y="90" width="50" height="8"/>
        <rect x="-30" y="98" width="60" height="12"/>
      </g>
      <g transform="translate(0,90)">
        <rect x="-90" y="0" width="40" height="80" fill="rgba(165,243,252,0.08)"/>
        <path d="M -95,0 L -70,-25 L -45,0 Z" fill="rgba(165,243,252,0.1)"/>
        <line x1="-70" y1="-25" x2="-70" y2="-32"/>
        <circle cx="-70" cy="-35" r="2" fill="rgba(165,243,252,0.3)"/>
        <rect x="-80" y="20" width="20" height="25"/>
        <line x1="-70" y1="20" x2="-70" y2="45"/>
        <rect x="50" y="0" width="40" height="80" fill="rgba(165,243,252,0.08)"/>
        <path d="M 45,0 L 70,-25 L 95,0 Z" fill="rgba(165,243,252,0.1)"/>
        <line x1="70" y1="-25" x2="70" y2="-32"/>
        <circle cx="70" cy="-35" r="2" fill="rgba(165,243,252,0.3)"/>
        <rect x="60" y="20" width="20" height="25"/>
        <line x1="70" y1="20" x2="70" y2="45"/>
        <path d="M -50,80 L -50,40 Q -50,15 0,15 Q 50,15 50,40 L 50,80 Z" fill="rgba(165,243,252,0.06)"/>
        <line x1="-50" y1="40" x2="50" y2="40"/>
        <path d="M -55,0 L 0,-15 L 55,0 Z" fill="rgba(165,243,252,0.1)"/>
      </g>
    </g>

    <!-- Layer 4: ตึกเมือง + หอนาฬิกา -->
    <g stroke="#bae6fd" stroke-width="1.2" fill="none" opacity="0.4" transform="translate(700,280)">
      <rect x="-30" y="0" width="20" height="90" fill="rgba(186,230,253,0.06)"/>
      <rect x="-30" y="0" width="20" height="6"/>
      <line x1="-30" y1="25" x2="-10" y2="25"/>
      <line x1="-30" y1="45" x2="-10" y2="45"/>
      <line x1="-30" y1="65" x2="-10" y2="65"/>
      <rect x="-5" y="-20" width="18" height="110" fill="rgba(186,230,253,0.06)"/>
      <circle cx="4" cy="-5" r="6"/>
      <line x1="4" y1="-9" x2="4" y2="-5"/>
      <line x1="4" y1="-5" x2="7" y2="-5"/>
    </g>

    <!-- Layer 5: หม้อด่านเกวียน -->
    <g stroke="#bae6fd" stroke-width="1.4" fill="none" opacity="0.35" transform="translate(80,330)">
      <ellipse cx="0" cy="0" rx="28" ry="6"/>
      <path d="M -28,0 Q -38,40 -20,75 L 20,75 Q 38,40 28,0"/>
      <ellipse cx="0" cy="75" rx="20" ry="4"/>
      <path d="M -32,20 Q 0,28 32,20" stroke-dasharray="2 3"/>
      <path d="M -34,40 Q 0,48 34,40" stroke-dasharray="2 3"/>
    </g>

    <!-- Layer 6: คลื่นลำตะคอง -->
    <g stroke="#ffffff" stroke-width="1" fill="none" opacity="0.18">
      <path d="M 0,430 Q 200,415 400,430 T 800,430"/>
      <path d="M 0,450 Q 250,470 500,450 T 800,450"/>
      <path d="M 0,470 Q 200,460 400,475 T 800,470"/>
    </g>

    <!-- Layer 7: ดาว -->
    <g fill="#ffffff" opacity="0.4">
      <circle cx="100" cy="70" r="1"/>
      <circle cx="180" cy="40" r="1.2"/>
      <circle cx="350" cy="60" r="0.8"/>
      <circle cx="500" cy="80" r="1"/>
      <circle cx="630" cy="50" r="0.8"/>
      <circle cx="730" cy="100" r="1"/>
    </g>
  </svg>
</template>
