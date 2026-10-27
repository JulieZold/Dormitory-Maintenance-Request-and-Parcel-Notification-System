<?php
// ดึงไฟล์ระบบทั้งหมดของคุณมาใช้งาน
require_once "../includes/auth.php";
require_admin(); // ป้องกันไม่ให้ user ทั่วไปแอบเข้าหน้านี้
require_once "../config/db.php";
require_once "layout.php";

// เรียกใช้ Header จากไฟล์ layout.php
admin_header('users'); 

// ---------------------------------------------------------
// เริ่มการทำงานของระบบสุ่มผู้พักอาศัย (แบบ PDO)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_create'])) {
    
    $default_password = '123456';
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

    $logs = [];
    $success_count = 0;
    $exist_count = 0;
    $error_count = 0;

    // ข้อมูลจำลองสำหรับสุ่ม
    $mock_firstnames = ['ชัญญา', 'สมหญิง', 'นิตา', 'กมลชนก', 'พรพรรณ', 'จิราพร', 'รัตนา', 'วิภาดา', 'วรินทร', 'ปวีณา', 'สมชาย', 'ธนพล', 'กฤษฎา', 'ณัฐวุฒิ', 'พีรพล', 'ศุภชัย', 'เอกราช', 'จิรภัทร', 'วรุฒ', 'กิตติศักดิ์'];
    $mock_lastnames = ['สุข', 'มีสุข', 'งามดี', 'ใจดี', 'รักสงบ', 'พาเพลิน', 'เจริญพร', 'ศิริรัตน์', 'วงศ์สว่าง', 'พิทักษ์ไทย', 'สายทอง', 'ยอดเยี่ยม', 'รุ่งเรือง', 'สิริโสภา', 'มั่นคง', 'ทองทา', 'แสงสว่าง', 'บุญมา', 'วิเศษศรี', 'ไชยศรี'];

    try {
        // ดึงข้อมูลห้องทั้งหมดด้วย PDO
        $stmt_rooms = $pdo->query("SELECT room_id, room_no FROM rooms");
        $rooms = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);

        if (count($rooms) > 0) {
            
            // เตรียมคำสั่ง SQL ล่วงหน้า (Prepared Statement) เพื่อความรวดเร็วและปลอดภัย
            $stmt_check = $pdo->prepare("SELECT user_id, full_name FROM users WHERE username = :username");
            $stmt_insert = $pdo->prepare("INSERT INTO users (username, password, role, room_id, full_name, email, phone, must_change_password) 
                                          VALUES (:username, :password, 'user', :room_id, :full_name, :email, :phone, 1)");

            foreach ($rooms as $room) {
                $room_id = $room['room_id'];
                $room_no = $room['room_no']; 
                $username = 'room' . $room_no; // รูปแบบ username
                
                // สุ่มข้อมูลจำลอง
                $rand_fname = $mock_firstnames[array_rand($mock_firstnames)];
                $rand_lname = $mock_lastnames[array_rand($mock_lastnames)];
                $full_name = $rand_fname . ' ' . $rand_lname;
                
                $email = "user_" . strtolower($room_no) . "@example.com";
                $phone = "08" . rand(1, 9) . "-" . rand(100, 999) . "-" . rand(1000, 9999); 

                // ตรวจสอบจาก username ว่ามีบัญชีอยู่แล้วหรือยัง
                $stmt_check->execute([':username' => $username]);
                $existing_user = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if (!$existing_user) {
                    // เพิ่มข้อมูลลงฐานข้อมูล
                    $insert_success = $stmt_insert->execute([
                        ':username' => $username,
                        ':password' => $hashed_password,
                        ':room_id' => $room_id,
                        ':full_name' => $full_name,
                        ':email' => $email,
                        ':phone' => $phone
                    ]);
                    
                    if ($insert_success) {
                        $logs[] = ['room' => $username, 'name' => $full_name, 'status' => 'success', 'msg' => 'สร้างบัญชีสำเร็จ'];
                        $success_count++;
                    } else {
                        $logs[] = ['room' => $username, 'name' => '-', 'status' => 'error', 'msg' => 'Error ในการบันทึก'];
                        $error_count++;
                    }
                } else {
                    $logs[] = ['room' => $username, 'name' => $existing_user['full_name'], 'status' => 'exist', 'msg' => 'มีในระบบแล้ว'];
                    $exist_count++;
                }
            }
        }
    } catch (PDOException $e) {
        $logs[] = ['room' => '-', 'name' => '-', 'status' => 'error', 'msg' => 'Database Error: ' . $e->getMessage()];
        $error_count++;
    }
    ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="bg-slate-800 px-6 py-5 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h2 class="text-2xl font-bold text-white">ผลการสร้างบัญชีผู้ใช้งานเริ่มต้น</h2>
                <p class="text-slate-300 text-sm mt-1">รหัสผ่านเริ่มต้นคือ <b>123456</b></p>
            </div>
            <a href="users.php" class="bg-white text-slate-800 font-semibold px-5 py-2 rounded-lg shadow-sm hover:bg-slate-100">
                กลับหน้าผู้พักอาศัย
            </a>
        </div>

        <div class="grid grid-cols-3 gap-6 p-6 bg-slate-50 border-b border-gray-200">
            <div class="bg-white border-l-4 border-green-500 rounded-lg p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">สร้างสำเร็จ</p>
                    <p class="text-2xl font-bold text-slate-800"><?= $success_count ?> <span class="text-sm font-normal text-gray-500">บัญชี</span></p>
                </div>
                <div class="w-10 h-10 bg-green-100 text-green-600 rounded-full flex justify-center items-center font-bold text-xl">✓</div>
            </div>
            <div class="bg-white border-l-4 border-yellow-400 rounded-lg p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">ข้าม (มีบัญชีแล้ว)</p>
                    <p class="text-2xl font-bold text-slate-800"><?= $exist_count ?> <span class="text-sm font-normal text-gray-500">บัญชี</span></p>
                </div>
                <div class="w-10 h-10 bg-yellow-100 text-yellow-600 rounded-full flex justify-center items-center font-bold text-xl">!</div>
            </div>
            <div class="bg-white border-l-4 border-red-500 rounded-lg p-4 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">ผิดพลาด</p>
                    <p class="text-2xl font-bold text-slate-800"><?= $error_count ?> <span class="text-sm font-normal text-gray-500">รายการ</span></p>
                </div>
                <div class="w-10 h-10 bg-red-100 text-red-600 rounded-full flex justify-center items-center font-bold text-xl">✕</div>
            </div>
        </div>

        <div class="p-6">
            <div class="border border-gray-200 rounded-xl overflow-hidden max-h-[500px] overflow-y-auto">
                <table class="w-full text-left">
                    <thead class="sticky top-0 bg-[#eff6ff] shadow-sm">
                        <tr>
                            <th class="py-3 px-6 border-b border-gray-200 w-24">ลำดับ</th>
                            <th class="py-3 px-6 border-b border-gray-200 w-40">Username</th>
                            <th class="py-3 px-6 border-b border-gray-200 w-56">ชื่อ - นามสกุล</th>
                            <th class="py-3 px-6 border-b border-gray-200">สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" class="py-6 text-center text-gray-500">ไม่พบข้อมูลห้อง กรุณาเพิ่มข้อมูลห้องก่อน</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $index => $log): ?>
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-slate-50 transition-colors">
                                <td class="py-3 px-6 text-slate-600"><?= $index + 1 ?></td>
                                <td class="py-3 px-6 font-semibold text-slate-800"><?= htmlspecialchars($log['room']) ?></td>
                                <td class="py-3 px-6 text-slate-600"><?= htmlspecialchars($log['name']) ?></td>
                                <td class="py-3 px-6">
                                    <?php if ($log['status'] === 'success'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span><?= htmlspecialchars($log['msg']) ?>
                                        </span>
                                    <?php elseif ($log['status'] === 'exist'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span><?= htmlspecialchars($log['msg']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span><?= htmlspecialchars($log['msg']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php
} else {
    ?>
    <div class="flex-1 flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-md border border-gray-200 w-[550px] overflow-hidden">
            <div class="bg-blue-50 py-6 flex justify-center border-b border-blue-100">
                <div class="w-20 h-20 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center shadow-sm">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
            
            <div class="p-8 text-center">
                <h2 class="text-2xl font-bold text-slate-800 mb-2">จำลองข้อมูลผู้พักอาศัย (Mock Data)</h2>
                <p class="text-slate-600 mb-6 leading-relaxed">
                    ระบบจะสร้างบัญชีให้ครบทุกห้อง พร้อม <b>สุ่มชื่อ นามสกุล อีเมล และเบอร์โทร</b> ขึ้นมาจำลองเพื่อให้หน้า UI ของคุณดูสมบูรณ์
                </p>

                <div class="text-sm text-slate-500 mb-8 bg-slate-50 p-4 rounded-lg border border-slate-100">
                    <ul class="text-left list-disc list-inside space-y-1">
                        <li><b>Username:</b> จะใช้รูปแบบ <code>room + เลขห้อง</code> (เช่น room102)</li>
                        <li><b>Password:</b> <code>123456</code> (ทุกบัญชี)</li>
                        <li>บัญชีที่มีอยู่แล้วระบบจะ <b>ข้าม</b> โดยอัตโนมัติ</li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <div class="flex justify-center gap-4">
                        <a href="users.php" class="px-6 py-2.5 rounded-xl border border-gray-300 text-gray-600 font-semibold hover:bg-gray-50 transition-all">
                            ยกเลิก
                        </a>
                        <button type="submit" name="confirm_create" class="px-6 py-2.5 rounded-xl bg-blue-600 text-white font-semibold shadow-md hover:bg-blue-700 hover:shadow-lg transition-all">
                            ยืนยัน ดำเนินการสร้าง
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

// เรียกใช้ Footer จากไฟล์ layout.php
admin_footer(); 
$pdo = null; // ปิดการเชื่อมต่อ PDO
?>