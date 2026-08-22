<?php $user = Auth::user(); ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Dashboard') ?> | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="assets/adminlte/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
    <link rel="stylesheet" href="assets/adminlte/dist/css/adminlte.min.css">
    <style>
        @media print {
            .main-sidebar, .main-header, .main-footer, .no-print { display: none !important; }
            .content-wrapper { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="index.php?page=dashboard" class="nav-link">Home</a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <?php
            // Notifikasi: invoice jatuh tempo, PO overdue, stok minimum
            $notifications = [];
            try {
                $overdueSI = Database::value("SELECT COUNT(*) FROM sales_invoices WHERE status IN ('UNPAID','PARTIAL') AND due_date < CURDATE()");
                if ($overdueSI > 0) $notifications[] = ['icon' => 'file-invoice-dollar', 'color' => 'danger', 'text' => "$overdueSI faktur penjualan jatuh tempo", 'link' => 'index.php?page=sales_invoice&filter=overdue'];
                $overduePI = Database::value("SELECT COUNT(*) FROM purchase_invoices WHERE status IN ('UNPAID','PARTIAL') AND due_date < CURDATE()");
                if ($overduePI > 0) $notifications[] = ['icon' => 'file-contract', 'color' => 'warning', 'text' => "$overduePI faktur pembelian jatuh tempo", 'link' => 'index.php?page=purchase_invoice&filter=overdue'];
                $lowStockCount = Database::value("SELECT COUNT(*) FROM products WHERE stock <= min_stock AND status = 1");
                if ($lowStockCount > 0) $notifications[] = ['icon' => 'box', 'color' => 'info', 'text' => "$lowStockCount produk stok menipis", 'link' => 'index.php?page=stock'];
                $poDraft = Database::value("SELECT COUNT(*) FROM purchase_orders WHERE status = 'DRAFT'");
                if ($poDraft > 0) $notifications[] = ['icon' => 'shopping-cart', 'color' => 'secondary', 'text' => "$poDraft PO menunggu approval", 'link' => 'index.php?page=purchase&status=DRAFT'];
            } catch (Exception $e) {}
            $notifCount = count($notifications);
            ?>
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-bell"></i>
                    <?php if ($notifCount > 0): ?>
                        <span class="badge badge-danger navbar-badge"><?= $notifCount ?></span>
                    <?php endif; ?>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header"><?= $notifCount ?> Notifikasi</span>
                    <div class="dropdown-divider"></div>
                    <?php foreach ($notifications as $n): ?>
                        <a href="<?= e($n['link']) ?>" class="dropdown-item">
                            <i class="fas fa-<?= e($n['icon']) ?> mr-2 text-<?= e($n['color']) ?>"></i> <?= e($n['text']) ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($notifCount === 0): ?>
                        <span class="dropdown-item text-muted">Tidak ada notifikasi</span>
                    <?php endif; ?>
                </div>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="fas fa-user-circle"></i>
                    <?= e($user['full_name']) ?> (<?= e($user['role']) ?>)
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a href="index.php?page=logout" class="dropdown-item text-danger">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </li>
        </ul>
    </nav>
