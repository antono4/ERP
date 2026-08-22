<footer class="main-footer">
    <strong><?= APP_NAME ?> v<?= APP_VERSION ?></strong> &mdash; Mini ERP System (PHP + AdminLTE + MySQL)
    <div class="float-right d-none d-sm-inline-block">
        <b><?= date('d F Y') ?></b>
    </div>
</footer>
</div>

<script src="assets/adminlte/plugins/jquery/jquery.min.js"></script>
<script src="assets/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="assets/adminlte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="assets/adminlte/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="assets/adminlte/plugins/select2/js/select2.full.min.js"></script>
<script src="assets/adminlte/plugins/chart.js/Chart.min.js"></script>
<script src="assets/adminlte/dist/js/adminlte.min.js"></script>
<script>
    $(function () {
        $('.datatable').DataTable({ responsive: true, autoWidth: false, pageLength: 10 });
        $('.select2').select2({ theme: 'bootstrap4', width: '100%' });
    });
</script>
<?php if (function_exists('module_scripts')) { module_scripts(); } ?>
</body>
</html>
