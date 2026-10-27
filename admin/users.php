<?php
require_once "../includes/auth.php";
require_admin();

require_once "../config/db.php";
require_once "layout.php";

/* =========================
   Process Forms (Add & Edit)
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "add";

  if ($action === "add") {
    $hash = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
      INSERT INTO users (room_id, full_name, email, phone, username, password, role, status, must_change_password)
      VALUES (?, ?, ?, ?, ?, ?, 'user', 'ใช้งานอยู่', 1)
    ");
    $stmt->execute([
      $_POST["room_id"],
      $_POST["full_name"],
      $_POST["email"],
      $_POST["phone"],
      $_POST["username"],
      $hash
    ]);
    header("Location: users.php");
    exit;

  } elseif ($action === "edit") {
    $user_id = $_POST["user_id"];
    
    if (!empty($_POST["password"])) {
      $hash = password_hash($_POST["password"], PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("
        UPDATE users 
        SET room_id = ?, full_name = ?, email = ?, phone = ?, username = ?, password = ?, status = ?
        WHERE user_id = ? AND role = 'user'
      ");
      $stmt->execute([
        $_POST["room_id"], $_POST["full_name"], $_POST["email"], 
        $_POST["phone"], $_POST["username"], $hash, $_POST["status"], $user_id
      ]);
    } else {
      $stmt = $pdo->prepare("
        UPDATE users 
        SET room_id = ?, full_name = ?, email = ?, phone = ?, username = ?, status = ?
        WHERE user_id = ? AND role = 'user'
      ");
      $stmt->execute([
        $_POST["room_id"], $_POST["full_name"], $_POST["email"], 
        $_POST["phone"], $_POST["username"], $_POST["status"], $user_id
      ]);
    }
    header("Location: users.php");
    exit;
  }
}

/* =========================
   Delete / move out user
========================= */
if (isset($_GET["delete"])) {
  $stmt = $pdo->prepare("
    UPDATE users 
    SET status = 'ย้ายออก' 
    WHERE user_id = ? AND role = 'user'
  ");
  $stmt->execute([$_GET["delete"]]);
  header("Location: users.php");
  exit;
}

/* =========================
   Check Edit Request
========================= */
$edit_user = null;
if (isset($_GET["edit"])) {
  $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ? AND role = 'user'");
  $stmt->execute([$_GET["edit"]]);
  $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =========================
   Data for Dropdowns
========================= */
$rooms = $pdo->query("
  SELECT * 
  FROM rooms 
  ORDER BY room_no ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   Users (With Search & Filter)
========================= */
$search_text = $_GET['search'] ?? '';
$filter_floor = $_GET['floor'] ?? '';

$query = "
  SELECT u.*, r.room_no, r.floor
  FROM users u
  LEFT JOIN rooms r ON u.room_id = r.room_id
  WHERE u.role = 'user'
";
$params = [];

if (!empty($filter_floor)) {
  $query .= " AND r.floor = ?";
  $params[] = $filter_floor;
}

if (!empty($search_text)) {
  $query .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR r.room_no LIKE ?)";
  $params[] = "%$search_text%";
  $params[] = "%$search_text%";
  $params[] = "%$search_text%";
}

$query .= " ORDER BY r.room_no ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

admin_header("users");
?>

<div class="max-w-[1200px] mx-auto space-y-6">

  <div class="flex justify-between items-center">
    <div>
      <h1 class="text-[28px] font-bold text-slate-800">จัดการผู้เช่า</h1>
      <p class="text-slate-500 mt-1">เพิ่ม แก้ไข และตรวจสอบข้อมูลผู้เช่าในหอพัก</p>
    </div>

    <?php if (!$edit_user): ?>
      <div class="flex gap-3">
        <button type="button" onclick="document.getElementById('addUserBox').classList.toggle('hidden')" class="btn-ui bg-blue-500 hover:bg-blue-600 text-white rounded-xl px-5 py-3 font-semibold">
          + เพิ่มผู้เช่าใหม่
        </button>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($edit_user): ?>
    <form method="POST" class="bg-orange-50 rounded-2xl border border-orange-200 shadow-sm p-6 mb-6">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="user_id" value="<?= $edit_user['user_id'] ?>">
      
      <div class="flex justify-between items-center mb-5">
        <div>
          <h2 class="text-xl font-bold text-orange-800">แก้ไขข้อมูลผู้เช่า</h2>
          <p class="text-orange-600 text-sm mt-1">กำลังแก้ไขข้อมูลของ: <?= htmlspecialchars($edit_user['full_name']) ?></p>
        </div>
        <a href="users.php" class="text-slate-500 hover:text-slate-700 font-medium px-4 py-2 bg-white rounded-lg border border-slate-300 shadow-sm">
          ✕ ยกเลิกการแก้ไข
        </a>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block mb-2 text-slate-700 font-medium">ชื่อ - นามสกุล</label>
          <input name="full_name" required value="<?= htmlspecialchars($edit_user['full_name']) ?>" class="w-full h-[52px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-orange-300">
        </div>

        <div>
          <label class="block mb-2 text-slate-700 font-medium">อีเมล</label>
          <input name="email" type="email" value="<?= htmlspecialchars($edit_user['email']) ?>" class="w-full h-[52px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-orange-300">
        </div>

        <div>
          <label class="block mb-2 text-slate-700 font-medium">เบอร์โทร</label>
          <input name="phone" value="<?= htmlspecialchars($edit_user['phone']) ?>" class="w-full h-[52px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-orange-300">
        </div>

        <div>
          <label class="block mb-2 text-slate-700 font-medium">Username</label>
          <input name="username" required value="<?= htmlspecialchars($edit_user['username']) ?>" class="w-full h-[52px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-orange-300">
        </div>

        <div>
          <label class="block mb-2 text-slate-700 font-medium">รหัสผ่านใหม่ (ไม่บังคับ)</label>
          <input name="password" placeholder="เว้นว่างไว้หากไม่เปลี่ยนรหัส" class="w-full h-[52px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-orange-300">
        </div>

        <div>
          <label class="block mb-2 text-slate-700 font-medium">สถานะผู้เช่า</label>
          <select name="status" class="w-full h-[52px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-orange-300">
            <option value="ใช้งานอยู่" <?= $edit_user['status'] === 'ใช้งานอยู่' ? 'selected' : '' ?>>ใช้งานอยู่</option>
            <option value="ย้ายออก" <?= $edit_user['status'] === 'ย้ายออก' ? 'selected' : '' ?>>ย้ายออก</option>
          </select>
        </div>

        <div class="col-span-3 lg:col-span-1">
          <label class="block mb-2 text-slate-700 font-medium">เลือกห้อง</label>
          <select name="room_id" required class="w-full h-[52px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-orange-300">
            <option value="">เลือกห้อง</option>
            <?php foreach ($rooms as $room): ?>
              <option value="<?= $room["room_id"] ?>" <?= $room["room_id"] == $edit_user['room_id'] ? 'selected' : '' ?>>
                ห้อง <?= htmlspecialchars($room["room_no"]) ?> (ชั้น <?= htmlspecialchars($room["floor"]) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="mt-5">
        <button type="submit" class="w-full h-[54px] rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-bold transition shadow-sm">
          บันทึกการแก้ไข
        </button>
      </div>
    </form>
  <?php endif; ?>

  <?php if (!$edit_user): ?>
    <form id="addUserBox" method="POST" class="hidden bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
      <input type="hidden" name="action" value="add">
      <div class="flex justify-between items-center mb-5">
        <div>
          <h2 class="text-xl font-semibold text-slate-800">เพิ่มผู้เช่าใหม่</h2>
          <p class="text-slate-500 text-sm mt-1">กำหนด username และ password เริ่มต้นให้กับผู้เช่า</p>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div><label class="block mb-2 text-slate-700 font-medium">ชื่อ - นามสกุล</label><input name="full_name" required placeholder="เช่น ชัญญา สุข" class="w-full h-[52px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300"></div>
        <div><label class="block mb-2 text-slate-700 font-medium">อีเมล</label><input name="email" type="email" placeholder="example@gmail.com" class="w-full h-[52px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300"></div>
        <div><label class="block mb-2 text-slate-700 font-medium">เบอร์โทร</label><input name="phone" placeholder="081-234-5678" class="w-full h-[52px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300"></div>
        <div><label class="block mb-2 text-slate-700 font-medium">Username</label><input name="username" required placeholder="เช่น room104" class="w-full h-[52px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300"></div>
        <div><label class="block mb-2 text-slate-700 font-medium">Password เริ่มต้น</label><input name="password" required placeholder="รหัสผ่านเริ่มต้น" class="w-full h-[52px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300"></div>
        <div>
          <label class="block mb-2 text-slate-700 font-medium">เลือกห้อง</label>
          <select name="room_id" required class="w-full h-[52px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300">
            <option value="">เลือกห้อง</option>
            <?php foreach ($rooms as $room): ?>
              <option value="<?= $room["room_id"] ?>">
                ห้อง <?= htmlspecialchars($room["room_no"]) ?> (ชั้น <?= htmlspecialchars($room["floor"]) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="mt-5">
        <button type="submit" class="w-full h-[54px] rounded-xl bg-blue-500 hover:bg-blue-600 text-white font-semibold transition">
          บันทึกผู้เช่า
        </button>
      </div>
    </form>
  <?php endif; ?>

  <form method="GET" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 flex gap-4 items-end">
    <?php if (isset($_GET['edit'])): ?>
      <input type="hidden" name="edit" value="<?= $_GET['edit'] ?>">
    <?php endif; ?>

    <div class="flex-1">
      <label class="block mb-1.5 text-sm text-slate-600 font-medium">ค้นหา ชื่อ, Username หรือ เลขห้อง</label>
      <input type="text" name="search" value="<?= htmlspecialchars($search_text) ?>" placeholder="ระบุคำค้นหา..." class="w-full h-[46px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300">
    </div>

    <div class="w-48">
      <label class="block mb-1.5 text-sm text-slate-600 font-medium">กรองตามชั้น</label>
      <select name="floor" class="w-full h-[46px] rounded-xl border border-slate-300 px-4 focus:ring-2 focus:ring-blue-300">
        <option value="">ทุกชั้น</option>
        <?php for ($f = 1; $f <= 4; $f++): ?>
          <option value="<?= $f ?>" <?= $filter_floor == $f ? 'selected' : '' ?>>
            ชั้น <?= $f ?>
          </option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="flex gap-2">
      <button type="submit" class="h-[46px] bg-slate-800 hover:bg-slate-900 text-white font-semibold px-6 rounded-xl transition shadow-sm">
        ค้นหา
      </button>

      <?php if (!empty($search_text) || !empty($filter_floor)): ?>
        <a href="users.php" class="h-[46px] bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold px-5 rounded-xl transition flex items-center justify-center border border-slate-200">
          ล้างค่า
        </a>
      <?php endif; ?>
    </div>
  </form>

  <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-200 flex justify-between items-center">
      <div>
        <h2 class="text-xl font-semibold text-slate-800">รายชื่อผู้เช่า</h2>
        <?php if (!empty($search_text) || !empty($filter_floor)): ?>
          <p class="text-blue-600 text-sm mt-1">แสดงผลลัพธ์การค้นหา</p>
        <?php else: ?>
          <p class="text-slate-500 text-sm mt-1">ข้อมูลผู้เช่าที่ถูกเพิ่มในระบบ</p>
        <?php endif; ?>
      </div>
      <span class="bg-blue-100 text-blue-700 rounded-full px-4 py-2 text-sm font-semibold">พบ <?= count($users) ?> รายการ</span>
    </div>

    <table class="w-full text-center">
      <thead class="bg-blue-50">
        <tr class="border-b border-slate-200">
          <th class="p-4 w-24">ชั้น</th>
          <th class="w-24">ห้อง</th>
          <th>ชื่อ - นามสกุล</th>
          <th>อีเมล</th>
          <th>เบอร์โทร</th>
          <th>สถานะ</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($users) > 0): ?>
          <?php foreach ($users as $u): ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50 <?= ($edit_user && $edit_user['user_id'] == $u['user_id']) ? 'bg-orange-50/50' : '' ?>">
              <td class="p-4 text-slate-600 font-medium">
                ชั้น <?= htmlspecialchars($u["floor"] ?? "-") ?>
              </td>
              <td class="font-bold text-slate-800"><?= htmlspecialchars($u["room_no"] ?? "-") ?></td>
              <td>
                <div class="font-semibold text-slate-800"><?= htmlspecialchars($u["full_name"]) ?></div>
                <div class="text-xs text-slate-400 mt-0.5">@<?= htmlspecialchars($u["username"]) ?></div>
              </td>
              <td><?= htmlspecialchars($u["email"] ?? "-") ?></td>
              <td><?= htmlspecialchars($u["phone"] ?? "-") ?></td>
              <td>
                <?php if ($u["status"] === "ใช้งานอยู่"): ?>
                  <span class="bg-green-100 text-green-700 border border-green-200 rounded-full px-4 py-1.5 text-sm font-medium">ใช้งานอยู่</span>
                <?php else: ?>
                  <span class="bg-slate-100 text-slate-600 border border-slate-200 rounded-full px-4 py-1.5 text-sm font-medium">ย้ายออก</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="flex justify-center gap-2">
                  <?php 
                    $edit_link = "?edit=" . $u["user_id"];
                    if (!empty($search_text)) $edit_link .= "&search=" . urlencode($search_text);
                    if (!empty($filter_floor)) $edit_link .= "&floor=" . urlencode($filter_floor);
                  ?>
                  <a href="<?= $edit_link ?>" class="btn-ui bg-orange-100 text-orange-700 border border-orange-200 rounded-xl px-4 py-2 text-sm font-semibold">
                    แก้ไข
                  </a>
                  <a onclick="return confirm('ต้องการเปลี่ยนสถานะผู้พักนี้เป็นย้ายออกหรือไม่?')" href="?delete=<?= $u["user_id"] ?>" class="btn-ui bg-red-100 text-red-700 border border-red-200 rounded-xl px-4 py-2 text-sm font-semibold">
                    ลบ
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" class="p-10 text-slate-500 font-medium text-lg">ไม่พบข้อมูลที่ค้นหา 🔍</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php admin_footer(); ?>