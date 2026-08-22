<?php
// -----------------------------------------------------
// MiniERP - Konfigurasi Aplikasi
// -----------------------------------------------------

define('APP_NAME', 'MiniERP');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', ''); // sesuaikan jika di subfolder, mis. '/erp'

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'erp_db');
define('DB_USER', 'erp_user');
define('DB_PASS', 'erp_pass123');
define('DB_CHARSET', 'utf8mb4');

define('CURRENCY', 'Rp');
define('DATE_FORMAT', 'd/m/Y');

session_start();
date_default_timezone_set('Asia/Jakarta');
