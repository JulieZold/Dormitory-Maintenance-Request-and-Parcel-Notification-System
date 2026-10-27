<?php
function user_header($active = "") {
    global $pdo; 
    
    $user_id = $_SESSION["user_id"] ?? 0;
    
    // อัปเดตสถานะการอ่านเมื่อมีการกดลิงก์แจ้งเตือน
    if (isset($_GET['read_notif']) && $user_id && $pdo) {
        $read_id = $_GET['read_notif'];
        try {
            $updateStmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
            $updateStmt->execute([$read_id, $user_id]);
        } catch (Exception $e) {
            $updateStmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $updateStmt->execute([$read_id, $user_id]);
        }
    }

    $unreadCount = 0;
    $notifications = [];
    
    if ($user_id && $pdo) {
        $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $unreadStmt->execute([$user_id]);
        $unreadCount = $unreadStmt->fetchColumn();
        
        $notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $notifStmt->execute([$user_id]);
        $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $profileChar = mb_substr($_SESSION["full_name"] ?? "U", 0, 1, 'UTF-8');
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>ผู้เช่า - ระบบหอพัก</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root{--ui-zoom:.9}
    body{zoom:var(--ui-zoom)}
    .app-shell{min-height:calc(100vh / var(--ui-zoom) - 64px)}
    *{font-family:'Sarabun',sans-serif}
    body{background:#f7f8fb;color:#0f172a}

    .sidebar-link{
      display:block;padding:12px 16px;border-radius:14px;
      transition:.2s;font-weight:500;
    }
    .sidebar-link:hover{background:#dbeafe;color:#1d4ed8;transform:translateX(4px)}
    .sidebar-active{background:#93c5fd;color:#0f172a;font-weight:700;box-shadow:0 4px 10px rgba(59,130,246,.25)}

    button,.btn-ui,a[class*="bg-"]{
      cursor:pointer;transition:.2s;
    }
    button:hover,.btn-ui:hover{transform:translateY(-1px);filter:brightness(.97)}
  </style>
</head>

<body>
<header class="h-[64px] bg-white border-b border-gray-300 flex items-center justify-between px-10 shadow-sm relative z-40">
  <h1 class="text-[26px] font-bold text-slate-800">ระบบแจ้งซ่อมและแจ้งรับพัสดุในหอพัก</h1>

  <div class="flex items-center gap-6">
    
    <!-- Notification Module -->
    <div class="relative inline-block text-left">
        <button onclick="toggleNotifications()" type="button" class="relative p-2 bg-slate-50 text-slate-500 hover:bg-slate-100 rounded-full transition shadow-sm border border-slate-200 flex items-center justify-center">
            <svg class="w-[22px] h-[22px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            <?php if ($unreadCount > 0): ?>
                <span class="absolute top-0 right-0 flex h-3 w-3">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500 border-2 border-white"></span>
                </span>
            <?php endif; ?>
        </button>

        <div id="notificationDropdown" class="hidden absolute right-0 mt-4 w-[350px] bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden transform opacity-100 scale-100 transition-all origin-top-right">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                <h3 class="font-bold text-slate-800 text-[16px]">การแจ้งเตือน</h3>
                <?php if ($unreadCount > 0): ?>
                    <span class="text-[11px] bg-red-100 text-red-600 px-3 py-1 rounded-full font-bold"><?= $unreadCount ?> รายการใหม่</span>
                <?php endif; ?>
            </div>

            <div class="max-h-[360px] overflow-y-auto">
                <?php if (count($notifications) > 0): ?>
                    <?php foreach ($notifications as $n): ?>
                        <?php 
                            $isUnread = (isset($n['is_read']) && $n['is_read'] == 0);
                            $bgClass = $isUnread ? 'bg-blue-50/40 hover:bg-blue-50' : 'bg-white hover:bg-slate-50';
                            
                            $msg = $n['message'] ?? '';
                            // ตรวจสอบประเภทจากข้อความ หรือคอลัมน์ type
                            $isParcel = (isset($n['type']) && $n['type'] === 'parcel') || (mb_strpos($msg, 'พัสดุ') !== false);
                            
                            $icon = $isParcel ? '📦' : '🛠️';
                            $target_page = $isParcel ? 'parcels.php' : 'repair_history.php';
                            
                            $notifId = $n['notification_id'] ?? ($n['id'] ?? 0);
                            $link = $target_page . "?read_notif=" . $notifId;
                            
                            $notifTitle = $n['title'] ?? ($isParcel ? 'แจ้งเตือนพัสดุเข้าใหม่' : 'แจ้งเตือนสถานะการแจ้งซ่อม');
                        ?>
                        <a href="<?= $link ?>" class="block px-5 py-4 border-b border-slate-100 transition <?= $bgClass ?>">
                            <div class="flex gap-4 items-start">
                                <div class="text-[20px] mt-0.5 shrink-0"><?= $icon ?></div>
                                <div class="flex-1">
                                    <p class="text-[14px] font-bold <?= $isUnread ? 'text-slate-800' : 'text-slate-600' ?>">
                                        <?= htmlspecialchars($notifTitle) ?>
                                    </p>
                                    <p class="text-[13px] text-slate-500 mt-1 line-clamp-2 leading-relaxed">
                                        <?= htmlspecialchars($msg) ?>
                                    </p>
                                    <p class="text-[11px] text-slate-400 mt-2 font-medium">
                                        <?= !empty($n['created_at']) ? date('d M Y, H:i', strtotime($n['created_at'])) : '' ?>
                                    </p>
                                </div>
                                <?php if ($isUnread): ?>
                                    <div class="shrink-0 mt-2"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 block"></span></div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="px-5 py-8 text-center flex flex-col items-center justify-center">
                        <span class="text-3xl mb-2 opacity-30">📭</span>
                        <p class="text-slate-500 font-medium text-[14px]">ยังไม่มีการแจ้งเตือนใหม่</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <a href="repair_history.php" class="block px-5 py-3 border-t border-slate-100 text-center bg-slate-50 hover:bg-slate-100 transition text-[13px] text-blue-600 font-bold">
                ดูการแจ้งเตือนทั้งหมด
            </a>
        </div>
    </div>

    <!-- Profile Info -->
    <div class="flex items-center gap-3">
      <span class="bg-blue-100 text-blue-900 rounded-full px-5 py-2 text-sm font-semibold shadow-sm">
        ห้อง <?= htmlspecialchars($_SESSION["room_no"] ?? "-") ?> - <?= htmlspecialchars($_SESSION["full_name"] ?? "ผู้เช่า") ?>
      </span>
      <div class="w-11 h-11 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-lg shadow-md">
        <?= htmlspecialchars($profileChar) ?>
      </div>
    </div>
  </div>
</header>

<div class="flex app-shell">
  <aside class="w-[230px] bg-white border-r border-gray-300 p-5 shadow-sm">
    <nav class="space-y-3 text-[19px]">
      <a class="sidebar-link <?= $active==='dashboard'?'sidebar-active':'' ?>" href="dashboard.php">หน้าหลัก</a>
      <a class="sidebar-link <?= $active==='repair'?'sidebar-active':'' ?>" href="repair_create.php">แจ้งซ่อมใหม่</a>
      <a class="sidebar-link <?= $active==='history'?'sidebar-active':'' ?>" href="repair_history.php">ประวัติแจ้งซ่อม</a>
      <a class="sidebar-link <?= $active==='parcels'?'sidebar-active':'' ?>" href="my_parcels.php">พัสดุของฉัน</a>
      <a class="sidebar-link <?= $active==='profile'?'sidebar-active':'' ?>" href="profile.php">ข้อมูลส่วนตัว</a>
      <a class="sidebar-link" href="../logout.php">ออกจากระบบ</a>
    </nav>
  </aside>

  <main class="flex-1 px-8 py-8 relative z-0">
<?php } ?>

<?php function user_footer() { ?>
  </main>
</div>

<script>
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('hidden');
}

window.addEventListener('click', function(e) {
    const dropdown = document.getElementById('notificationDropdown');
    const bellBtn = e.target.closest('button[onclick="toggleNotifications()"]');
    
    if (dropdown && !dropdown.contains(e.target) && !bellBtn) {
        if (!dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
        }
    }
});
</script>
</body>
</html>
<?php } ?>