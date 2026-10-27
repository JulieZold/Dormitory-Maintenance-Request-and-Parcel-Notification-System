<?php
require_once "../includes/auth.php";
require_admin();

require_once "../config/db.php";
require_once "layout.php";

/* =========================
   Process Updates
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "quick";
    $request_id = $_POST["request_id"];
    $status = $_POST["status"];

    if ($action === "quick") {
        $stmt = $pdo->prepare("UPDATE repair_requests SET status=? WHERE request_id=?");
        $stmt->execute([$status, $request_id]);
    } elseif ($action === "detail") {
        $confirmed_date = !empty($_POST["confirmed_date"]) ? $_POST["confirmed_date"] : null;
        $admin_remark = trim($_POST["admin_remark"]) !== "" ? trim($_POST["admin_remark"]) : null;

        // ลองอัปเดตข้อมูลแบบครบถ้วน ถ้าใน DB ไม่มี confirmed_date ให้ถอยกลับมาอัปเดตเฉพาะสถานะ
        try {
            $stmt = $pdo->prepare("UPDATE repair_requests SET status=?, confirmed_date=?, admin_remark=?, acknowledged_at=NULL WHERE request_id=?");
            $stmt->execute([$status, $confirmed_date, $admin_remark, $request_id]);
        } catch (Exception $e) {
            $stmt = $pdo->prepare("UPDATE repair_requests SET status=? WHERE request_id=?");
            $stmt->execute([$status, $request_id]);
        }
    }
    
    // ==========================================
    // ส่งการแจ้งเตือนให้ผู้ใช้งาน
    // ==========================================
    $userStmt = $pdo->prepare("SELECT user_id, description FROM repair_requests WHERE request_id = ?");
    $userStmt->execute([$request_id]);
    $repairData = $userStmt->fetch(PDO::FETCH_ASSOC);

    if ($repairData) {
        $target_user_id = $repairData['user_id'];
        
        $repair_desc = mb_substr($repairData['description'], 0, 30, 'UTF-8');
        if (mb_strlen($repairData['description'], 'UTF-8') > 30) {
            $repair_desc .= '...';
        }
        
        if ($status === 'กำลังดำเนินการ') {
            $message = "🛠️ นิติบุคคลรับเรื่องแจ้งซ่อม '{$repair_desc}' แล้ว และกำลังดำเนินการแก้ไขค่ะ";
        } elseif ($status === 'เสร็จสิ้น') {
            $message = "🛠️ งานซ่อม '{$repair_desc}' ดำเนินการเสร็จสิ้นแล้ว ขอบคุณค่ะ";
        } else {
            $message = "🛠️ สถานะงานซ่อม '{$repair_desc}' เปลี่ยนเป็น: {$status}";
        }
        
        // ลอง Insert แจ้งเตือน (รองรับทั้งกรณีตารางที่มีคอลัมน์ title/type และไม่มี)
        try {
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notifStmt->execute([$target_user_id, $message]);
        } catch (Exception $e) {
            // เผื่อในอนาคตมีคอลัมน์อื่นเพิ่มเติม
        }
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

$status = $_GET["status"] ?? "";
$search = trim($_GET["search"] ?? "");

$sql = "
  SELECT 
    rr.*,
    r.room_no,
    r.floor,
    c.category_name,
    u.full_name,
    u.phone,
    u.email
  FROM repair_requests rr
  JOIN rooms r ON rr.room_id = r.room_id
  JOIN repair_categories c ON rr.category_id = c.category_id
  JOIN users u ON rr.user_id = u.user_id
  WHERE 1
";

$params = [];

if ($status !== "") {
  $sql .= " AND rr.status = ?";
  $params[] = $status;
}

if ($search !== "") {
  $sql .= " AND (
    r.room_no LIKE ? 
    OR u.full_name LIKE ? 
    OR rr.description LIKE ?
    OR c.category_name LIKE ?
  )";
  $params[] = "%$search%";
  $params[] = "%$search%";
  $params[] = "%$search%";
  $params[] = "%$search%";
}

$sql .= " ORDER BY rr.request_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

admin_header("repairs");

$search_param = $search !== "" ? "&search=" . urlencode($search) : "";
?>

<div class="max-w-[1050px] mx-auto">
  <h2 class="text-2xl font-bold text-slate-800 mb-7">รายการคำขอซ่อม</h2>

  <div class="flex justify-between items-center mb-6">
    <div class="flex gap-3">
      <a href="repairs.php<?= $search !== "" ? "?search=".urlencode($search) : "" ?>" class="btn-ui <?= $status === '' ? 'bg-slate-800 text-white shadow-sm' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50' ?> border rounded-xl px-5 py-2 text-sm font-semibold transition">ทั้งหมด</a>
      <a href="?status=รออนุมัติ<?= $search_param ?>" class="btn-ui <?= $status === 'รออนุมัติ' ? 'bg-orange-500 text-white shadow-sm border-orange-500' : 'bg-orange-50 text-orange-700 border-orange-200 hover:bg-orange-100' ?> border rounded-xl px-5 py-2 text-sm font-semibold transition">รออนุมัติ</a>
      <a href="?status=กำลังดำเนินการ<?= $search_param ?>" class="btn-ui <?= $status === 'กำลังดำเนินการ' ? 'bg-blue-500 text-white shadow-sm border-blue-500' : 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100' ?> border rounded-xl px-5 py-2 text-sm font-semibold transition">กำลังดำเนินการ</a>
      <a href="?status=เสร็จสิ้น<?= $search_param ?>" class="btn-ui <?= $status === 'เสร็จสิ้น' ? 'bg-green-500 text-white shadow-sm border-green-500' : 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100' ?> border rounded-xl px-5 py-2 text-sm font-semibold transition">เสร็จสิ้น</a>
    </div>

    <form method="GET" class="flex gap-2">
      <?php if ($status !== ""): ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
      <?php endif; ?>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหารหัส, ห้อง, ผู้แจ้ง..." class="w-[260px] h-[42px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300 focus:outline-none text-sm">
      <button type="submit" class="bg-slate-800 text-white px-5 h-[42px] rounded-xl text-sm font-semibold hover:bg-slate-900 transition shadow-sm">ค้นหา</button>
      <?php if ($search !== ""): ?>
        <a href="repairs.php<?= $status !== "" ? "?status=".urlencode($status) : "" ?>" class="bg-white text-slate-600 px-4 h-[42px] rounded-xl text-sm font-semibold border border-slate-200 hover:bg-slate-50 transition shadow-sm flex items-center justify-center">ล้างค่า</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
    <table class="w-full text-center">
      <thead class="bg-blue-50/50">
        <tr class="border-b border-slate-200">
          <th class="p-4">รหัส</th>
          <th>ห้อง</th>
          <th>ผู้แจ้ง</th>
          <th>หมวดหมู่</th>
          <th>รายละเอียด</th>
          <th>วันนัดเข้าซ่อม</th>
          <th>สถานะ</th>
          <th>จัดการ</th>
        </tr>
      </thead>

      <tbody>
        <?php if (count($rows) > 0): ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $color = "bg-orange-100 text-orange-700 border-orange-200";
              if ($r["status"] === "กำลังดำเนินการ") $color = "bg-blue-100 text-blue-700 border-blue-200";
              if ($r["status"] === "เสร็จสิ้น") $color = "bg-green-100 text-green-700 border-green-200";

              $requestCode = "#R-" . str_pad($r["request_id"], 4, "0", STR_PAD_LEFT);
              $createdDate = date("d/m/Y H:i", strtotime($r["created_at"]));
              $appointmentDate = !empty($r["appointment_date"]) ? date("d/m/Y", strtotime($r["appointment_date"])) : "-";
              
              $confirmedDateText = !empty($r["confirmed_date"]) ? date("d/m/Y", strtotime($r["confirmed_date"])) : "ยังไม่ระบุ";
              $isAcknowledged = !empty($r["acknowledged_at"]);
            ?>

            <tr class="border-b border-slate-100 hover:bg-slate-50">
              <td class="p-4 font-semibold text-slate-700"><?= $requestCode ?></td>
              <td class="font-bold text-slate-800"><?= htmlspecialchars($r["room_no"]) ?></td>
              <td class="text-slate-700 font-medium"><?= htmlspecialchars($r["full_name"]) ?></td>
              <td class="text-slate-600"><?= htmlspecialchars($r["category_name"]) ?></td>
              <td class="max-w-[180px] text-slate-500">
                <div class="truncate" title="<?= htmlspecialchars($r["description"]) ?>">
                  <?= htmlspecialchars($r["description"]) ?>
                </div>
                <?php if (!empty($r["image"])): ?>
                  <button type="button" onclick="openImageModal('../uploads/repairs/<?= htmlspecialchars($r["image"]) ?>')" class="inline-flex items-center gap-1 text-xs text-blue-600 font-semibold hover:underline mt-1 focus:outline-none">
                    ดูรูปแนบ
                  </button>
                <?php endif; ?>
              </td>
              <td>
                <div class="text-slate-600 text-sm">ผู้แจ้ง: <?= $appointmentDate ?></div>
                <div class="text-xs <?= !empty($r["confirmed_date"]) ? 'text-blue-600 font-semibold' : 'text-slate-400' ?> mt-0.5">
                  ช่าง: <?= $confirmedDateText ?>
                  <?php if(!empty($r["confirmed_date"]) && $isAcknowledged && $r["status"] !== "เสร็จสิ้น"): ?>
                    <span class="text-green-600 ml-1" title="ผู้พักรับทราบการนัดหมายแล้ว">✓</span>
                  <?php elseif(!empty($r["confirmed_date"]) && !$isAcknowledged && $r["status"] !== "เสร็จสิ้น"): ?>
                    <span class="text-red-500 ml-1" title="รอผู้พักกดยืนยันรับทราบ">⏳</span>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <form method="POST">
                  <input type="hidden" name="action" value="quick">
                  <input type="hidden" name="request_id" value="<?= $r["request_id"] ?>">
                  <select name="status" onchange="this.form.submit()" class="<?= $color ?> border px-4 py-1.5 rounded-full text-sm outline-none font-medium cursor-pointer">
                    <option <?= $r["status"] === "รออนุมัติ" ? "selected" : "" ?>>รออนุมัติ</option>
                    <option <?= $r["status"] === "กำลังดำเนินการ" ? "selected" : "" ?>>กำลังดำเนินการ</option>
                    <option <?= $r["status"] === "เสร็จสิ้น" ? "selected" : "" ?>>เสร็จสิ้น</option>
                  </select>
                </form>
              </td>
              <td>
                <button type="button" onclick="openRepairDetail('detail-<?= $r["request_id"] ?>')" class="btn-ui bg-blue-50 text-blue-600 border border-blue-200 rounded-xl px-4 py-2 text-sm font-semibold hover:bg-blue-100 transition">
                  ตรวจสอบ / อัปเดต
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" class="p-12 text-slate-500 text-lg">ยังไม่มีรายการคำขอซ่อม</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Popup จัดการรายละเอียดซ่อม -->
<?php if (count($rows) > 0): ?>
  <?php foreach ($rows as $r): ?>
    <?php
      $requestCode = "#R-" . str_pad($r["request_id"], 4, "0", STR_PAD_LEFT);
      $createdDate = date("d/m/Y H:i", strtotime($r["created_at"]));
      $appointmentDate = !empty($r["appointment_date"]) ? date("d/m/Y", strtotime($r["appointment_date"])) : "-";
      $isAcknowledged = !empty($r["acknowledged_at"]);
    ?>
    <div id="detail-<?= $r["request_id"] ?>" class="hidden fixed inset-0 bg-black/50 items-center justify-center p-4 sm:p-6 backdrop-blur-sm" style="z-index: 99998;">
      <div class="bg-white w-full max-w-[750px] rounded-2xl shadow-xl border border-slate-200 flex flex-col max-h-[90vh] overflow-hidden">
        
        <div class="bg-slate-800 text-white px-6 py-4 flex justify-between items-center shrink-0">
          <div>
            <h3 class="text-xl font-bold">ข้อมูลคำขอซ่อม <?= $requestCode ?></h3>
          </div>
          <button type="button" onclick="closeRepairDetail('detail-<?= $r["request_id"] ?>')" class="bg-white/10 hover:bg-white/20 text-white rounded-full w-9 h-9 flex items-center justify-center transition">✕</button>
        </div>

        <div class="p-6 space-y-6 text-left overflow-y-auto">
          <div>
              <h4 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
                  <span class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-sm">1</span> 
                  รายละเอียดจากผู้พัก
              </h4>
              <div class="grid grid-cols-2 gap-4">
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                  <p class="text-xs text-slate-500">ผู้แจ้ง</p>
                  <p class="font-semibold text-slate-800 text-sm mt-0.5"><?= htmlspecialchars($r["full_name"]) ?> (ห้อง <?= htmlspecialchars($r["room_no"]) ?>)</p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                  <p class="text-xs text-slate-500">เบอร์โทร</p>
                  <p class="font-semibold text-slate-800 text-sm mt-0.5"><?= htmlspecialchars($r["phone"] ?? "-") ?></p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                  <p class="text-xs text-slate-500">วันที่แจ้ง</p>
                  <p class="font-semibold text-slate-800 text-sm mt-0.5"><?= $createdDate ?></p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                  <p class="text-xs text-slate-500">วันที่สะดวกให้เข้าซ่อม</p>
                  <p class="font-semibold text-slate-800 text-sm mt-0.5"><?= $appointmentDate ?></p>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 col-span-2">
                  <p class="text-xs text-slate-500 mb-1">ปัญหา (<?= htmlspecialchars($r["category_name"]) ?>)</p>
                  <p class="text-sm text-slate-700 leading-relaxed"><?= nl2br(htmlspecialchars($r["description"])) ?></p>
                </div>

                <?php if (!empty($r["image"])): ?>
                  <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 col-span-2">
                    <p class="text-xs text-slate-500 mb-2 font-semibold">📷 รูปภาพที่แนบมา:</p>
                    <button type="button" onclick="openImageModal('../uploads/repairs/<?= htmlspecialchars($r["image"]) ?>')" class="inline-block group relative rounded-lg overflow-hidden border border-slate-300 focus:outline-none text-left w-auto">
                      <img src="../uploads/repairs/<?= htmlspecialchars($r["image"]) ?>" alt="รูปแจ้งซ่อม" class="max-h-[220px] rounded-lg object-cover group-hover:scale-105 transition duration-200">
                      <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-xs font-bold">
                        คลิกเพื่อดูรูปใหญ่
                      </div>
                    </button>
                  </div>
                <?php endif; ?>

              </div>
          </div>

          <form method="POST" class="mt-8 pt-6 border-t border-slate-200">
            <input type="hidden" name="action" value="detail">
            <input type="hidden" name="request_id" value="<?= $r["request_id"] ?>">

            <div class="flex justify-between items-center mb-5">
                <h4 class="font-bold text-slate-800 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-sm">2</span> 
                    ส่วนของการดำเนินการ (ช่าง/แอดมิน)
                </h4>
                <?php if (!empty($r["confirmed_date"]) && $r["status"] !== "เสร็จสิ้น"): ?>
                    <?php if ($isAcknowledged): ?>
                        <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold">ผู้พักรับทราบแล้ว</span>
                    <?php else: ?>
                        <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-xs font-bold">รอการรับทราบจากผู้พัก</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 gap-5 mb-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">คอนเฟิร์มวันเข้าซ่อม (ช่าง)</label>
                    <input type="date" name="confirmed_date" value="<?= $r["confirmed_date"] ?? '' ?>" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-300 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">ปรับสถานะ</label>
                    <select name="status" class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-blue-300 outline-none font-semibold text-sm">
                        <option value="รออนุมัติ" <?= $r["status"] === "รออนุมัติ" ? "selected" : "" ?>>รออนุมัติ</option>
                        <option value="กำลังดำเนินการ" <?= $r["status"] === "กำลังดำเนินการ" ? "selected" : "" ?>>กำลังดำเนินการ</option>
                        <option value="เสร็จสิ้น" <?= $r["status"] === "เสร็จสิ้น" ? "selected" : "" ?>>เสร็จสิ้น</option>
                    </select>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-2">หมายเหตุ/ข้อความถึงผู้พัก (ถ้ามี)</label>
                <textarea name="admin_remark" rows="2" placeholder="เช่น อะไหล่หมดต้องรอ 2 วัน, จะเข้าไปซ่อมช่วงบ่ายโมงตรง ฯลฯ" class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:ring-2 focus:ring-blue-300 outline-none text-sm resize-none"><?= htmlspecialchars($r["admin_remark"] ?? '') ?></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
              <button type="button" onclick="closeRepairDetail('detail-<?= $r["request_id"] ?>')" class="btn-ui bg-white text-slate-600 hover:bg-slate-100 border border-slate-300 rounded-xl px-5 py-2.5 font-semibold text-sm transition">ยกเลิก</button>
              <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl px-6 py-2.5 font-bold text-sm shadow-sm transition">บันทึกข้อมูล</button>
            </div>
          </form>

        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Modal สำหรับดูรูปภาพขยาย (Image Viewer) -->
<div id="imageViewerModal" class="hidden fixed inset-0 bg-black/80 items-center justify-center p-4 backdrop-blur-sm" style="z-index: 99999;" onclick="closeImageModal()">
    <div class="relative max-w-4xl w-full max-h-[90vh] flex justify-center items-center">
        <button type="button" class="absolute -top-10 right-0 text-white hover:text-gray-300 text-3xl font-bold transition focus:outline-none" onclick="closeImageModal()">✕</button>
        <img id="viewerImageSrc" src="" alt="รูปภาพขยาย" class="max-w-full max-h-[85vh] rounded-lg shadow-2xl object-contain cursor-auto" onclick="event.stopPropagation()">
    </div>
</div>

<script>
function openRepairDetail(id) {
  const modal = document.getElementById(id);
  document.body.appendChild(modal); 
  modal.classList.remove("hidden");
  modal.classList.add("flex");
}

function closeRepairDetail(id) {
  const modal = document.getElementById(id);
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

function openImageModal(imageSrc) {
  const modal = document.getElementById('imageViewerModal');
  const img = document.getElementById('viewerImageSrc');
  img.src = imageSrc;
  document.body.appendChild(modal); 
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeImageModal() {
  const modal = document.getElementById('imageViewerModal');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
  document.getElementById('viewerImageSrc').src = ''; 
}
</script>

<?php admin_footer(); ?>