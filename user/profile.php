<?php
require_once "../includes/auth.php";
require_user();

require_once "../config/db.php";
require_once "layout.php";

$user_id = $_SESSION["user_id"];

/* =========================
   UPDATE PROFILE
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $full_name = trim($_POST["full_name"]);
  $email = trim($_POST["email"]);
  $phone = trim($_POST["phone"]);

  $stmt = $pdo->prepare("
    UPDATE users
    SET
      full_name = ?,
      email = ?,
      phone = ?
    WHERE user_id = ?
  ");

  $stmt->execute([
    $full_name,
    $email,
    $phone,
    $user_id
  ]);

  $_SESSION["full_name"] = $full_name;

  header("Location: profile.php?success=1");
  exit;
}

/* =========================
   USER INFO
========================= */
$stmt = $pdo->prepare("
  SELECT 
    u.*,
    r.room_no,
    r.floor
  FROM users u
  LEFT JOIN rooms r ON u.room_id = r.room_id
  WHERE u.user_id = ?
");

$stmt->execute([$user_id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

$_SESSION["room_no"] = $user["room_no"] ?? "101";

$avatarLetter = mb_strtoupper(
  mb_substr($user["full_name"] ?? "U", 0, 1, "UTF-8")
);

user_header("profile");
?>

<div class="max-w-[1000px] mx-auto space-y-6">

  <!-- HEADER -->
  <div>
    <h1 class="text-[28px] font-bold text-slate-800">
      ข้อมูลส่วนตัว
    </h1>

    <p class="text-slate-500 mt-1">
      จัดการข้อมูลบัญชีผู้ใช้งานของคุณ
    </p>
  </div>

  <?php if (isset($_GET["success"])): ?>
    <div class="bg-green-100 border border-green-200 text-green-700 rounded-2xl px-5 py-4 font-medium">
      บันทึกข้อมูลเรียบร้อยแล้ว
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-[320px_1fr] gap-6">

    <!-- LEFT -->
    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-7 text-center">

      <div class="w-[120px] h-[120px] mx-auto rounded-full bg-blue-500 text-white flex items-center justify-center text-5xl font-bold shadow-lg">
        <?= htmlspecialchars($avatarLetter) ?>
      </div>

      <h2 class="text-2xl font-bold text-slate-800 mt-6">
        <?= htmlspecialchars($user["full_name"]) ?>
      </h2>

      <p class="text-slate-500 mt-2">
        ห้อง <?= htmlspecialchars($user["room_no"] ?? "-") ?>
      </p>

      <div class="mt-6 inline-block bg-blue-100 text-blue-700 px-5 py-2 rounded-full font-semibold">
        ผู้เช่า
      </div>

      <div class="mt-8 border-t border-slate-200 pt-6 text-left space-y-5">

        <div>
          <p class="text-slate-400 text-sm">
            Username
          </p>

          <p class="font-semibold text-slate-800 mt-1">
            <?= htmlspecialchars($user["username"]) ?>
          </p>
        </div>

        <div>
          <p class="text-slate-400 text-sm">
            ชั้น
          </p>

          <p class="font-semibold text-slate-800 mt-1">
            ชั้น <?= htmlspecialchars($user["floor"] ?? "-") ?>
          </p>
        </div>

        <div>
          <p class="text-slate-400 text-sm">
            สถานะ
          </p>

          <div class="mt-2 inline-block bg-green-100 text-green-700 px-4 py-2 rounded-full text-sm font-semibold">
            ใช้งานอยู่
          </div>
        </div>

      </div>

    </div>

    <!-- RIGHT -->
    <div class="bg-white border border-slate-200 rounded-3xl shadow-sm p-8">

      <div class="flex justify-between items-center mb-8">

        <div>
          <h2 class="text-2xl font-bold text-slate-800">
            แก้ไขข้อมูลส่วนตัว
          </h2>

          <p class="text-slate-500 mt-1">
            อัปเดตข้อมูลสำหรับติดต่อ
          </p>
        </div>

      </div>

      <form method="POST" class="space-y-6">

        <!-- NAME -->
        <div>
          <label class="block text-[15px] font-semibold text-slate-700 mb-3">
            ชื่อ - นามสกุล
          </label>

          <input
            type="text"
            name="full_name"
            required
            value="<?= htmlspecialchars($user["full_name"]) ?>"
            class="w-full h-[56px] rounded-2xl border border-slate-200 bg-slate-50 px-5 text-[16px] outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-400"
          >
        </div>

        <!-- EMAIL -->
        <div>
          <label class="block text-[15px] font-semibold text-slate-700 mb-3">
            อีเมล
          </label>

          <input
            type="email"
            name="email"
            value="<?= htmlspecialchars($user["email"] ?? "") ?>"
            class="w-full h-[56px] rounded-2xl border border-slate-200 bg-slate-50 px-5 text-[16px] outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-400"
          >
        </div>

        <!-- PHONE -->
        <div>
          <label class="block text-[15px] font-semibold text-slate-700 mb-3">
            เบอร์โทรศัพท์
          </label>

          <input
            type="text"
            name="phone"
            value="<?= htmlspecialchars($user["phone"] ?? "") ?>"
            class="w-full h-[56px] rounded-2xl border border-slate-200 bg-slate-50 px-5 text-[16px] outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-400"
          >
        </div>

        <!-- ROOM -->
        <div>
          <label class="block text-[15px] font-semibold text-slate-700 mb-3">
            ห้องพัก
          </label>

          <input
            type="text"
            readonly
            value="ห้อง <?= htmlspecialchars($user["room_no"] ?? "-") ?> (ชั้น <?= htmlspecialchars($user["floor"] ?? "-") ?>)"
            class="w-full h-[56px] rounded-2xl border border-slate-200 bg-slate-100 px-5 text-[16px]"
          >
        </div>

        <!-- BUTTON -->
        <div class="pt-3 flex justify-end gap-4">

          <a
            href="dashboard.php"
            class="px-7 py-4 rounded-2xl border border-slate-300 bg-white hover:bg-slate-100 transition font-semibold"
          >
            ย้อนกลับ
          </a>

          <button
            type="submit"
            class="bg-blue-500 hover:bg-blue-600 text-white font-semibold px-8 py-4 rounded-2xl transition shadow-md hover:shadow-lg"
          >
            บันทึกข้อมูล
          </button>

        </div>

      </form>

    </div>

  </div>

</div>

<?php user_footer(); ?>