<?php
session_start();
require_once "config/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password"])) {

        $_SESSION["user_id"] = $user["user_id"];
        $_SESSION["full_name"] = $user["full_name"];
        $_SESSION["role"] = $user["role"];
        $_SESSION["room_id"] = $user["room_id"];

        if ($user["role"] === "admin") {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: user/dashboard.php");
        }

        exit;

    } else {
        $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>

<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <title>เข้าสู่ระบบ</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <!-- FONT -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- ICON -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        * {
            font-family: 'Sarabun', sans-serif;
        }

        body {
            background: #f4f7fb;
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-6">

    <div class="w-full max-w-[500px] bg-white rounded-[28px] shadow-xl border border-slate-200 p-10">

        <!-- LOGO -->
        <div class="flex justify-center mb-7">
            <div class="w-20 h-20 rounded-3xl bg-blue-500 flex items-center justify-center shadow-lg">
                <i data-lucide="house" class="w-10 h-10 text-white"></i>
            </div>
        </div>

        <!-- TITLE -->
        <div class="text-center mb-9">

            <h1 class="text-[30px] font-bold text-slate-800 leading-tight">
                ระบบแจ้งซ่อมและแจ้งรับพัสดุในหอพัก
            </h1>

            <p class="text-slate-500 text-base mt-2">
                เข้าสู่ระบบเพื่อใช้งาน
            </p>

        </div>

        <!-- ERROR -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-200 text-red-700 rounded-2xl px-5 py-4 mb-7">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- FORM -->
        <form method="POST" class="space-y-7">

            <!-- USERNAME -->
            <div>

                <label class="block text-[20px] font-semibold text-slate-700 mb-3">
                    ชื่อผู้ใช้
                </label>

                <input
                    type="text"
                    name="username"
                    required
                    placeholder="กรอกชื่อผู้ใช้"
                    class="w-full h-[58px] rounded-2xl border border-slate-300 px-5 text-[17px] outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-400 transition">

            </div>

            <!-- PASSWORD -->
            <div>

                <label class="block text-[20px] font-semibold text-slate-700 mb-3">
                    รหัสผ่าน
                </label>

                <div class="relative">

                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        placeholder="กรอกรหัสผ่าน"
                        class="w-full h-[58px] rounded-2xl border border-slate-300 px-5 pr-16 text-[17px] outline-none focus:ring-4 focus:ring-blue-100 focus:border-blue-400 transition">

                    <!-- TOGGLE -->
                    <button
                        type="button"
                        onclick="togglePassword()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition">

                        <i id="eyeIcon"
                            data-lucide="eye"
                            class="w-5 h-5 text-slate-600"></i>

                    </button>

                </div>

            </div>

            <!-- BUTTON -->
            <button
                type="submit"
                class="w-full h-[60px] rounded-2xl bg-blue-500 hover:bg-blue-600 text-white text-[20px] font-bold shadow-lg hover:shadow-xl transition">

                เข้าสู่ระบบ

            </button>

        </form>

    </div>

    <script>
        lucide.createIcons();

        function togglePassword() {

            const passwordInput = document.getElementById("password");
            const eyeIcon = document.getElementById("eyeIcon");

            if (passwordInput.type === "password") {

                passwordInput.type = "text";
                eyeIcon.setAttribute("data-lucide", "eye-off");

            } else {

                passwordInput.type = "password";
                eyeIcon.setAttribute("data-lucide", "eye");

            }

            lucide.createIcons();
        }
    </script>

</body>

</html>