<?php
require_once "../config/db.php";

$username = 'admin';
$password = '123456';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // 1. ตรวจสอบว่ามีผู้ใช้ admin อยู่ในระบบหรือไม่
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        // ถ้ามีบัญชีอยู่แล้ว ให้ UPDATE รหัสผ่านใหม่โดยไม่ต้องลบข้อมูล (หลีกเลี่ยง Foreign Key Error)
        $update = $pdo->prepare("UPDATE users SET password = ?, status = 'ใช้งานอยู่', must_change_password = 0 WHERE username = ?");
        $update->execute([$hashed_password, $username]);
        echo "<h2 style='color: green;'>อัปเดตรหัสผ่าน Admin เรียบร้อยแล้ว!</h2>";
    } else {
        // ถ้ายังไม่มี ให้สร้างบัญชี Admin ใหม่
        $insert = $pdo->prepare("INSERT INTO users (username, password, role, full_name, email, phone, status, must_change_password) VALUES (?, ?, 'admin', 'ผู้ดูแลระบบ', 'admin@dorm.com', '0812345678', 'ใช้งานอยู่', 0)");
        $insert->execute([$username, $hashed_password]);
        echo "<h2 style='color: green;'>สร้างบัญชี Admin ใหม่เรียบร้อยแล้ว!</h2>";
    }

    echo "<p><b>Username:</b> admin</p>";
    echo "<p><b>Password:</b> 123456</p>";
    echo "<p><a href='../login.php'>คลิกที่นี่เพื่อไปหน้าล็อกอิน</a></p>";
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>เกิดข้อผิดพลาด:</h2> " . $e->getMessage();
}
?>