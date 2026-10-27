<?php
require_once "../includes/auth.php";
require_user();

require_once "../config/db.php";
require_once "layout.php";

$user_id = $_SESSION["user_id"];
$room_id = $_SESSION["room_id"];

/* USER INFO */
$stmt = $pdo->prepare("
  SELECT u.*, r.room_no, r.floor
  FROM users u
  LEFT JOIN rooms r ON u.room_id = r.room_id
  WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$_SESSION["room_no"] = $user["room_no"] ?? "101";

/* REPAIR STATS */
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM repair_requests WHERE user_id = ?");
$totalStmt->execute([$user_id]);
$totalRepairs = $totalStmt->fetchColumn();

$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM repair_requests WHERE user_id = ? AND status = 'รออนุมัติ'");
$pendingStmt->execute([$user_id]);
$pendingCount = $pendingStmt->fetchColumn();

$progressStmt = $pdo->prepare("SELECT COUNT(*) FROM repair_requests WHERE user_id = ? AND status = 'กำลังดำเนินการ'");
$progressStmt->execute([$user_id]);
$progressCount = $progressStmt->fetchColumn();

/* PARCEL STATS */
$parcelStmt = $pdo->prepare("
  SELECT COALESCE(SUM(quantity),0)
  FROM parcels
  WHERE room_id = ? AND status = 'รอรับ'
");
$parcelStmt->execute([$room_id]);
$totalParcels = $parcelStmt->fetchColumn();

/* =========================
   SEARCH LOGIC (ระบบค้นหา)
========================= */
$search = trim($_GET["search"] ?? "");
$searchRepairs = [];
$searchParcels = [];

if ($search !== "") {
    // ค้นหาในประวัติแจ้งซ่อม
    $srStmt = $pdo->prepare("
        SELECT rr.*, c.category_name
        FROM repair_requests rr
        LEFT JOIN repair_categories c ON rr.category_id = c.category_id
        WHERE rr.user_id = ? AND (rr.description LIKE ? OR c.category_name LIKE ?)
        ORDER BY rr.created_at DESC
    ");
    $srStmt->execute([$user_id, "%$search%", "%$search%"]);
    $searchRepairs = $srStmt->fetchAll(PDO::FETCH_ASSOC);

    // ค้นหาในประวัติพัสดุ
    $spStmt = $pdo->prepare("
        SELECT *
        FROM parcels
        WHERE room_id = ? AND (parcel_type LIKE ? OR note LIKE ?)
        ORDER BY received_at DESC
    ");
    $spStmt->execute([$room_id, "%$search%", "%$search%"]);
    $searchParcels = $spStmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    /* LATEST REPAIR */
    $latestRepairStmt = $pdo->prepare("
      SELECT rr.*, c.category_name
      FROM repair_requests rr
      LEFT JOIN repair_categories c ON rr.category_id = c.category_id
      WHERE rr.user_id = ?
      ORDER BY rr.created_at DESC
      LIMIT 1
    ");
    $latestRepairStmt->execute([$user_id]);
    $latestRepair = $latestRepairStmt->fetch(PDO::FETCH_ASSOC);

    /* LATEST PARCEL */
    $latestParcelStmt = $pdo->prepare("
      SELECT *
      FROM parcels
      WHERE room_id = ?
      ORDER BY received_at DESC
      LIMIT 1
    ");
    $latestParcelStmt->execute([$room_id]);
    $latestParcel = $latestParcelStmt->fetch(PDO::FETCH_ASSOC);
}

user_header("home");
?>

<div class="max-w-[1200px] mx-auto space-y-7">

  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-[30px] font-bold text-slate-800">
        สวัสดี คุณ <?= htmlspecialchars($user["full_name"] ?? "-") ?>
      </h1>

      <p class="text-slate-500 mt-1 text-[16px]">
        ห้อง <?= htmlspecialchars($user["room_no"] ?? "-") ?>
        (ชั้น <?= htmlspecialchars($user["floor"] ?? "-") ?>)
      </p>
    </div>

    <div class="flex items-center gap-3">
        <form method="GET" class="flex gap-2">
            <input 
                type="text" 
                name="search" 
                value="<?= htmlspecialchars($search) ?>" 
                placeholder="ค้นหาแจ้งซ่อม, พัสดุ..." 
                class="w-[260px] h-[46px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300 focus:outline-none text-sm"
            >
            <button type="submit" class="bg-slate-800 text-white px-5 h-[46px] rounded-xl text-sm font-semibold hover:bg-slate-900 transition shadow-sm">
                ค้นหา
            </button>
            <?php if ($search !== ""): ?>
                <a href="dashboard.php" class="bg-white text-slate-600 px-4 h-[46px] rounded-xl text-sm font-semibold border border-slate-200 hover:bg-slate-50 transition shadow-sm flex items-center justify-center">
                    ล้างค่า
                </a>
            <?php endif; ?>
        </form>

        <a href="repair_create.php"
           class="bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-5 h-[46px] flex items-center justify-center rounded-xl shadow-md hover:shadow-lg transition">
          + แจ้งซ่อมใหม่
        </a>
    </div>
  </div>

  <div class="grid grid-cols-4 gap-5">
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
      <p class="text-slate-500 text-sm">รายการแจ้งซ่อมทั้งหมด</p>
      <h2 class="text-4xl font-bold text-blue-500 mt-4"><?= $totalRepairs ?></h2>
      <p class="text-slate-400 text-sm mt-2">รายการ</p>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
      <p class="text-slate-500 text-sm">รออนุมัติ</p>
      <h2 class="text-4xl font-bold text-orange-500 mt-4"><?= $pendingCount ?></h2>
      <p class="text-slate-400 text-sm mt-2">รายการ</p>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
      <p class="text-slate-500 text-sm">กำลังดำเนินการ</p>
      <h2 class="text-4xl font-bold text-blue-500 mt-4"><?= $progressCount ?></h2>
      <p class="text-slate-400 text-sm mt-2">รายการ</p>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
      <p class="text-slate-500 text-sm">พัสดุรอรับ</p>
      <h2 class="text-4xl font-bold text-red-500 mt-4"><?= $totalParcels ?></h2>
      <p class="text-slate-400 text-sm mt-2">ชิ้น</p>
    </div>
  </div>

  <?php if ($search !== ""): ?>
    
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
      <h2 class="text-2xl font-bold text-slate-800 mb-2">ผลการค้นหา: "<?= htmlspecialchars($search) ?>"</h2>
      <p class="text-slate-500 mb-6">พบแจ้งซ่อม <?= count($searchRepairs) ?> รายการ และ พัสดุ <?= count($searchParcels) ?> รายการ</p>

      <div class="grid grid-cols-2 gap-6">
        <div>
          <h3 class="text-lg font-bold text-slate-700 mb-4 flex items-center gap-2"><span class="text-xl">💡</span> แจ้งซ่อม</h3>
          <div class="space-y-3 max-h-[400px] overflow-y-auto pr-2">
            <?php if (count($searchRepairs) > 0): ?>
              <?php foreach ($searchRepairs as $r): ?>
                <?php
                  $statusClass = "bg-orange-100 text-orange-700";
                  if ($r["status"] === "กำลังดำเนินการ") $statusClass = "bg-blue-100 text-blue-700";
                  if ($r["status"] === "เสร็จสิ้น") $statusClass = "bg-green-100 text-green-700";
                ?>
                <a href="repair_history.php" class="block border border-slate-200 rounded-2xl p-4 flex items-center justify-between hover:bg-blue-50/30 hover:border-blue-300 transition cursor-pointer">
                  <div>
                    <h3 class="font-semibold text-slate-800"><?= htmlspecialchars($r["description"] ?? "-") ?></h3>
                    <p class="text-slate-500 text-sm mt-1">หมวดหมู่: <?= htmlspecialchars($r["category_name"] ?? "-") ?></p>
                    <p class="text-slate-400 text-xs mt-1"><?= date("d/m/Y H:i", strtotime($r["created_at"])) ?></p>
                  </div>
                  <div class="<?= $statusClass ?> px-4 py-2 rounded-full text-xs font-semibold shrink-0">
                    <?= htmlspecialchars($r["status"]) ?>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="text-center py-6 border border-dashed border-slate-200 rounded-2xl text-slate-400">ไม่พบรายการแจ้งซ่อม</div>
            <?php endif; ?>
          </div>
        </div>

        <div>
          <h3 class="text-lg font-bold text-slate-700 mb-4 flex items-center gap-2"><span class="text-xl">📦</span> พัสดุ</h3>
          <div class="space-y-3 max-h-[400px] overflow-y-auto pr-2">
            <?php if (count($searchParcels) > 0): ?>
              <?php foreach ($searchParcels as $p): ?>
                <a href="my_parcels.php" class="block border border-slate-200 rounded-2xl p-4 flex items-center justify-between hover:bg-blue-50/30 hover:border-blue-300 transition cursor-pointer">
                  <div>
                    <h3 class="font-semibold text-slate-800"><?= htmlspecialchars($p["parcel_type"] ?? "พัสดุทั่วไป") ?></h3>
                    <?php if (!empty($p["note"])): ?>
                      <p class="text-slate-500 text-sm mt-1">หมายเหตุ: <?= htmlspecialchars($p["note"]) ?></p>
                    <?php endif; ?>
                    <p class="text-slate-400 text-xs mt-1">รับเมื่อ: <?= date("d/m/Y H:i", strtotime($p["received_at"])) ?></p>
                  </div>
                  <div class="text-right shrink-0">
                    <?php if ($p["status"] === "รอรับ"): ?>
                      <div class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-xs font-semibold inline-block mb-1">ยังไม่รับ</div>
                    <?php else: ?>
                      <div class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-semibold inline-block mb-1">รับแล้ว</div>
                    <?php endif; ?>
                    <p class="text-slate-700 font-bold text-sm mt-1"><?= $p["quantity"] ?> ชิ้น</p>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="text-center py-6 border border-dashed border-slate-200 rounded-2xl text-slate-400">ไม่พบรายการพัสดุ</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  <?php else: ?>
    
    <div class="grid grid-cols-2 gap-6">
      <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
        <h2 class="text-2xl font-bold text-slate-800 mb-5">การแจ้งซ่อมล่าสุด</h2>

        <?php if ($latestRepair): ?>
          <?php
            $statusClass = "bg-orange-100 text-orange-700";
            if ($latestRepair["status"] === "กำลังดำเนินการ") $statusClass = "bg-blue-100 text-blue-700";
            if ($latestRepair["status"] === "เสร็จสิ้น") $statusClass = "bg-green-100 text-green-700";
          ?>

          <a href="repair_history.php" class="block border border-slate-200 rounded-2xl p-5 flex items-center justify-between hover:bg-blue-50/50 hover:border-blue-300 hover:shadow-sm transition cursor-pointer">
            <div class="flex items-center gap-5">
              <div class="w-14 h-14 rounded-2xl bg-yellow-100 flex items-center justify-center text-2xl shrink-0">💡</div>

              <div>
                <h3 class="text-xl font-semibold text-slate-800">
                  <?= htmlspecialchars($latestRepair["description"] ?? "-") ?>
                </h3>
                <p class="text-slate-500 mt-1">
                  แจ้งเมื่อ <?= date("d M Y", strtotime($latestRepair["created_at"])) ?>
                </p>
              </div>
            </div>

            <div class="<?= $statusClass ?> px-5 py-3 rounded-full text-sm font-semibold whitespace-nowrap">
              <?= htmlspecialchars($latestRepair["status"]) ?>
            </div>
          </a>
        <?php else: ?>
          <div class="text-center py-10 text-slate-400 border border-dashed border-slate-200 rounded-2xl">ยังไม่มีรายการแจ้งซ่อม</div>
        <?php endif; ?>
      </div>

      <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
        <h2 class="text-2xl font-bold text-slate-800 mb-5">พัสดุรอรับล่าสุด</h2>

        <?php if ($latestParcel): ?>
          <a href="my_parcels.php" class="block border border-slate-200 rounded-2xl p-5 flex items-center justify-between hover:bg-blue-50/50 hover:border-blue-300 hover:shadow-sm transition cursor-pointer">
            <div class="flex items-center gap-5">
              <div class="w-14 h-14 rounded-2xl bg-blue-100 flex items-center justify-center text-2xl shrink-0">📦</div>

              <div>
                <h3 class="text-xl font-semibold text-slate-800">
                  <?= htmlspecialchars($latestParcel["parcel_type"] ?? "พัสดุทั่วไป") ?>
                </h3>
                <p class="text-slate-500 mt-1">
                  รับเมื่อ <?= date("d M Y", strtotime($latestParcel["received_at"])) ?>
                </p>
              </div>
            </div>

            <div class="text-right shrink-0">
              <?php if ($latestParcel["status"] === "รอรับ"): ?>
                  <div class="bg-red-100 text-red-600 px-4 py-2 rounded-full text-sm font-semibold inline-block">รอรับ</div>
              <?php else: ?>
                  <div class="bg-green-100 text-green-700 px-4 py-2 rounded-full text-sm font-semibold inline-block">รับแล้ว</div>
              <?php endif; ?>
              <p class="text-blue-500 text-2xl font-bold mt-2"><?= $latestParcel["quantity"] ?></p>
              <p class="text-slate-400 text-sm">ชิ้น</p>
            </div>
          </a>
        <?php else: ?>
          <div class="text-center py-10 text-slate-400 border border-dashed border-slate-200 rounded-2xl">ยังไม่มีพัสดุใหม่</div>
        <?php endif; ?>
      </div>
    </div>

  <?php endif; ?>

</div>

<?php user_footer(); ?>