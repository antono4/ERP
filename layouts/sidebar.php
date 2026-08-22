<?php $curPage = $_GET['page'] ?? 'dashboard'; ?>
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="index.php" class="brand-link text-center">
        <span class="brand-text font-weight-bold"><i class="fas fa-cubes"></i> <?= APP_NAME ?></span>
    </a>
    <div class="sidebar">
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" data-accordion="false">

                <li class="nav-item">
                    <a href="index.php?page=dashboard" class="nav-link <?= activeMenu('dashboard', $curPage) ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i><p>Dashboard</p>
                    </a>
                </li>

                <li class="nav-header">MASTER DATA</li>
                <li class="nav-item <?= menuOpen(['categories','products'], $curPage) ?>">
                    <a href="#" class="nav-link <?= activeMenu('categories', $curPage) . activeMenu('products', $curPage) ?>">
                        <i class="nav-icon fas fa-box"></i>
                        <p>Item Master <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="index.php?page=products" class="nav-link <?= activeMenu('products', $curPage) ?>">
                                <i class="far fa-circle nav-icon"></i><p>Produk</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="index.php?page=categories" class="nav-link <?= activeMenu('categories', $curPage) ?>">
                                <i class="far fa-circle nav-icon"></i><p>Kategori</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item <?= menuOpen(['customers','suppliers'], $curPage) ?>">
                    <a href="#" class="nav-link <?= activeMenu('customers', $curPage) . activeMenu('suppliers', $curPage) ?>">
                        <i class="nav-icon fas fa-handshake"></i>
                        <p>Business Partner <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="index.php?page=customers" class="nav-link <?= activeMenu('customers', $curPage) ?>">
                                <i class="far fa-circle nav-icon"></i><p>Customer</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="index.php?page=suppliers" class="nav-link <?= activeMenu('suppliers', $curPage) ?>">
                                <i class="far fa-circle nav-icon"></i><p>Supplier</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=price_levels" class="nav-link <?= activeMenu('price_levels', $curPage) ?>">
                        <i class="nav-icon fas fa-tags"></i><p>Harga Bertingkat</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=users" class="nav-link <?= activeMenu('users', $curPage) ?>">
                        <i class="nav-icon fas fa-users"></i><p>User</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=activity_log" class="nav-link <?= activeMenu('activity_log', $curPage) ?>">
                        <i class="nav-icon fas fa-history"></i><p>Activity Log</p>
                    </a>
                </li>

                <li class="nav-header">TRANSAKSI</li>
                <li class="nav-item <?= menuOpen(['purchase','purchase_form','purchase_view'], $curPage) ?>">
                    <a href="index.php?page=purchase" class="nav-link <?= activeMenu('purchase', $curPage) ?>">
                        <i class="nav-icon fas fa-shopping-cart"></i><p>Purchasing (PO)</p>
                    </a>
                </li>
                <li class="nav-item <?= menuOpen(['sales','sales_form','sales_view'], $curPage) ?>">
                    <a href="index.php?page=sales" class="nav-link <?= activeMenu('sales', $curPage) ?>">
                        <i class="nav-icon fas fa-cash-register"></i><p>Sales (SO)</p>
                    </a>
                </li>
                <li class="nav-item <?= menuOpen(['delivery','delivery_form'], $curPage) ?>">
                    <a href="index.php?page=delivery" class="nav-link <?= activeMenu('delivery', $curPage) ?>">
                        <i class="nav-icon fas fa-truck"></i><p>Surat Jalan</p>
                    </a>
                </li>
                <li class="nav-item <?= menuOpen(['sales_return','purchase_return'], $curPage) ?>">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-undo"></i>
                        <p>Retur <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="index.php?page=sales_return" class="nav-link <?= activeMenu('sales_return', $curPage) ?>">
                                <i class="far fa-circle nav-icon"></i><p>Retur Penjualan</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="index.php?page=purchase_return" class="nav-link <?= activeMenu('purchase_return', $curPage) ?>">
                                <i class="far fa-circle nav-icon"></i><p>Retur Pembelian</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <li class="nav-header">BILLING (AR/AP)</li>
                <li class="nav-item">
                    <a href="index.php?page=sales_invoice" class="nav-link <?= activeMenu('sales_invoice', $curPage) ?>">
                        <i class="nav-icon fas fa-file-invoice-dollar"></i><p>Faktur Penjualan</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=purchase_invoice" class="nav-link <?= activeMenu('purchase_invoice', $curPage) ?>">
                        <i class="nav-icon fas fa-file-contract"></i><p>Faktur Pembelian</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=payments" class="nav-link <?= activeMenu('payments', $curPage) ?>">
                        <i class="nav-icon fas fa-money-check-alt"></i><p>Pembayaran</p>
                    </a>
                </li>

                <li class="nav-header">PERSEDIAAN</li>
                <li class="nav-item">
                    <a href="index.php?page=stock" class="nav-link <?= activeMenu('stock', $curPage) ?>">
                        <i class="nav-icon fas fa-warehouse"></i><p>Stok Barang</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=movements" class="nav-link <?= activeMenu('movements', $curPage) ?>">
                        <i class="nav-icon fas fa-exchange-alt"></i><p>Kartu Stok</p>
                    </a>
                </li>
                <li class="nav-item <?= menuOpen(['opname','opname_form','opname_view'], $curPage) ?>">
                    <a href="index.php?page=opname" class="nav-link <?= activeMenu('opname', $curPage) ?>">
                        <i class="nav-icon fas fa-clipboard-check"></i><p>Stock Opname</p>
                    </a>
                </li>

                <li class="nav-header">KEUANGAN</li>
                <li class="nav-item">
                    <a href="index.php?page=accounts" class="nav-link <?= activeMenu('accounts', $curPage) ?>">
                        <i class="nav-icon fas fa-book"></i><p>Chart of Accounts</p>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=journal" class="nav-link <?= activeMenu('journal', $curPage) ?>">
                        <i class="nav-icon fas fa-file-invoice"></i><p>Jurnal Umum</p>
                    </a>
                </li>

                <li class="nav-header">LAPORAN</li>
                <li class="nav-item <?= menuOpen(['report_sales','report_purchase','report_stock','report_profit'], $curPage) ?>">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-chart-bar"></i>
                        <p>Laporan <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="index.php?page=report_sales" class="nav-link <?= activeMenu('report_sales', $curPage) ?>">
                                <i class="far fa-circle nav-icon"></i><p>Penjualan</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="index.php?page=report_purchase" class="nav-link <?= activeMenu('report_purchase', $curPage) ?>">
                                <i class="far fa-circle nav-icon"></i><p>Pembelian</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="index.php?page=report_stock" class="nav-link <?= activeMenu('report_stock', $curPage) ?>">
                                <i class="far fa-circle nav-icon"></i><p>Stok</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="index.php?page=report_profit" class="nav-link <?= activeMenu('report_profit', $curPage) ?>">
                                <i class="far fa-circle nav-icon"></i><p>Laba Rugi</p>
                            </a>
                        </li>
                    </ul>
                </li>

            </ul>
        </nav>
    </div>
</aside>
