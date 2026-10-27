<?php
require_once "../includes/auth.php";
require_admin();

require_once "../config/db.php";
require_once "layout.php";

/* =========================
   Summary (ภาพรวมทั้งหมด)
========================= */
$total = $pdo->query("
  SELECT COUNT(*) 
  FROM repair_requests
")->fetchColumn();

$pending = $pdo->query("
  SELECT COUNT(*) 
  FROM repair_requests
  WHERE status = 'รออนุมัติ'
")->fetchColumn();

$done = $pdo->query("
  SELECT COUNT(*) 
  FROM repair_requests
  WHERE status = 'เสร็จสิ้น'
")->fetchColumn();

$parcels = $pdo->query("
  SELECT COALESCE(SUM(quantity),0)
  FROM parcels
")->fetchColumn();

/* =========================
   Report Data (สรุปรายห้อง + ระบบค้นหา)
========================= */
$search = trim($_GET["search"] ?? "");

$sql = "
  SELECT 
    r.room_no,
    u.full_name,

    COUNT(DISTINCT rr.request_id) AS total_repairs,

    SUM(
      CASE 
        WHEN rr.status = 'เสร็จสิ้น'
        THEN 1
        ELSE 0
      END
    ) AS done_repairs,

    SUM(
      CASE 
        WHEN rr.status != 'เสร็จสิ้น'
        THEN 1
        ELSE 0
      END
    ) AS pending_repairs,

    COALESCE(SUM(p.quantity),0) AS parcel_count

  FROM rooms r

  LEFT JOIN users u
    ON u.room_id = r.room_id
    AND u.role = 'user'

  LEFT JOIN repair_requests rr
    ON rr.room_id = r.room_id

  LEFT JOIN parcels p
    ON p.room_id = r.room_id
";

$params = [];

// เงื่อนไขการค้นหา
if ($search !== "") {
    $sql .= " WHERE r.room_no LIKE ? OR u.full_name LIKE ?";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " GROUP BY r.room_id ORDER BY r.room_no ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

admin_header("reports");
?>

<!-- เพิ่ม CSS เฉพาะสำหรับการพิมพ์ Export Report ให้ไม่เพี้ยน -->
<style>
@media print {
    /* ซ่อนเมนู, หัวเว็บ, ปุ่ม และฟอร์มค้นหาที่ไม่ต้องการให้ติดไปตอนปริ้นท์ */
    aside, 
    nav, 
    header, 
    .btn-export, 
    form, 
    button, 
    a.bg-white,
    .max-w-\[1200px\] > div:first-child {
        display: none !important;
    }

    /* ขยายความกว้างเนื้อหาให้เต็มหน้ากระดาษ */
    body, 
    .max-w-\[1200px\] {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* ปรับแต่งตารางให้พอดีหน้ากระดาษ A4 */
    table {
        width: 100% !important;
        border-collapse: collapse !important;
    }

    th, td {
        padding: 10px !important;
        font-size: 13px !important;
        border-bottom: 1px solid #cbd5e1 !important;
    }

    /* บังคับให้สีพื้นหลังและแบดจ์แสดงผลตามหน้าจอ */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>

<div class="max-w-[1200px] mx-auto space-y-6">

  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-[28px] font-bold text-slate-800">
        รายงานสถิติ
      </h1>
      <p class="text-slate-500 mt-1">
        สรุปข้อมูลคำขอซ่อมและพัสดุภายในหอพัก
      </p>
    </div>

    <button
      onclick="window.print()"
      class="btn-export bg-blue-500 hover:bg-blue-600 text-white rounded-xl px-5 py-3 font-semibold shadow-sm flex items-center gap-2 cursor-pointer transition"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
      </svg>
      Export Report
    </button>
  </div>

  <div class="grid grid-cols-4 gap-6">

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition">
      <p class="text-slate-600 font-medium leading-6">คำขอซ่อมทั้งหมด</p>
      <p class="text-blue-600 text-4xl font-bold mt-3"><?= $total ?></p>
      <p class="text-sm text-slate-500 mt-2">รายการ</p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition">
      <p class="text-slate-600 font-medium">รออนุมัติ</p>
      <p class="text-orange-500 text-4xl font-bold mt-3"><?= $pending ?></p>
      <p class="text-sm text-slate-500 mt-2">รายการ</p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition">
      <p class="text-slate-600 font-medium">เสร็จสิ้นแล้ว</p>
      <p class="text-green-500 text-4xl font-bold mt-3"><?= $done ?></p>
      <p class="text-sm text-slate-500 mt-2">รายการ</p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition">
      <p class="text-slate-600 font-medium">พัสดุทั้งหมด</p>
      <p class="text-red-500 text-4xl font-bold mt-3"><?= $parcels ?></p>
      <p class="text-sm text-slate-500 mt-2">ชิ้น</p>
    </div>

  </div>

  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">

    <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50/50">

      <div>
        <h2 class="text-xl font-semibold text-slate-800">
          <?= !empty($search) ? "ผลการค้นหาสรุปรายห้อง" : "สรุปรายห้อง" ?>
        </h2>
        <p class="text-slate-500 text-sm mt-1">
          ข้อมูลคำขอซ่อมและพัสดุแยกตามห้องพัก
        </p>
      </div>

      <div class="flex items-center gap-4">
        <form method="GET" class="flex gap-2">
          <input 
            type="text" 
            name="search" 
            value="<?= htmlspecialchars($search) ?>" 
            placeholder="ค้นหาห้อง, ชื่อผู้พัก..." 
            class="w-[260px] h-[42px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300 focus:outline-none text-sm"
          >
          <button type="submit" class="bg-slate-800 text-white px-5 py-2 rounded-xl text-sm font-semibold hover:bg-slate-900 transition shadow-sm">
            ค้นหา
          </button>
          
          <?php if (!empty($search)): ?>
            <a href="reports.php" class="bg-white text-slate-600 px-4 py-2 rounded-xl text-sm font-semibold border border-slate-200 hover:bg-slate-50 transition shadow-sm flex items-center">
              ล้างค่า
            </a>
          <?php endif; ?>
        </form>

        <div class="w-px h-8 bg-slate-300"></div> <span class="bg-blue-100 text-blue-700 rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap">
          <?= count($reports) ?> ห้อง
        </span>
      </div>

    </div>

    <table class="w-full text-center">
      <thead class="bg-blue-50/50">
        <tr class="border-b border-slate-200">
          <th class="p-4">ห้อง</th>
          <th>ผู้พัก</th>
          <th>แจ้งซ่อมทั้งหมด</th>
          <th>เสร็จสิ้น</th>
          <th>ค้างดำเนินการ</th>
          <th>พัสดุทั้งหมด</th>
        </tr>
      </thead>

      <tbody>
        <?php if (count($reports) > 0): ?>
          <?php foreach ($reports as $r): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">

              <td class="p-4 font-bold text-slate-800">
                <?= htmlspecialchars($r["room_no"]) ?>
              </td>

              <td class="text-slate-700 font-medium">
                <?= htmlspecialchars($r["full_name"] ?? "-") ?>
              </td>

              <td>
                <span class="bg-blue-100 text-blue-700 rounded-full px-4 py-1.5 font-semibold text-sm">
                  <?= $r["total_repairs"] ?: 0 ?>
                </span>
              </td>

              <td>
                <span class="bg-green-100 text-green-700 rounded-full px-4 py-1.5 font-semibold text-sm">
                  <?= $r["done_repairs"] ?: 0 ?>
                </span>
              </td>

              <td>
                <span class="bg-orange-100 text-orange-700 rounded-full px-4 py-1.5 font-semibold text-sm">
                  <?= $r["pending_repairs"] ?: 0 ?>
                </span>
              </td>

              <td>
                <span class="bg-red-100 text-red-700 rounded-full px-4 py-1.5 font-semibold text-sm">
                  <?= $r["parcel_count"] ?: 0 ?>
                </span>
              </td>

            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" class="p-12 text-slate-500 text-lg">
              <?php if (!empty($search)): ?>
                ไม่พบข้อมูลห้องหรือผู้พักที่ตรงกับ "<b><?= htmlspecialchars($search) ?></b>"
              <?php else: ?>
                ยังไม่มีข้อมูลรายงาน
              <?php endif; ?>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

  </div>

</div>

<?php admin_footer(); ?>