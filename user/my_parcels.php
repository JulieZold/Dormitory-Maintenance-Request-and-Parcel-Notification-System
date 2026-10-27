<?php
require_once "../includes/auth.php";
require_user();

require_once "../config/db.php";
require_once "layout.php";

$user_id = $_SESSION["user_id"];
$room_id = $_SESSION["room_id"];

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
   PARCEL STATS
========================= */
// 1. รอรับทั้งหมด (นับเฉพาะสถานะ 'รอรับ')
$totalStmt = $pdo->prepare("
  SELECT COALESCE(SUM(quantity),0)
  FROM parcels
  WHERE room_id = ? AND status = 'รอรับ'
");
$totalStmt->execute([$room_id]);
$totalParcels = $totalStmt->fetchColumn();

// 2. รับแล้วเดือนนี้ (นับเฉพาะที่รับแล้วในเดือนปัจจุบัน)
$monthStmt = $pdo->prepare("
  SELECT COALESCE(SUM(quantity),0)
  FROM parcels
  WHERE room_id = ? 
  AND status = 'รับแล้ว'
  AND MONTH(picked_up_at) = MONTH(CURRENT_DATE())
  AND YEAR(picked_up_at) = YEAR(CURRENT_DATE())
");
$monthStmt->execute([$room_id]);
$monthParcels = $monthStmt->fetchColumn();

// 3. รวมทั้งหมด (ประวัติทั้งหมด)
$allStmt = $pdo->prepare("SELECT COUNT(*) FROM parcels WHERE room_id = ?");
$allStmt->execute([$room_id]);
$allParcelsCount = $allStmt->fetchColumn();

/* =========================
   LATEST PARCEL
========================= */
$latestStmt = $pdo->prepare("
  SELECT *
  FROM parcels p
  WHERE p.room_id = ? AND p.status = 'รอรับ'
  ORDER BY p.received_at DESC
  LIMIT 1
");
$latestStmt->execute([$room_id]);
$latestParcel = $latestStmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   SEARCH & FILTER
========================= */
$status = $_GET["status"] ?? "";
$search = trim($_GET["search"] ?? "");

$sql = "SELECT * FROM parcels WHERE room_id = ?";
$params = [$room_id];

if ($status !== "") {
  $sql .= " AND status = ?";
  $params[] = $status;
}

if ($search !== "") {
  $sql .= " AND (parcel_type LIKE ? OR courier LIKE ? OR tracking_number LIKE ? OR note LIKE ?)";
  $params[] = "%$search%";
  $params[] = "%$search%";
  $params[] = "%$search%";
  $params[] = "%$search%";
}

$sql .= " ORDER BY received_at DESC";
$historyStmt = $pdo->prepare($sql);
$historyStmt->execute($params);
$parcelHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$search_param = $search !== "" ? "&search=" . urlencode($search) : "";

user_header("parcels");
?>

<div class="max-w-[1100px] mx-auto space-y-7">

  <div>
    <h1 class="text-[30px] font-bold text-slate-800">
      พัสดุของฉัน - ห้อง <?= htmlspecialchars($user["room_no"] ?? "101") ?> (ชั้น <?= htmlspecialchars($user["floor"] ?? "-") ?>)
    </h1>
  </div>

  <div class="grid grid-cols-3 gap-5">
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
      <p class="text-slate-500 text-sm">รอรับทั้งหมด</p>
      <h2 class="text-4xl font-bold text-red-500 mt-4"><?= $totalParcels ?></h2>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
      <p class="text-slate-500 text-sm">รับแล้วเดือนนี้</p>
      <h2 class="text-4xl font-bold text-green-500 mt-4"><?= $monthParcels ?></h2>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
      <p class="text-slate-500 text-sm">รวมทั้งหมด</p>
      <h2 class="text-4xl font-bold text-slate-800 mt-4"><?= $allParcelsCount ?></h2>
    </div>
  </div>

  <?php if ($status === "" && $search === ""): ?>
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
      <h2 class="text-2xl font-bold text-slate-800 mb-5">พัสดุรอรับล่าสุด</h2>

      <?php if ($latestParcel): ?>
        <div class="border border-red-200 bg-red-50/30 rounded-2xl p-5 flex items-center justify-between hover:bg-red-50 transition">
          <div class="flex items-center gap-5">
            <div class="w-14 h-14 rounded-2xl bg-red-100 flex items-center justify-center text-2xl shrink-0">
              📦
            </div>
            <div>
              <h3 class="text-xl font-semibold text-slate-800">
                <?= htmlspecialchars($latestParcel["courier"] ?? "ไม่ระบุขนส่ง") ?> <?= htmlspecialchars($latestParcel["parcel_type"] ?? "พัสดุทั่วไป") ?>
              </h3>
              <p class="text-slate-500 mt-1">
                รับเข้าระบบ <?= date("d M Y H:i", strtotime($latestParcel["received_at"])) ?>
              </p>
            </div>
          </div>

          <div class="text-right flex items-center gap-5">
            <div>
              <div class="bg-red-100 text-red-600 px-4 py-1.5 rounded-full text-xs font-semibold inline-block">
                รอรับ
              </div>
              <p class="text-red-500 text-2xl font-bold mt-2">
                <?= $latestParcel["quantity"] ?> <span class="text-sm font-normal text-slate-400">ชิ้น</span>
              </p>
            </div>
            
            <button type="button" onclick="openParcelDetail('detail-<?= $latestParcel['parcel_id'] ?>')" class="bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-sm transition">
              ดูรายละเอียด
            </button>
          </div>
        </div>
      <?php else: ?>
        <div class="text-center py-10 text-slate-400 border border-dashed border-slate-200 rounded-2xl">
          ยังไม่มีพัสดุใหม่
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
    
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-slate-800">
        <?= !empty($search) || !empty($status) ? "ผลการค้นหาพัสดุ" : "ประวัติการรับพัสดุ" ?>
      </h2>
    </div>

    <div class="flex justify-between items-center mb-6">
      <div class="flex gap-3">
        <a href="?<?= $search !== "" ? "search=".urlencode($search) : "" ?>" 
           class="btn-ui <?= $status === '' ? 'bg-slate-800 text-white shadow-sm' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50' ?> border rounded-xl px-5 py-2 text-sm font-semibold transition">
          ทั้งหมด
        </a>
        <a href="?status=รอรับ<?= $search_param ?>" 
           class="btn-ui <?= $status === 'รอรับ' ? 'bg-red-500 text-white shadow-sm border-red-500' : 'bg-red-50 text-red-600 border-red-200 hover:bg-red-100' ?> border rounded-xl px-5 py-2 text-sm font-semibold transition">
          รอรับ
        </a>
        <a href="?status=รับแล้ว<?= $search_param ?>" 
           class="btn-ui <?= $status === 'รับแล้ว' ? 'bg-green-500 text-white shadow-sm border-green-500' : 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100' ?> border rounded-xl px-5 py-2 text-sm font-semibold transition">
          รับแล้ว
        </a>
      </div>

      <form method="GET" class="flex gap-2">
        <?php if ($status !== ""): ?>
          <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        <?php endif; ?>

        <input
          type="text"
          name="search"
          value="<?= htmlspecialchars($search) ?>"
          placeholder="ค้นหาบริษัทขนส่ง, เลขพัสดุ..."
          class="w-[260px] h-[42px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300 focus:outline-none text-sm"
        >
        <button type="submit" class="bg-slate-800 text-white px-5 h-[42px] rounded-xl text-sm font-semibold hover:bg-slate-900 transition shadow-sm">
          ค้นหา
        </button>

        <?php if ($search !== ""): ?>
          <a href="?<?= $status !== "" ? "status=".urlencode($status) : "" ?>" class="bg-white text-slate-600 px-4 h-[42px] rounded-xl text-sm font-semibold border border-slate-200 hover:bg-slate-50 transition shadow-sm flex items-center justify-center">
            ล้างค่า
          </a>
        <?php endif; ?>
      </form>
    </div>

    <div class="overflow-x-auto border border-slate-200 rounded-2xl">
      <table class="w-full">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr>
            <th class="text-left px-6 py-4 text-slate-600">วันที่รับเข้าระบบ</th>
            <th class="text-left px-6 py-4 text-slate-600">บริษัทขนส่ง</th>
            <th class="text-left px-6 py-4 text-slate-600">ประเภทพัสดุ</th>
            <th class="text-center px-6 py-4 text-slate-600">จำนวน</th>
            <th class="text-center px-6 py-4 text-slate-600">สถานะ</th>
            <th class="text-center px-6 py-4 text-slate-600">จัดการ</th>
          </tr>
        </thead>

        <tbody>
          <?php if (count($parcelHistory) > 0): ?>
            <?php foreach ($parcelHistory as $parcel): ?>
              <?php 
                $isPending = $parcel["status"] === "รอรับ";
                $statusColor = $isPending ? "bg-red-100 text-red-600 border-red-200" : "bg-green-100 text-green-700 border-green-200";
              ?>
              <tr class="border-b border-slate-100 hover:bg-slate-50 transition last:border-0">
                <td class="px-6 py-5 text-slate-600">
                  <?= date("d M Y H:i", strtotime($parcel["received_at"])) ?>
                </td>

                <td class="px-6 py-5">
                  <div class="font-bold text-slate-800">
                    <?= htmlspecialchars($parcel["courier"] ?? "ไม่ระบุ") ?>
                  </div>
                </td>

                <td class="px-6 py-5">
                  <div class="font-medium text-slate-700">
                    <?= htmlspecialchars($parcel["parcel_type"] ?? "พัสดุทั่วไป") ?>
                  </div>
                </td>

                <td class="px-6 py-5 text-center font-bold text-slate-700">
                  <?= $parcel["quantity"] ?>
                </td>

                <td class="px-6 py-5 text-center">
                  <?php if ($isPending): ?>
                    <span class="<?= $statusColor ?> px-4 py-1.5 rounded-full text-xs font-semibold inline-block">
                      รอรับ
                    </span>
                  <?php else: ?>
                    <div class="inline-flex flex-col items-center">
                      <span class="<?= $statusColor ?> px-4 py-1.5 rounded-full text-xs font-semibold mb-1">
                        รับแล้ว
                      </span>
                      <span class="text-[11px] text-slate-400">
                        <?= $parcel["picked_up_at"] ? date("d/m/y H:i", strtotime($parcel["picked_up_at"])) : "" ?>
                      </span>
                    </div>
                  <?php endif; ?>
                </td>

                <td class="px-6 py-5 text-center">
                  <button type="button" onclick="openParcelDetail('detail-<?= $parcel['parcel_id'] ?>')" class="bg-blue-50 text-blue-600 border border-blue-200 rounded-xl px-4 py-1.5 text-xs font-bold hover:bg-blue-100 transition whitespace-nowrap">
                    ดูรายละเอียด
                  </button>
                </td>
              </tr>

              <div id="detail-<?= $parcel['parcel_id'] ?>" class="hidden fixed inset-0 bg-black/40 z-50 items-center justify-center p-4 sm:p-6 backdrop-blur-sm">
                <div class="bg-white w-full max-w-[550px] rounded-2xl shadow-xl border border-slate-200 flex flex-col max-h-[90vh] overflow-hidden">
                  
                  <div class="bg-slate-800 text-white px-6 py-4 flex justify-between items-center shrink-0">
                    <h3 class="text-lg font-bold flex items-center gap-2">📦 รายละเอียดพัสดุ</h3>
                    <button type="button" onclick="closeParcelDetail('detail-<?= $parcel['parcel_id'] ?>')" class="text-white/70 hover:text-white transition text-xl">✕</button>
                  </div>

                  <div class="p-6 space-y-4 text-left overflow-y-auto">
                    
                    <div class="grid grid-cols-2 gap-4">
                      <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <p class="text-xs text-slate-500">บริษัทขนส่ง</p>
                        <p class="font-bold text-slate-800 text-sm mt-0.5"><?= htmlspecialchars($parcel["courier"] ?? "ไม่ระบุ") ?></p>
                      </div>
                      <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <p class="text-xs text-slate-500">ประเภทพัสดุ</p>
                        <p class="font-semibold text-slate-800 text-sm mt-0.5"><?= htmlspecialchars($parcel["parcel_type"] ?? "พัสดุทั่วไป") ?></p>
                      </div>
                    </div>

                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                      <p class="text-xs text-slate-500">เลขพัสดุ (Tracking Number)</p>
                      <p class="font-mono text-slate-800 font-bold text-base tracking-wide mt-0.5"><?= !empty($parcel["tracking_number"]) ? htmlspecialchars($parcel["tracking_number"]) : "-" ?></p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                      <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <p class="text-xs text-slate-500">จำนวนพัสดุ</p>
                        <p class="font-bold text-slate-800 text-sm mt-0.5"><?= $parcel["quantity"] ?> ชิ้น</p>
                      </div>
                      <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <p class="text-xs text-slate-500">วันที่รับเข้าระบบ</p>
                        <p class="font-semibold text-slate-700 text-xs mt-1"><?= date("d M Y - H:i น.", strtotime($parcel["received_at"])) ?></p>
                      </div>
                    </div>

                    <?php if (!empty($parcel["note"])): ?>
                      <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
                        <p class="text-xs text-orange-600 font-bold mb-1">หมายเหตุจากเจ้าหน้าที่</p>
                        <p class="text-sm text-orange-800 leading-relaxed"><?= nl2br(htmlspecialchars($parcel["note"])) ?></p>
                      </div>
                    <?php endif; ?>

                    <?php 
                      $imgSrc = "";
                      if (!empty($parcel["image"])) {
                          if (strpos($parcel["image"], 'uploads/') !== false) {
                              $imgSrc = "../" . $parcel["image"];
                          } else {
                              $imgSrc = "../uploads/parcels/" . $parcel["image"];
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

                    <div class="border <?= $isPending ? 'border-red-200 bg-red-50/30' : 'border-green-200 bg-green-50/30' ?> rounded-xl p-4">
                      <div class="flex justify-between items-center">
                        <div>
                          <p class="text-xs text-slate-500">สถานะพัสดุปัจจุบัน</p>
                          <span class="inline-block <?= $isPending ? 'text-red-600' : 'text-green-700' ?> font-bold text-lg mt-0.5">
                            <?= $isPending ? '⏳ รอรับพัสดุ' : '✓ รับพัสดุเรียบร้อยแล้ว' ?>
                          </span>
                        </div>
                        <?php if (!$isPending): ?>
                          <div class="text-right text-xs text-slate-500">
                            <p class="font-medium">รับเมื่อ:</p>
                            <p class="font-semibold text-slate-700"><?= date("d/m/y - H:i น.", strtotime($parcel["picked_up_at"])) ?></p>
                          </div>
                        <?php endif; ?>
                      </div>
                      
                      <?php if (!$isPending && !empty($parcel["pickup_note"])): ?>
                        <div class="mt-3 pt-3 border-t <?= $isPending ? 'border-red-200' : 'border-green-200/60' ?> text-xs text-slate-600">
                          <span class="font-bold text-green-800">บันทึกการรับ:</span> <?= htmlspecialchars($parcel["pickup_note"]) ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <div class="flex justify-end pt-2">
                      <button type="button" onclick="closeParcelDetail('detail-<?= $parcel['parcel_id'] ?>')" class="w-full sm:w-auto bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-6 py-2.5 rounded-xl transition text-sm shadow-sm">
                        ปิดหน้าต่าง
                      </button>
                    </div>

                  </div>
                </div>
              </div>

            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="px-6 py-12 text-center text-slate-500 text-lg">
                <?php if (!empty($search)): ?>
                  ไม่พบพัสดุที่ตรงกับ "<b><?= htmlspecialchars($search) ?></b>"
                <?php else: ?>
                  ยังไม่มีประวัติพัสดุ
                <?php endif; ?>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
function openParcelDetail(id) {
  const modal = document.getElementById(id);
  if(modal) {
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

<?php user_footer(); ?>