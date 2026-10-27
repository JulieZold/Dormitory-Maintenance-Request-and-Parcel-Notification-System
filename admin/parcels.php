<?php
require_once "../includes/auth.php";
require_admin();

require_once "../config/db.php";
require_once "layout.php";

// ดึง ID ของ Admin ที่ล็อกอินอยู่
$admin_id = $_SESSION["user_id"] ?? 1;

/* =========================
   Process Actions (บันทึก / รับพัสดุ)
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // 1. กรณีบันทึกพัสดุเข้าใหม่
    if (isset($_POST["action"]) && $_POST["action"] === "add") {
        $room_id = $_POST["room_id"];
        $quantity = $_POST["quantity"];
        $tracking_number = trim($_POST["tracking_number"]);
        $note = trim($_POST["note"]);
        $imagePath = null;

        // จัดการค่า บริษัทขนส่ง
        $courier = $_POST["courier"];
        if ($courier === "อื่น ๆ") {
            $courier = trim($_POST["courier_other"]);
        }

        // จัดการค่า ประเภทพัสดุ
        $parcel_type = $_POST["parcel_type"];
        if ($parcel_type === "อื่น ๆ") {
            $parcel_type = trim($_POST["parcel_type_other"]);
        }

        // จัดการอัปโหลดรูปภาพ
        if (!empty($_FILES["parcel_image"]["name"])) {
            $targetDir = "../uploads/parcels/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $imageName = time() . "_" . basename($_FILES["parcel_image"]["name"]);
            if (move_uploaded_file($_FILES["parcel_image"]["tmp_name"], $targetDir . $imageName)) {
                $imagePath = "uploads/parcels/" . $imageName;
            }
        }

        // บันทึกข้อมูลพัสดุ
        $stmt = $pdo->prepare("
            INSERT INTO parcels (room_id, recorded_by, courier, parcel_type, tracking_number, quantity, note, image, status, received_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'รอรับ', NOW())
        ");
        $stmt->execute([$room_id, $admin_id, $courier, $parcel_type, $tracking_number, $quantity, $note, $imagePath]);
        
        // ส่งแจ้งเตือน (Notification) หาผู้พัก
        $userStmt = $pdo->prepare("SELECT user_id FROM users WHERE room_id = ?");
        $userStmt->execute([$room_id]);
        $roomUsers = $userStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($roomUsers) > 0) {
            $message = "📦 คุณมีพัสดุใหม่จำนวน {$quantity} ชิ้น จากขนส่ง {$courier} (พัสดุ: {$parcel_type}) รอรับได้ที่ห้องนิติบุคคลค่ะ";

            try {
                $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                foreach ($roomUsers as $u) {
                    $notifStmt->execute([$u['user_id'], $message]);
                }
            } catch (Exception $e) {
                // กันการทำงานค้าง
            }
        }

        header("Location: parcels.php");
        exit;
    }

    // 2. กรณีกดยืนยันให้ผู้พักมารับของ
    if (isset($_POST["action"]) && $_POST["action"] === "pickup") {
        $parcel_id = $_POST["parcel_id"];
        $pickup_note = trim($_POST["pickup_note"]);

        $stmt = $pdo->prepare("
            UPDATE parcels 
            SET status = 'รับแล้ว', picked_up_at = NOW(), pickup_note = ? 
            WHERE parcel_id = ? AND status = 'รอรับ'
        ");
        $stmt->execute([$pickup_note, $parcel_id]);
        
        header("Location: parcels.php");
        exit;
    }
}

/* =========================
   Data Fetching
========================= */
$rooms = $pdo->query("
  SELECT * 
  FROM rooms
  ORDER BY room_no ASC
")->fetchAll(PDO::FETCH_ASSOC);

$status_filter = $_GET["status"] ?? "รอรับ";
$search = trim($_GET["search"] ?? "");

$sql = "
  SELECT p.*, r.room_no
  FROM parcels p
  JOIN rooms r ON p.room_id = r.room_id
  WHERE 1=1
";
$params = [];

if ($status_filter !== "all") {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($search !== "") {
    $sql .= " AND (r.room_no LIKE ? OR p.tracking_number LIKE ? OR p.courier LIKE ? OR p.note LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.status DESC, p.received_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$latest = $stmt->fetchAll(PDO::FETCH_ASSOC);

admin_header("parcels");
?>

<div class="max-w-[1200px] mx-auto space-y-6">

  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-[28px] font-bold text-slate-800">จัดการพัสดุ</h1>
      <p class="text-slate-500 mt-1">บันทึกพัสดุเข้าหอพัก และจัดการการรับพัสดุ</p>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <!-- ===================================== -->
    <!-- ฝั่งซ้าย: ฟอร์มเพิ่มพัสดุ -->
    <!-- ===================================== -->
    <div class="lg:col-span-5">
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-7">
        <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2">
          <span class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-sm">📥</span>
          บันทึกพัสดุเข้าใหม่
        </h2>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
          <input type="hidden" name="action" value="add">

          <div>
            <label class="block mb-1.5 text-slate-700 font-medium text-sm">เลือกห้องพัก <span class="text-red-500">*</span></label>
            <select name="room_id" required class="w-full h-[48px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300 outline-none">
              <option value="">-- เลือกห้อง --</option>
              <?php foreach ($rooms as $room): ?>
                <option value="<?= $room["room_id"] ?>">
                  ห้อง <?= htmlspecialchars($room["room_no"]) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block mb-1.5 text-slate-700 font-medium text-sm">บริษัทขนส่ง <span class="text-red-500">*</span></label>
            <select name="courier" id="courier_select" required class="w-full h-[48px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300 outline-none">
              <option value="">-- เลือกบริษัทขนส่ง --</option>
              <option value="ไปรษณีย์ไทย (Thailand Post)">ไปรษณีย์ไทย (Thailand Post)</option>
              <option value="KEX Express (Kerry Express)">KEX Express (Kerry Express)</option>
              <option value="Flash Express">Flash Express</option>
              <option value="J&T Express">J&T Express</option>
              <option value="SPX Express">SPX Express</option>
              <option value="อื่น ๆ">อื่น ๆ (ระบุเองด้านล่าง)</option>
            </select>
          </div>

          <div id="courier_other_div" class="hidden animate-fadeIn">
            <label class="block mb-1.5 text-orange-700 font-semibold text-sm">ระบุชื่อบริษัทขนส่งอื่น <span class="text-red-500">*</span></label>
            <input type="text" name="courier_other" id="courier_other_input" placeholder="เช่น LINE MAN, Grab, DHL" class="w-full h-[48px] rounded-xl border border-orange-300 bg-orange-50/20 px-4 focus:ring-2 focus:ring-orange-300 outline-none">
          </div>

          <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2">
              <label class="block mb-1.5 text-slate-700 font-medium text-sm">ประเภทพัสดุ <span class="text-red-500">*</span></label>
              <select name="parcel_type" id="parcel_type_select" required class="w-full h-[48px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300 outline-none">
                <option value="">-- เลือกประเภท --</option>
                <option value="พัสดุเอกสาร">พัสดุเอกสาร</option>
                <option value="พัสดุสินค้าทั่วไป">พัสดุสินค้าทั่วไป</option>
                <option value="พัสดุเปราะบาง">พัสดุเปราะบาง</option>
                <option value="พัสดุของเหลวและเจล">พัสดุของเหลวและเจล</option>
                <option value="พัสดุอันตราย">พัสดุอันตราย</option>
                <option value="พัสดุควบคุมอุณหภูมิ">พัสดุควบคุมอุณหภูมิ</option>
                <option value="พัสดุขนาดใหญ่">พัสดุขนาดใหญ่</option>
                <option value="อื่น ๆ">อื่น ๆ (ระบุเอง)</option>
              </select>
            </div>
            <div>
              <label class="block mb-1.5 text-slate-700 font-medium text-sm">จำนวน (ชิ้น) <span class="text-red-500">*</span></label>
              <input type="number" name="quantity" min="1" value="1" required class="w-full h-[48px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300 outline-none text-center font-bold">
            </div>
          </div>

          <div id="parcel_type_other_div" class="hidden">
            <label class="block mb-1.5 text-orange-700 font-semibold text-sm">ระบุประเภทพัสดุอื่น <span class="text-red-500">*</span></label>
            <input type="text" name="parcel_type_other" id="parcel_type_other_input" placeholder="เช่น อาหารสด, ต้นไม้, ยารักษาโรค" class="w-full h-[48px] rounded-xl border border-orange-300 bg-orange-50/20 px-4 focus:ring-2 focus:ring-orange-300 outline-none">
          </div>

          <div>
            <label class="block mb-1.5 text-slate-700 font-medium text-sm">เลขพัสดุ (Tracking Number)</label>
            <input type="text" name="tracking_number" placeholder="กรอกเลขพัสดุ (ถ้ามี)" class="w-full h-[48px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300 outline-none font-mono text-sm tracking-wide">
          </div>

          <div>
            <label class="block mb-1.5 text-slate-700 font-medium text-sm">แนบรูปหน้ากล่อง (ถ้ามี)</label>
            <input type="file" name="parcel_image" accept="image/*" class="w-full border border-slate-300 rounded-xl px-4 py-2.5 bg-slate-50 text-sm">
          </div>

          <div>
            <label class="block mb-1.5 text-slate-700 font-medium text-sm">หมายเหตุ</label>
            <textarea name="note" rows="2" placeholder="เช่น กล่องบุบ, ของสดต้องรีบมารับ" class="w-full rounded-xl border border-slate-300 px-4 py-3 focus:ring-2 focus:ring-blue-300 outline-none resize-none text-sm"></textarea>
          </div>

          <button type="submit" class="w-full h-[52px] rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold transition shadow mt-2">
            บันทึกเข้าระบบ
          </button>
        </form>
      </div>
    </div>

    <!-- ===================================== -->
    <!-- ฝั่งขวา: รายการพัสดุ -->
    <!-- ===================================== -->
    <div class="lg:col-span-7">
      <div class="bg-white rounded-2xl border border-slate-200 shadow-sm flex flex-col h-[850px] overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex flex-col gap-4 shrink-0">
          <div class="flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
              <span class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center text-sm">📦</span>
              รายการพัสดุ
            </h2>
            <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-bold">
              <?= count($latest) ?> รายการ
            </span>
          </div>

          <form method="GET" class="flex flex-col sm:flex-row justify-between items-center gap-3">
            <div class="flex gap-2 w-full sm:w-auto">
              <button type="submit" name="status" value="all" class="px-4 py-1.5 rounded-lg text-sm font-semibold transition <?= $status_filter === 'all' ? 'bg-slate-800 text-white' : 'bg-white border border-slate-300 text-slate-600 hover:bg-slate-100' ?>">ทั้งหมด</button>
              <button type="submit" name="status" value="รอรับ" class="px-4 py-1.5 rounded-lg text-sm font-semibold transition <?= $status_filter === 'รอรับ' ? 'bg-orange-500 text-white border-orange-500' : 'bg-white border border-orange-200 text-orange-600 hover:bg-orange-50' ?>">รอรับ</button>
              <button type="submit" name="status" value="รับแล้ว" class="px-4 py-1.5 rounded-lg text-sm font-semibold transition <?= $status_filter === 'รับแล้ว' ? 'bg-green-500 text-white border-green-500' : 'bg-white border border-green-200 text-green-700 hover:bg-green-50' ?>">รับแล้ว</button>
            </div>
            
            <div class="flex gap-2 w-full sm:w-auto flex-1 max-w-[250px]">
              <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาห้อง, ขนส่ง, เลขพัสดุ..." class="w-full h-[34px] rounded-lg border border-slate-300 px-3 text-sm focus:outline-none focus:border-blue-500">
              <button type="submit" class="bg-slate-800 text-white px-3 h-[34px] rounded-lg text-sm hover:bg-slate-700 transition">ค้นหา</button>
            </div>
          </form>
        </div>

        <div class="p-6 space-y-4 overflow-y-auto">
          <?php if (count($latest) > 0): ?>
            <?php foreach ($latest as $item): ?>
              <?php $isPending = $item["status"] === "รอรับ"; ?>

              <div class="border <?= $isPending ? 'border-orange-200 bg-orange-50/20' : 'border-slate-200 bg-white' ?> rounded-2xl p-5 hover:shadow-md transition">
                <div class="flex justify-between items-start">
                  
                  <div class="flex gap-4">
                    <div class="w-12 h-12 rounded-full <?= $isPending ? 'bg-orange-100 text-orange-600' : 'bg-green-100 text-green-600' ?> flex items-center justify-center text-xl shrink-0">
                      <?= $isPending ? '⏳' : '✓' ?>
                    </div>
                    <div>
                      <div class="flex items-baseline gap-2 mb-1">
                        <p class="font-bold text-slate-800 text-lg">ห้อง <?= htmlspecialchars($item["room_no"]) ?></p>
                      </div>
                      
                      <div class="text-sm text-slate-600 space-y-1">
                        <p><span class="font-semibold text-slate-500">บริษัทขนส่ง:</span> <span class="text-slate-800 font-medium"><?= htmlspecialchars($item["courier"] ?? "ไม่ระบุ") ?></span></p>
                        <p><span class="font-semibold text-slate-500">ประเภทพัสดุ:</span> <span class="text-slate-800 font-medium"><?= htmlspecialchars($item["parcel_type"] ?? "พัสดุทั่วไป") ?></span></p>
                        <p class="text-slate-400 text-xs pt-1">บันทึกเมื่อ: <?= !empty($item["received_at"]) ? date("d M Y H:i", strtotime($item["received_at"])) : '-' ?></p>
                      </div>
                    </div>
                  </div>

                  <div class="text-right">
                    <p class="text-3xl font-bold <?= $isPending ? 'text-orange-500' : 'text-slate-700' ?>">
                      <?= $item["quantity"] ?> <span class="text-sm font-normal text-slate-500">ชิ้น</span>
                    </p>
                  </div>

                </div>

                <div class="my-4 border-t <?= $isPending ? 'border-orange-200' : 'border-slate-100' ?>"></div>

                <div class="flex items-end justify-between gap-4">
                  <div class="text-sm">
                    <?php if (!empty($item["note"])): ?>
                      <p class="text-slate-600 mb-2"><span class="font-semibold text-orange-600">โน้ต:</span> <?= htmlspecialchars($item["note"]) ?></p>
                    <?php endif; ?>
                    
                    <button type="button" onclick="openParcelDetail('detail-<?= $item['parcel_id'] ?>')" class="bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 font-semibold px-4 py-1.5 rounded-xl transition text-xs flex items-center gap-1.5">
                      รายละเอียด
                    </button>
                  </div>

                  <div>
                    <?php if ($isPending): ?>
                      <button onclick="openPickupModal(<?= $item['parcel_id'] ?>, '<?= htmlspecialchars($item['room_no']) ?>')" class="bg-blue-500 hover:bg-blue-600 text-white font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm transition whitespace-nowrap">
                        กดรับพัสดุ
                      </button>
                    <?php else: ?>
                      <div class="text-right">
                        <span class="inline-block bg-green-100 text-green-700 font-bold text-xs px-3 py-1 rounded-full mb-1">ลูกบ้านรับแล้ว</span>
                        <?php if(!empty($item["picked_up_at"])): ?>
                          <div class="text-[11px] text-slate-400">เวลารับ: <?= date("H:i", strtotime($item["picked_up_at"])) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($item["pickup_note"])): ?>
                          <div class="text-[11px] text-slate-500 mt-1 max-w-[180px] truncate" title="<?= htmlspecialchars($item["pickup_note"]) ?>">
                            โดย: <?= htmlspecialchars($item["pickup_note"]) ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

            <?php endforeach; ?>
          <?php else: ?>
            <div class="flex flex-col justify-center items-center h-full text-slate-400 space-y-3 opacity-60 pt-20">
              <span class="text-6xl">📬</span>
              <p class="font-medium">ไม่พบพัสดุในหมวดหมู่นี้</p>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- ===================================== -->
<!-- Modal: รายละเอียดพัสดุแบบเต็ม (Popup) -->
<!-- ===================================== -->
<?php if (count($latest) > 0): ?>
  <?php foreach ($latest as $item): ?>
    <?php $isPending = $item["status"] === "รอรับ"; ?>
    <div id="detail-<?= $item['parcel_id'] ?>" class="hidden fixed inset-0 bg-black/40 items-center justify-center p-4 sm:p-6 backdrop-blur-sm" style="z-index: 99998;">
      <div class="bg-white w-full max-w-[550px] rounded-2xl shadow-xl border border-slate-200 flex flex-col max-h-[90vh] overflow-hidden">
        
        <div class="bg-slate-800 text-white px-6 py-4 flex justify-between items-center shrink-0">
          <h3 class="text-lg font-bold flex items-center gap-2">📦 รายละเอียดพัสดุ (ห้อง <?= htmlspecialchars($item["room_no"]) ?>)</h3>
          <button type="button" onclick="closeParcelDetail('detail-<?= $item['parcel_id'] ?>')" class="text-white/70 hover:text-white transition text-xl">✕</button>
        </div>

        <div class="p-6 space-y-4 text-left overflow-y-auto">
          
          <div class="grid grid-cols-2 gap-4">
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
              <p class="text-xs text-slate-500">บริษัทขนส่ง</p>
              <p class="font-bold text-slate-800 text-sm mt-0.5"><?= htmlspecialchars($item["courier"] ?? "ไม่ระบุ") ?></p>
            </div>
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
              <p class="text-xs text-slate-500">ประเภทพัสดุ</p>
              <p class="font-semibold text-slate-800 text-sm mt-0.5"><?= htmlspecialchars($item["parcel_type"] ?? "พัสดุทั่วไป") ?></p>
            </div>
          </div>

          <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
            <p class="text-xs text-slate-500">เลขพัสดุ (Tracking Number)</p>
            <p class="font-mono text-slate-800 font-bold text-base tracking-wide mt-0.5"><?= !empty($item["tracking_number"]) ? htmlspecialchars($item["tracking_number"]) : "-" ?></p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
              <p class="text-xs text-slate-500">จำนวนพัสดุ</p>
              <p class="font-bold text-slate-800 text-sm mt-0.5"><?= $item["quantity"] ?> ชิ้น</p>
            </div>
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
              <p class="text-xs text-slate-500">วันที่รับเข้าระบบ</p>
              <p class="font-semibold text-slate-700 text-xs mt-1"><?= !empty($item["received_at"]) ? date("d M Y - H:i น.", strtotime($item["received_at"])) : '-' ?></p>
            </div>
          </div>

          <?php if (!empty($item["note"])): ?>
            <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
              <p class="text-xs text-orange-600 font-bold mb-1">หมายเหตุจากแอดมิน</p>
              <p class="text-sm text-orange-800 leading-relaxed"><?= nl2br(htmlspecialchars($item["note"])) ?></p>
            </div>
          <?php endif; ?>

          <?php 
            $imgSrc = "";
            if (!empty($item["image"])) {
                if (strpos($item["image"], 'uploads/') !== false) {
                    $imgSrc = "../" . $item["image"];
                } else {
                    $imgSrc = "../uploads/parcels/" . $item["image"];
                }
            }
          ?>
          <?php if (!empty($imgSrc) && file_exists($imgSrc)): ?>
            <div class="border border-slate-200 rounded-xl p-2 bg-slate-50 text-center">
              <p class="text-xs text-slate-500 text-left mb-2 px-1">📸 รูปภาพหน้ากล่องพัสดุ</p>
              <a href="<?= htmlspecialchars($imgSrc) ?>" target="_blank" class="block relative group">
                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Parcel Image" class="max-h-[220px] mx-auto rounded-lg object-contain shadow-sm group-hover:opacity-90 transition">
                <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition bg-black/10 rounded-lg pointer-events-none">
                  <span class="bg-black/60 text-white text-xs px-3 py-1.5 rounded-full font-bold shadow-md">คลิกเพื่อดูรูปใหญ่</span>
                </div>
              </a>
            </div>
          <?php endif; ?>

          <div class="border <?= $isPending ? 'border-orange-200 bg-orange-50/30' : 'border-green-200 bg-green-50/30' ?> rounded-xl p-4">
            <div class="flex justify-between items-center">
              <div>
                <p class="text-xs text-slate-500">สถานะพัสดุปัจจุบัน</p>
                <span class="inline-block <?= $isPending ? 'text-orange-600' : 'text-green-700' ?> font-bold text-lg mt-0.5">
                  <?= $isPending ? '⏳ รอรับพัสดุ' : '✓ รับพัสดุเรียบร้อยแล้ว' ?>
                </span>
              </div>
              <?php if (!$isPending && !empty($item["picked_up_at"])): ?>
                <div class="text-right text-xs text-slate-500">
                  <p class="font-medium">รับเมื่อ:</p>
                  <p class="font-semibold text-slate-700"><?= date("d/m/y - H:i น.", strtotime($item["picked_up_at"])) ?></p>
                </div>
              <?php endif; ?>
            </div>
            
            <?php if (!$isPending && !empty($item["pickup_note"])): ?>
              <div class="mt-3 pt-3 border-t <?= $isPending ? 'border-orange-200' : 'border-green-200/60' ?> text-xs text-slate-600">
                <span class="font-bold text-green-800">บันทึกการรับ:</span> <?= htmlspecialchars($item["pickup_note"]) ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="flex justify-end pt-2">
            <button type="button" onclick="closeParcelDetail('detail-<?= $item['parcel_id'] ?>')" class="w-full sm:w-auto bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-6 py-2.5 rounded-xl transition text-sm shadow-sm">
              ปิดหน้าต่าง
            </button>
          </div>

        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- ===================================== -->
<!-- Modal: ยืนยันการรับพัสดุ -->
<!-- ===================================== -->
<div id="pickupModal" class="hidden fixed inset-0 bg-black/40 items-center justify-center p-4 backdrop-blur-sm" style="z-index: 99998;">
  <div class="bg-white w-full max-w-[400px] rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-blue-600 text-white px-5 py-4 flex justify-between items-center">
      <h3 class="font-bold text-lg">ยืนยันการรับพัสดุ</h3>
      <button type="button" onclick="closePickupModal()" class="text-white/70 hover:text-white transition">✕</button>
    </div>
    <form method="POST" class="p-6">
      <input type="hidden" name="action" value="pickup">
      <input type="hidden" name="parcel_id" id="modal_parcel_id" value="">
      
      <p class="text-slate-700 font-medium text-center mb-4 text-lg">
        ยืนยันการส่งมอบพัสดุให้ <br> <span class="text-blue-600 font-bold text-2xl" id="modal_room_no">ห้อง 000</span>
      </p>

      <div class="mb-6">
        <label class="block text-sm font-semibold text-slate-700 mb-2">ผู้มารับ / หมายเหตุ (ถ้ามี)</label>
        <input type="text" name="pickup_note" placeholder="เช่น เจ้าตัวมารับเอง, เพื่อนชื่อบอยมารับแทน" class="w-full h-[45px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300 outline-none text-sm">
      </div>

      <div class="flex gap-3">
        <button type="button" onclick="closePickupModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 rounded-xl transition">ยกเลิก</button>
        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-xl shadow-sm transition">ยืนยันรับพัสดุ</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('courier_select').addEventListener('change', function() {
    const otherDiv = document.getElementById('courier_other_div');
    const otherInput = document.getElementById('courier_other_input');
    if (this.value === 'อื่น ๆ') {
        otherDiv.classList.remove('hidden');
        otherInput.required = true;
        otherInput.focus();
    } else {
        otherDiv.classList.add('hidden');
        otherInput.required = false;
        otherInput.value = '';
    }
});

