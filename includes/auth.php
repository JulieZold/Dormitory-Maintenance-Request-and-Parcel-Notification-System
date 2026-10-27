<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_login() {
    if (!isset($_SESSION["user_id"])) {
        header("Location: /dorm_repair_parcel_system/login.php");
        exit;
    }
}

function require_admin() {
    require_login();
    if ($_SESSION["role"] !== "admin") {
        header("Location: /dorm_repair_parcel_system/login.php");
        exit;
    }
}

function require_user() {
    require_login();
    if ($_SESSION["role"] !== "user") {
        header("Location: /dorm_repair_parcel_system/login.php");
        exit;
    }
}
?>