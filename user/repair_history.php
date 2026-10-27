<?php
require_once "../includes/auth.php";
require_user();

require_once "../config/db.php";
require_once "layout.php";

$user_id = $_SESSION["user_id"];
$room_id = $_SESSION["room_id"];

/* =========================
   Process Actions (รับทราบการนัดหมาย)
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "acknowledge") {
    $request_id = $_POST["request_id"];
    
    // อัปเดตเวลาที่กดยืนยันรับทราบ
    $stmt = $pdo->prepare("UPDATE repair_requests SET acknowledged_at = NOW() WHERE request_id = ? AND user_id = ?");
    $stmt->execute([$request_id, $user_id]);
    
    header("Location: repair_history.php");
    exit;
}

/* =========================
   USER INFO
========================= */
$stmt = $pdo->prepare("
  SELECT u.*, r.room_no, r.floor
  FROM users u
  LEFT JOIN rooms r ON u.room_id = r.room_id
  WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$_SESSION["room_no"] = $user["room_no"] ?? "101";

/* =========================
   SEARCH & FILTER
========================= */
$status = $_GET["status"] ?? "";
$search = trim($_GET["search"] ?? "");

$sql = "
  SELECT rr.*, c.category_name
  FROM repair_requests rr
  JOIN repair_categories c ON rr.category_id = c.category_id
  WHERE rr.user_id = ?
";

$params = [$user_id];

if ($status !== "") {
  $sql .= " AND rr.status = ?";
  $params[] = $status;
}

if ($search !== "") {
  $sql .= " AND (rr.description LIKE ? OR c.category_name LIKE ? OR rr.request_id LIKE ?)";
  $params[] = "%$search%";
  $params[] = "%$search%";
  $params[] = "%$search%";
}

$sql .= " ORDER BY rr.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$repairs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* สถิติสำหรับกราฟรายเดือน และ กราฟหมวดหมู่ */
$monthlyStmt = $pdo->prepare("SELECT MONTH(created_at) AS month_num, COUNT(*) AS total FROM repair_requests WHERE user_id = ? GROUP BY MONTH(created_at)");
$monthlyStmt->execute([$user_id]);
$monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$months = [1 => "ม.ค.", 2 => "ก.พ.", 3 => "มี.ค.", 4 => "เม.ย.", 5 => "พ.ค.", 6 => "มิ.ย."];
$maxMonthly = max(array_merge([1], array_values($monthlyData)));

$categoryStmt = $pdo->prepare("SELECT c.category_name, COUNT(*) AS total FROM repair_requests rr JOIN repair_categories c ON rr.category_id = c.category_id WHERE rr.user_id = ? GROUP BY c.category_id, c.category_name");
$categoryStmt->execute([$user_id]);
$categoryData = $categoryStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalCategory = array_sum($categoryData);
if ($totalCategory == 0) $totalCategory = 1;
$electricPercent = round((($categoryData["ไฟฟ้า"] ?? $categoryData["ระบบไฟฟ้า"] ?? 0) / $totalCategory) * 100);
$waterPercent = round((($categoryData["ประปา"] ?? $categoryData["ระบบประปา"] ?? 0) / $totalCategory) * 100);
$airPercent = round((($categoryData["แอร์"] ?? $categoryData["เครื่องปรับอากาศ"] ?? 0) / $totalCategory) * 100);
$otherPercent = max(0, 100 - $electricPercent - $waterPercent - $airPercent);

$search_param = $search !== "" ? "&search=" . urlencode($search) : "";

user_header("history");
?>

<div class="max-w-[1200px] mx-auto space-y-6">

  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-[28px] font-bold text-slate-800">ประวัติการแจ้งซ่อม</h1>
      <p class="text-slate-500 mt-1">
        ห้อง <?= htmlspecialchars($user["room_no"] ?? "101") ?>
        (ชั้น <?= htmlspecialchars($user["floor"] ?? "-") ?>)
      </p>
    </div>
  </div>

  <div class="flex justify-between items-center">
    <div class="flex gap-3">
      <a href="repair_history.php<?= $search !== "" ? "?search=".urlencode($search) : "" ?>" class="btn-ui <?= $status === '' ? 'bg-slate-800 text-white shadow-sm' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50' ?> border rounded-xl px-5 py-2 text-sm font-semibold transition">ทั้งหมด</a>
      <a href="?status=รออนุมัติ<?= $search_param ?>" class="btn-ui <?= $status === 'รออนุมัติ' ? 'bg-orange-500 text-white shadow-sm border-orange-500' : 'bg-orange-50 text-orange-700 border-orange-200 hover:bg-orange-100' ?> border rounded-xl px-5 py-2 text-sm font-semibold transition">รออนุมัติ</a>
      <a href="?status=กำลังดำเนินการ<?= $search_param ?>" class="btn-ui <?= $status === 'กำลังดำเนินการ' ? 'bg-blue-500 text-white shadow-sm border-blue-500' : 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100' ?> border rounded-xl px-5 py-2 text-sm font-semibold transition">กำลังดำเนินการ</a>
      <a href="?status=เสร็จสิ้น<?= $search_param ?>" class="btn-ui <?= $status === 'เสร็จสิ้น' ? 'bg-green-500 text-white shadow-sm border-green-500' : 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100' ?> border rounded-xl px-5 py-2 text-sm font-semibold transition">เสร็จสิ้น</a>
    </div>

    <form method="GET" class="flex gap-2">
      <?php if ($status !== ""): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>"><?php endif; ?>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหารหัส, หมวดหมู่, รายละเอียด..." class="w-[260px] h-[42px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300 focus:outline-none text-sm">
      <button type="submit" class="bg-slate-800 text-white px-5 h-[42px] rounded-xl text-sm font-semibold hover:bg-slate-900 transition shadow-sm">ค้นหา</button>
      <?php if ($search !== ""): ?><a href="repair_history.php<?= $status !== "" ? "?status=".urlencode($status) : "" ?>" class="bg-white text-slate-600 px-4 h-[42px] rounded-xl text-sm font-semibold border border-slate-200 hover:bg-slate-50 transition shadow-sm flex items-center justify-center">ล้างค่า</a><?php endif; ?>
    </form>
  </div>

  <?php if ($search === ""): ?>
  <div class="grid grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold text-slate-800">สถิติรายเดือน</h2>
      </div>
      <div class="flex items-end gap-7 h-[180px] px-4 border-b border-slate-300">
        <?php foreach ($months as $monthNum => $monthName): ?>
          <?php $count = $monthlyData[$monthNum] ?? 0; $height = $count > 0 ? max(28, ($count / $maxMonthly) * 135) : 18; ?>
          <div class="text-center flex-1">
            <div class="flex justify-center items-end h-[140px]"><div class="w-[42px] rounded-t-lg bg-blue-500" style="height: <?= $height ?>px;"></div></div>
            <p class="text-sm font-semibold text-slate-700 mt-2"><?= $count ?></p>
            <p class="text-sm text-slate-500"><?= $monthName ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-semibold text-slate-800">สถิติตามประเภท</h2>
      </div>
      <div class="flex items-center justify-center gap-12 h-[185px]">
        <div class="w-[145px] h-[145px] rounded-full flex items-center justify-center shadow-inner" style="background: conic-gradient(#3b82f6 0% <?= $electricPercent ?>%, #60a5fa <?= $electricPercent ?>% <?= $electricPercent + $waterPercent ?>%, #a78bfa <?= $electricPercent + $waterPercent ?>% <?= $electricPercent + $waterPercent + $airPercent ?>%, #fbbf24 <?= $electricPercent + $waterPercent + $airPercent ?>% 100%);">
          <div class="w-[70px] h-[70px] bg-white rounded-full"></div>
        </div>
        <div class="space-y-3 text-slate-700">
          <p><span class="inline-block w-3 h-3 bg-blue-500 rounded-full mr-2"></span>ไฟฟ้า <?= $electricPercent ?>%</p>
          <p><span class="inline-block w-3 h-3 bg-blue-400 rounded-full mr-2"></span>ประปา <?= $waterPercent ?>%</p>
          <p><span class="inline-block w-3 h-3 bg-purple-400 rounded-full mr-2"></span>แอร์ <?= $airPercent ?>%</p>
          <p><span class="inline-block w-3 h-3 bg-yellow-400 rounded-full mr-2"></span>อื่น ๆ <?= $otherPercent ?>%</p>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center">
      <div>
        <h2 class="text-xl font-semibold text-slate-800"><?= !empty($search) || !empty($status) ? "ผลการค้นหา" : "รายการแจ้งซ่อมทั้งหมด" ?></h2>
        <p class="text-slate-500 text-sm mt-1">ประวัติการแจ้งซ่อมของห้องพักนี้</p>
      </div>
      <span class="bg-blue-100 text-blue-700 rounded-full px-4 py-1.5 text-sm font-semibold">พบ <?= count($repairs) ?> รายการ</span>
    </div>

    <table class="w-full text-center">
      <thead class="bg-slate-50">
        <tr class="border-b border-slate-200">
          <th class="p-4">รหัส</th>
          <th>หมวดหมู่</th>
          <th>รายละเอียดปัญหา</th>
          <th>วันที่แจ้ง</th>
          <th>สถานะ</th>
          <th>จัดการ</th>
        </tr>
      </thead>

      <tbody>
        <?php if (count($repairs) > 0): ?>
          <?php foreach ($repairs as $r): ?>
            <?php
              $statusClass = "bg-orange-100 text-orange-700 border-orange-200";
              if ($r["status"] === "กำลังดำเนินการ") $statusClass = "bg-blue-100 text-blue-700 border-blue-200";
              if ($r["status"] === "เสร็จสิ้น") $statusClass = "bg-green-100 text-green-700 border-green-200";

              $requestCode = "#R-" . str_pad($r["request_id"], 4, "0", STR_PAD_LEFT);
              $createdDate = date("d M Y", strtotime($r["created_at"]));
              $appointmentDate = !empty($r["appointment_date"]) ? date("d M Y", strtotime($r["appointment_date"])) : "-";
              $confirmedDateText = !empty($r["confirmed_date"]) ? date("d M Y", strtotime($r["confirmed_date"])) : "";
              
              // เช็คว่าต้องการการตอบรับจากผู้ใช้หรือไม่
              $needsAck = !empty($r["confirmed_date"]) && empty($r["acknowledged_at"]) && $r["status"] !== "เสร็จสิ้น";
            ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50 transition relative">
              <td class="p-4 font-semibold text-slate-700">
                <?= $requestCode ?>
                <?php if($needsAck): ?>
                  <span class="absolute top-4 left-4 flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500 border-2 border-white"></span>
                  </span>
                <?php endif; ?>
              </td>
              <td class="text-slate-700 font-medium"><?= htmlspecialchars($r["category_name"]) ?></td>
              <td class="max-w-[200px] truncate text-slate-500" title="<?= htmlspecialchars($r["description"]) ?>">
                <?= htmlspecialchars($r["description"]) ?>
              </td>
              <td class="text-slate-600"><?= $createdDate ?></td>
              <td>
                <span class="<?= $statusClass ?> border px-4 py-1.5 rounded-full text-sm font-semibold inline-block">
                  <?= htmlspecialchars($r["status"]) ?>
                </span>
              </td>
              <td>
                <button type="button" onclick="openRepairDetail('detail-<?= $r["request_id"] ?>')" class="btn-ui <?= $needsAck ? 'bg-red-50 text-red-600 border-red-200 hover:bg-red-100' : 'bg-blue-50 text-blue-600 border-blue-200 hover:bg-blue-100' ?> border rounded-xl px-4 py-2 text-sm font-semibold transition">
                  <?= $needsAck ? 'รอรับทราบ' : 'ดูรายละเอียด' ?>
                </button>
              </td>
            </tr>

            <div id="detail-<?= $r["request_id"] ?>" class="hidden fixed inset-0 bg-black/40 z-50 items-center justify-center p-4 sm:p-6 backdrop-blur-sm">
              <div class="bg-white w-full max-w-[680px] rounded-2xl shadow-xl border border-slate-200 flex flex-col max-h-[90vh] overflow-hidden">
                
                <div class="bg-slate-800 text-white px-6 py-4 flex justify-between items-center shrink-0">
                  <h3 class="text-xl font-bold">รายละเอียดคำขอซ่อม <?= $requestCode ?></h3>
                  <button type="button" onclick="closeRepairDetail('detail-<?= $r["request_id"] ?>')" class="bg-white/10 hover:bg-white/20 text-white rounded-full w-9 h-9 flex items-center justify-center transition">✕</button>
                </div>

                <div class="p-6 space-y-5 text-left overflow-y-auto">
                  
                  <div class="grid grid-cols-2 gap-4">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                      <p class="text-sm text-slate-500">วันที่แจ้ง</p>
                      <p class="font-semibold text-slate-800"><?= $createdDate ?></p>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                      <p class="text-sm text-slate-500">หมวดหมู่</p>
                      <p class="font-semibold text-slate-800"><?= htmlspecialchars($r["category_name"]) ?></p>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 col-span-2">
                      <p class="text-sm text-blue-600 font-medium">วันที่คุณสะดวกให้เข้าซ่อมตอนแรก</p>
                      <p class="font-bold text-blue-800"><?= $appointmentDate ?></p>
                    </div>
                  </div>

                  <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
                    <p class="text-sm text-slate-500 mb-2">รายละเอียดปัญหา</p>
                    <p class="leading-relaxed text-slate-700"><?= nl2br(htmlspecialchars($r["description"])) ?></p>
                  </div>

                  <?php if (!empty($r["confirmed_date"]) || !empty($r["admin_remark"])): ?>
                    <div class="border <?= $needsAck ? 'border-red-300 bg-red-50/50' : 'border-orange-200 bg-orange-50' ?> rounded-xl p-5 mt-4">
                      <h4 class="font-bold <?= $needsAck ? 'text-red-700' : 'text-orange-800' ?> mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        การตอบกลับจากนิติบุคคล
                      </h4>
                      
                      <div class="space-y-4">
                        <?php if (!empty($r["confirmed_date"])): ?>
                          <div class="bg-white rounded-lg p-3 border <?= $needsAck ? 'border-red-200' : 'border-orange-100' ?>">
                            <p class="text-xs text-slate-500 font-medium">วันที่ช่างนัดหมายเข้าซ่อมจริง</p>
                            <p class="text-slate-800 font-bold text-lg mt-0.5"><?= $confirmedDateText ?></p>
                          </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($r["admin_remark"])): ?>
                          <div>
                            <p class="text-xs text-orange-600/80 font-bold mb-1">หมายเหตุเพิ่มเติม</p>
                            <p class="text-slate-700 text-sm leading-relaxed bg-white p-3 rounded-lg border border-orange-100"><?= nl2br(htmlspecialchars($r["admin_remark"])) ?></p>
                          </div>
                        <?php endif; ?>

                        <?php if ($needsAck): ?>
                            <div class="pt-2 border-t border-red-200">
                                <p class="text-sm text-red-600 mb-3 text-center font-medium">โปรดกดยืนยันว่าคุณรับทราบการนัดหมายนี้แล้ว</p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="acknowledge">
                                    <input type="hidden" name="request_id" value="<?= $r["request_id"] ?>">
                                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl shadow-md transition flex justify-center items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        รับทราบการนัดหมาย
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <?php if (!empty($r["acknowledged_at"])): ?>
                                <div class="text-sm text-green-700 font-semibold flex items-center gap-1.5 pt-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    คุณรับทราบการนัดหมายแล้ว (<?= date("d/m/y H:i", strtotime($r["acknowledged_at"])) ?>)
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                      </div>
                    </div>
                  <?php endif; ?>

                  <?php if (!$needsAck): ?>
                    <div class="flex justify-end pt-4 border-t border-slate-100">
                      <button type="button" onclick="closeRepairDetail('detail-<?= $r["request_id"] ?>')" class="btn-ui bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200 rounded-xl px-6 py-2.5 font-semibold transition">ปิดหน้าต่าง</button>
                    </div>
                  <?php endif; ?>

                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" class="p-12 text-slate-500 text-lg">ยังไม่มีประวัติการแจ้งซ่อม</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function openRepairDetail(id) {
  const modal = document.getElementById(id);
  modal.classList.remove("hidden");
  modal.classList.add("flex");
}
function closeRepairDetail(id) {
  const modal = document.getElementById(id);
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}
</script>

<?php user_footer(); ?>