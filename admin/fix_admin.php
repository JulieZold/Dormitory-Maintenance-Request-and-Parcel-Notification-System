<?php
require_once "../config/db.php";

// สร้างรหัสผ่าน '123456' แบบถูกต้องผ่านฟังก์ชันของ PHP โดยตรง
$new_password = password_hash("123456", PASSWORD_DEFAULT);

// อัปเดตรหัสผ่านของแอดมิน
$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt->execute([$new_password]);

echo "รีเซ็ตรหัสผ่านแอดมินเป็น 123456 สำเร็จแล้ว! ตอนนี้สามารถกลับไปเข้าสู่ระบบได้เลย";
?>