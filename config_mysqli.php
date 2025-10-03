<?php
// *** แก้ค่าตามเครื่องคุณ ***
const DB_HOST = 'localhost';
const DB_NAME = 's67160334';
const DB_USER = 's67160334';
const DB_PASS = 't-000707';
const DB_CHARSET = 'utf8mb4';

// ตั้งค่า session ให้ปลอดภัยขึ้น
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
  ]);
  session_start();
}

// ให้ mysqli โยน exception เวลา error
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $mysqli->set_charset(DB_CHARSET);
} catch (Throwable $e) {
  http_response_code(500);
  exit('Database connection failed.');
}
