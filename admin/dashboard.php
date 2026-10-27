<?php
require_once "../includes/auth.php";
require_admin();

require_once "../config/db.php";
require_once "layout.php";

/* =========================
   Summary
========================= */
$total = $pdo->query("
  SELECT COUNT(*) 
  FROM repair_requests 
  WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
  AND YEAR(created_at) = YEAR(CURRENT_DATE())
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

$parcelsToday = $pdo->query("
  SELECT COALESCE(SUM(quantity),0)
  FROM parcels
  WHERE DATE(received_at) = CURRENT_DATE()
")->fetchColumn();

/* =========================
   Latest repairs (ค้นหาข้อมูล)
========================= */
$search = $_GET['search'] ?? '';
$params = [];

$repair_query = "
  SELECT rr.*, r.room_no, c.category_name
  FROM repair_requests rr
  JOIN rooms r ON rr.room_id = r.room_id
  JOIN repair_categories c ON rr.category_id = c.category_id
";

// ถ้ามีการพิมพ์ค้นหา
if (!empty($search)) {
    $repair_query .= " WHERE r.room_no LIKE ? OR rr.description LIKE ? OR c.category_name LIKE ? OR rr.request_id LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$repair_query .= " ORDER BY rr.request_id DESC";

// ถ้าไม่ได้ค้นหา ให้แสดงแค่ 5 รายการล่าสุด
if (empty($search)) {
    $repair_query .= " LIMIT 5";
}

$stmt = $pdo->prepare($repair_query);
$stmt->execute($params);
$repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   Monthly chart
========================= */
$monthlyStmt = $pdo->query("
  SELECT 
    MONTH(created_at) AS month_num,
    COUNT(*) AS total
  FROM repair_requests
  WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
  GROUP BY MONTH(created_at)
");
$monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$months = [
  1 => "ม.ค.",
  2 => "ก.พ.",
  3 => "มี.ค.",
  4 => "เม.ย.",
  5 => "พ.ค.",
  6 => "มิ.ย.",
];

$maxMonthly = max(array_merge([1], array_values($monthlyData)));

/* =========================
   Category chart
========================= */
$categoryStmt = $pdo->query("
  SELECT 
    c.category_name,
    COUNT(*) AS total
  FROM repair_requests rr
  JOIN repair_categories c ON rr.category_id = c.category_id
  GROUP BY c.category_id, c.category_name
");
$categoryData = $categoryStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$totalCategory = array_sum($categoryData);
if ($totalCategory == 0) {
  $totalCategory = 1;
}

$electricPercent = round((($categoryData["ไฟฟ้า"] ?? $categoryData["ระบบไฟฟ้า"] ?? 0) / $totalCategory) * 100);
$waterPercent = round((($categoryData["ประปา"] ?? $categoryData["ระบบประปา"] ?? 0) / $totalCategory) * 100);
$airPercent = round((($categoryData["แอร์"] ?? $categoryData["เครื่องปรับอากาศ"] ?? 0) / $totalCategory) * 100);
$otherPercent = max(0, 100 - $electricPercent - $waterPercent - $airPercent);

admin_header("dashboard");
?>

<div class="max-w-[1200px] mx-auto space-y-4">

  <div>
    <h1 class="text-[22px] font-bold text-slate-800">
      Dashboard ภาพรวมระบบ
    </h1>
    <p class="text-sm text-slate-500 mt-0.5">
      สรุปข้อมูลคำขอซ่อมและพัสดุภายในหอพัก
    </p>
  </div>

  <!-- Summary cards -->
  <div class="grid grid-cols-4 gap-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4 hover:shadow-md transition">
      <p class="text-sm text-slate-600 font-medium">คำขอซ่อมทั้งหมดเดือนนี้</p>
      <p class="mt-1 flex items-baseline gap-2">
        <span class="text-blue-600 text-3xl font-bold"><?= $total ?></span>
        <span class="text-sm text-slate-500">รายการ</span>
      </p>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4 hover:shadow-md transition">
      <p class="text-sm text-slate-600 font-medium">รออนุมัติ</p>
      <p class="mt-1 flex items-baseline gap-2">
        <span class="text-orange-500 text-3xl font-bold"><?= $pending ?></span>
        <span class="text-sm text-slate-500">รายการ</span>
      </p>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4 hover:shadow-md transition">
      <p class="text-sm text-slate-600 font-medium">เสร็จสิ้นแล้ว</p>
      <p class="mt-1 flex items-baseline gap-2">
        <span class="text-green-500 text-3xl font-bold"><?= $done ?></span>
        <span class="text-sm text-slate-500">รายการ</span>
      </p>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4 hover:shadow-md transition">
      <p class="text-sm text-slate-600 font-medium">พัสดุเข้ารับวันนี้</p>
      <p class="mt-1 flex items-baseline gap-2">
        <span class="text-red-500 text-3xl font-bold"><?= $parcelsToday ?></span>
        <span class="text-sm text-slate-500">ชิ้น</span>
      </p>
    </div>
  </div>

  <!-- Charts -->
  <div class="grid grid-cols-2 gap-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
      <h2 class="text-base font-semibold text-slate-800 mb-3">
        คำขอซ่อมรายเดือน
      </h2>

      <div class="flex items-end gap-5 h-[140px] px-2 border-b border-slate-300">
        <?php foreach ($months as $monthNum => $monthName): ?>
          <?php
            $count = $monthlyData[$monthNum] ?? 0;
            $height = $count > 0 ? max(20, ($count / $maxMonthly) * 80) : 12;
          ?>

          <div class="text-center flex-1">
            <div class="flex justify-center items-end h-[85px]">
              <div
                class="w-[34px] rounded-t-md bg-blue-500"
                style="height: <?= $height ?>px;"
              ></div>
            </div>
            <p class="text-xs font-semibold text-slate-700 mt-1"><?= $count ?></p>
            <p class="text-xs text-slate-500 pb-1"><?= $monthName ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-4">
      <h2 class="text-base font-semibold text-slate-800 mb-3">
        สถิติตามประเภท
      </h2>

      <div class="flex items-center justify-center gap-10 h-[140px]">
        <div
          class="w-[115px] h-[115px] rounded-full flex items-center justify-center shadow-inner"
          style="
            background: conic-gradient(
              #3b82f6 0% <?= $electricPercent ?>%,
              #60a5fa <?= $electricPercent ?>% <?= $electricPercent + $waterPercent ?>%,
              #a78bfa <?= $electricPercent + $waterPercent ?>% <?= $electricPercent + $waterPercent + $airPercent ?>%,
              #fbbf24 <?= $electricPercent + $waterPercent + $airPercent ?>% 100%
            );
          "
        >
          <div class="w-[56px] h-[56px] bg-white rounded-full"></div>
        </div>

        <div class="space-y-1.5 text-sm text-slate-700">
          <p><span class="inline-block w-3 h-3 bg-blue-500 rounded-full mr-2"></span>ไฟฟ้า <?= $electricPercent ?>%</p>
          <p><span class="inline-block w-3 h-3 bg-blue-400 rounded-full mr-2"></span>ประปา <?= $waterPercent ?>%</p>
          <p><span class="inline-block w-3 h-3 bg-purple-400 rounded-full mr-2"></span>แอร์ <?= $airPercent ?>%</p>
          <p><span class="inline-block w-3 h-3 bg-yellow-400 rounded-full mr-2"></span>อื่น ๆ <?= $otherPercent ?>%</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Latest repairs -->
  <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-200 flex justify-between items-center bg-slate-50/50">
      <h2 class="text-base font-semibold text-slate-800">
        <?= !empty($search) ? "ผลการค้นหารายการแจ้งซ่อม" : "รายการแจ้งซ่อมล่าสุด" ?>
      </h2>

      <div class="flex items-center gap-3">
        <form method="GET" class="flex gap-2">
          <input 
            type="text" 
            name="search" 
            value="<?= htmlspecialchars($search) ?>" 
            placeholder="ค้นหารหัส, ห้อง, รายละเอียด..." 
            class="w-[260px] h-[36px] rounded-lg border border-slate-300 px-3 focus:ring-2 focus:ring-blue-300 focus:outline-none text-sm"
          >
          <button type="submit" class="h-[36px] bg-slate-800 text-white px-4 rounded-lg text-sm font-semibold hover:bg-slate-900 transition shadow-sm">
            ค้นหา
          </button>
          
          <?php if (!empty($search)): ?>
            <a href="dashboard.php" class="h-[36px] bg-white text-slate-600 px-4 rounded-lg text-sm font-semibold border border-slate-200 hover:bg-slate-50 transition shadow-sm flex items-center">
              ล้างค่า
            </a>
          <?php endif; ?>
        </form>

        <div class="w-px h-6 bg-slate-300"></div>
        <a href="repairs.php" class="btn-ui h-[36px] inline-flex items-center bg-blue-500 text-white rounded-lg px-4 text-sm font-semibold hover:bg-blue-600 shadow-sm">
          ดูทั้งหมด
        </a>
      </div>
    </div>

    <table class="dash-table w-full text-center">
      <thead class="bg-blue-50/50">
        <tr class="border-b border-slate-200">
          <th>รหัส</th>
          <th>ห้อง</th>
          <th>หมวดหมู่</th>
          <th>รายละเอียด</th>
          <th>วันที่แจ้ง</th>
          <th>สถานะ</th>
        </tr>
      </thead>

      <tbody>
        <?php if (count($repairs) > 0): ?>
          <?php foreach ($repairs as $r): ?>
            <?php
              $statusClass = "bg-orange-100 text-orange-700 border-orange-200";
              if ($r["status"] === "กำลังดำเนินการ") {
                $statusClass = "bg-blue-100 text-blue-700 border-blue-200";
              }
              if ($r["status"] === "เสร็จสิ้น") {
                $statusClass = "bg-green-100 text-green-700 border-green-200";
              }
            ?>

            <tr class="border-b border-slate-100 hover:bg-slate-50">
              <td class="font-semibold text-slate-700">
                #R-<?= str_pad($r["request_id"], 4, "0", STR_PAD_LEFT) ?>
              </td>
              <td class="font-bold text-slate-800"><?= htmlspecialchars($r["room_no"]) ?></td>
              <td><?= htmlspecialchars($r["category_name"]) ?></td>
              <td class="max-w-[220px] truncate text-slate-600" title="<?= htmlspecialchars($r["description"]) ?>">
                <?= htmlspecialchars($r["description"]) ?>
              </td>
              <td class="text-slate-600"><?= date("d M y", strtotime($r["created_at"])) ?></td>
              <td>
                <span class="<?= $statusClass ?> border px-3 py-1 rounded-full text-xs font-medium">
                  <?= htmlspecialchars($r["status"]) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" class="p-8 text-slate-500">
              <?php if (!empty($search)): ?>
                ไม่พบรายการแจ้งซ่อมที่ตรงกับ "<b><?= htmlspecialchars($search) ?></b>"
              <?php else: ?>
                ยังไม่มีรายการแจ้งซ่อม
              <?php endif; ?>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
  .dash-table th { padding: 10px 8px; font-size: 14px; }
  .dash-table td { padding: 9px 8px; font-size: 14px; }
</style>

<?php admin_footer(); ?>