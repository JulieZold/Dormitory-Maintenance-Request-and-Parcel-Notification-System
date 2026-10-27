<?php
require_once "../includes/auth.php";
require_user();

require_once "../config/db.php";
require_once "layout.php";

$user_id = $_SESSION["user_id"];
$room_id = $_SESSION["room_id"];

/* USER + ROOM */
$stmt = $pdo->prepare("
  SELECT u.*, r.room_no, r.floor
  FROM users u
  LEFT JOIN rooms r ON u.room_id = r.room_id
  WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$_SESSION["room_no"] = $user["room_no"] ?? "101";

/* SAVE REPAIR */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $category_id = $_POST["category_id"];
  $description = trim($_POST["description"]);
  $appointment_date = $_POST["appointment_date"] ?? null;
  $appointment_time = $_POST["appointment_time"] ?? null;

  // จัดการถ้าเลือกหมวดหมู่ "อื่น ๆ" (value = 8) ให้เอาข้อความที่ระบุเองไปแปะไว้ใน description
  if ($category_id == "8" && !empty($_POST["category_other"])) {
      $custom_category = trim($_POST["category_other"]);
      $description = "[หมวดหมู่ระบุเอง: " . $custom_category . "]\n" . $description;
  }

  $imageName = null;

  if (!empty($_FILES["image"]["name"])) {
    $targetDir = "../uploads/repairs/";

    if (!is_dir($targetDir)) {
      mkdir($targetDir, 0777, true);
    }

    $imageName = time() . "_" . basename($_FILES["image"]["name"]);
    move_uploaded_file($_FILES["image"]["tmp_name"], $targetDir . $imageName);
  }

  // บันทึกรายการแจ้งซ่อม
  $insert = $pdo->prepare("
    INSERT INTO repair_requests (
      user_id,
      room_id,
      category_id,
      description,
      appointment_date,
      status,
      created_at
    )
    VALUES (?, ?, ?, ?, ?, 'รออนุมัติ', NOW())
  ");

  $insert->execute([
    $user_id,
    $room_id,
    $category_id,
    $description,
    $appointment_date
  ]);

  // ==========================================
  // ส่งการแจ้งเตือนให้ Admin (ใส่เฉพาะ user_id และ message)
  // ==========================================
  $room_no = $_SESSION["room_no"] ?? "ไม่ระบุ";

  $adminStmt = $pdo->prepare("SELECT user_id FROM users WHERE role = 'admin'");
  $adminStmt->execute();
  $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

  $repair_desc = mb_substr($description, 0, 35, 'UTF-8');
  if (mb_strlen($description, 'UTF-8') > 35) {
      $repair_desc .= '...';
  }

  $message = "🛠️ มีคำขอแจ้งซ่อมใหม่จากห้อง {$room_no}: {$repair_desc}";

  if (count($admins) > 0) {
      $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
      foreach ($admins as $admin) {
          $notifStmt->execute([$admin['user_id'], $message]);
      }
  }
  // ==========================================

  header("Location: repair_history.php");
  exit;
}

user_header("repair");
?>

<div class="max-w-[1100px] mx-auto space-y-4">

  <div>
    <h1 class="text-[24px] font-bold text-slate-800">แจ้งซ่อมใหม่</h1>
    <p class="text-sm text-slate-500 mt-1">
      ห้อง <?= htmlspecialchars($user["room_no"] ?? "101") ?>
      (ชั้น <?= htmlspecialchars($user["floor"] ?? "-") ?>)
      จะถูกบันทึกอัตโนมัติ
    </p>
  </div>

  <form method="POST" enctype="multipart/form-data" class="space-y-4">

    <!-- หมวดหมู่ปัญหา -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
      <h2 class="text-lg font-bold text-slate-800 mb-3">หมวดหมู่ปัญหา</h2>

      <div class="grid grid-cols-4 gap-3">
        <label class="category-card">
          <input type="radio" name="category_id" value="1" data-category-name="ระบบไฟฟ้า" class="hidden peer category-radio" required checked>
          <div class="category-box peer-checked:bg-blue-100 peer-checked:border-blue-400 peer-checked:text-blue-700">ระบบไฟฟ้า</div>
        </label>

        <label class="category-card">
          <input type="radio" name="category_id" value="2" data-category-name="ระบบประปา" class="hidden peer category-radio">
          <div class="category-box peer-checked:bg-blue-100 peer-checked:border-blue-400 peer-checked:text-blue-700">ระบบประปา</div>
        </label>

        <label class="category-card">
          <input type="radio" name="category_id" value="3" data-category-name="เครื่องปรับอากาศ" class="hidden peer category-radio">
          <div class="category-box peer-checked:bg-blue-100 peer-checked:border-blue-400 peer-checked:text-blue-700">เครื่องปรับอากาศ</div>
        </label>

        <label class="category-card">
          <input type="radio" name="category_id" value="4" data-category-name="ประตู / หน้าต่าง" class="hidden peer category-radio">
          <div class="category-box peer-checked:bg-blue-100 peer-checked:border-blue-400 peer-checked:text-blue-700">ประตู / หน้าต่าง</div>
        </label>

        <label class="category-card">
          <input type="radio" name="category_id" value="5" data-category-name="อินเทอร์เน็ต" class="hidden peer category-radio">
          <div class="category-box peer-checked:bg-blue-100 peer-checked:border-blue-400 peer-checked:text-blue-700">อินเทอร์เน็ต</div>
        </label>

        <label class="category-card">
          <input type="radio" name="category_id" value="6" data-category-name="กุญแจ / ล็อค" class="hidden peer category-radio">
          <div class="category-box peer-checked:bg-blue-100 peer-checked:border-blue-400 peer-checked:text-blue-700">กุญแจ</div>
        </label>

        <label class="category-card">
          <input type="radio" name="category_id" value="7" data-category-name="ความสะอาด" class="hidden peer category-radio">
          <div class="category-box peer-checked:bg-blue-100 peer-checked:border-blue-400 peer-checked:text-blue-700">ความสะอาด</div>
        </label>

        <label class="category-card">
          <input type="radio" name="category_id" value="8" data-category-name="อื่น ๆ" class="hidden peer category-radio">
          <div class="category-box peer-checked:bg-blue-100 peer-checked:border-blue-400 peer-checked:text-blue-700">อื่น ๆ</div>
        </label>
      </div>

      <div id="otherCategoryDiv" class="hidden mt-4 bg-orange-50 border border-orange-200 rounded-xl p-4">
        <label class="block text-sm font-semibold text-orange-800 mb-1.5">โปรดระบุหมวดหมู่ปัญหา <span class="text-red-500">*</span></label>
        <input type="text" name="category_other" id="category_other_input" placeholder="เช่น เครื่องทำน้ำอุ่น, เฟอร์นิเจอร์ชำรุด, เตียงหัก..." class="w-full h-[42px] rounded-lg border border-orange-300 px-4 text-sm focus:ring-2 focus:ring-orange-300 outline-none">
      </div>
    </div>

    <!-- รายละเอียดการแจ้งซ่อม -->
    <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
      <h2 class="text-lg font-bold text-slate-800 mb-3">รายละเอียดการแจ้งซ่อม</h2>

      <div class="mb-4">
        <label class="block text-sm font-semibold text-slate-700 mb-1.5">รายละเอียดของปัญหา *</label>
        <textarea
          name="description"
          required
          rows="3"
          placeholder="อธิบายปัญหาที่พบโดยละเอียด เช่น ไฟในห้องน้ำไม่ติด สวิตช์เสีย..."
          class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-4 focus:ring-blue-100 resize-none"
        ></textarea>
      </div>

      <!-- 4 ช่องในแถวเดียว -->
      <div class="grid grid-cols-4 gap-4 mb-4">
        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">ห้อง (อัตโนมัติ)</label>
          <input
            type="text"
            readonly
            value="ห้อง <?= htmlspecialchars($user["room_no"] ?? "101") ?> (ชั้น <?= htmlspecialchars($user["floor"] ?? "-") ?>)"
            class="w-full h-[42px] rounded-xl border border-slate-300 px-4 text-sm bg-slate-100 text-slate-600"
          >
        </div>

        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">หมวดหมู่ (อัตโนมัติ)</label>
          <input
            id="selectedCategoryText"
            type="text"
            readonly
            value="ระบบไฟฟ้า"
            class="w-full h-[42px] rounded-xl border border-slate-300 px-4 text-sm bg-slate-100 text-slate-600"
          >
        </div>

        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">วันที่สะดวกให้เข้าซ่อม</label>
          <input
            type="date"
            name="appointment_date"
            class="w-full h-[42px] rounded-xl border border-slate-300 px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100"
          >
        </div>

        <div>
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">ช่วงเวลา</label>
          <input
            type="time"
            name="appointment_time"
            class="w-full h-[42px] rounded-xl border border-slate-300 px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100"
          >
        </div>
      </div>

      <!-- แนบรูป + ปุ่ม อยู่แถวเดียวกัน -->
      <div class="flex items-end justify-between gap-6">
        <div class="flex-1">
          <label class="block text-sm font-semibold text-slate-700 mb-1.5">แนบรูปภาพ (ถ้ามี)</label>
          <input
            type="file"
            name="image"
            accept="image/*"
            class="w-full h-[42px] rounded-xl border border-slate-300 px-3 py-1.5 text-sm bg-white"
          >
        </div>

        <div class="flex gap-3">
          <a href="dashboard.php" class="h-[42px] inline-flex items-center px-6 rounded-xl border border-slate-300 bg-white hover:bg-slate-100 text-slate-600 text-sm transition font-semibold">
            ยกเลิก
          </a>

          <button type="submit" class="h-[42px] bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold px-6 rounded-xl shadow-md hover:shadow-lg transition">
            ส่งคำขอ
          </button>
        </div>
      </div>
    </div>

  </form>
</div>

<style>
  .category-box {
    border: 1px solid #cbd5e1;
    background: white;
    border-radius: 12px;
    padding: 10px 12px;
    text-align: center;
    font-size: 14px;
    font-weight: 600;
    transition: .2s;
    cursor: pointer;
  }

  .category-box:hover {
    background: #eff6ff;
    border-color: #60a5fa;
  }
</style>

<script>
document.querySelectorAll(".category-radio").forEach((radio) => {
  radio.addEventListener("change", function () {
    document.getElementById("selectedCategoryText").value = this.dataset.categoryName;

    const otherDiv = document.getElementById("otherCategoryDiv");
    const otherInput = document.getElementById("category_other_input");

    if (this.value === "8") {
        otherDiv.classList.remove("hidden");
        otherInput.required = true;
        otherInput.focus();
    } else {
        otherDiv.classList.add("hidden");
        otherInput.required = false;
        otherInput.value = "";
    }
  });
});
</script>

<?php user_footer(); ?>