document.getElementById('parcel_type_select').addEventListener('change', function() {
    const otherDiv = document.getElementById('parcel_type_other_div');
    const otherInput = document.getElementById('parcel_type_other_input');
    if (this.value === 'อื่น ๆ') {
        otherDiv.classList.remove('hidden');
        otherInput.required = true;
        otherInput.focus();
    } else {
        otherDiv.classList.add('hidden');
        otherInput.required = false;
        otherInput.value = '';
    }
});

function openPickupModal(parcelId, roomNo) {
  document.getElementById('modal_parcel_id').value = parcelId;
  document.getElementById('modal_room_no').innerText = 'ห้อง ' + roomNo;
  const modal = document.getElementById('pickupModal');
  document.body.appendChild(modal); 
  modal.classList.remove("hidden");
  modal.classList.add("flex");
}
function closePickupModal() {
  const modal = document.getElementById('pickupModal');
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

function openParcelDetail(id) {
  const modal = document.getElementById(id);
  if(modal) {
    document.body.appendChild(modal); 
    modal.classList.remove("hidden");
    modal.classList.add("flex");
  }
}
function closeParcelDetail(id) {
  const modal = document.getElementById(id);
  if(modal) {
    modal.classList.add("hidden");
    modal.classList.remove("flex");
  }
}
</script>

<?php admin_footer(); ?>