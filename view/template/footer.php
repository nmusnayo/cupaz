<footer class="main-footer">
  <strong>Copyright &copy; 2025 <a href="https://adminlte.io">GRUPO 11</a>.</strong>
  All rights reserved.
  <div class="float-right d-none d-sm-inline-block">
    <b>Version</b> 3.2.0
  </div>
</footer>

<!-- Control Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
  <!-- Control sidebar content goes here -->
</aside>
<!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->
<script>
  const searchInput = document.getElementById('searchInput');
  const itemsList = document.getElementById('itemsList');
  if (searchInput && itemsList) {
    searchInput.addEventListener('keyup', function () {
      let filter = this.value.toLowerCase();
      let items = itemsList.getElementsByTagName('li');

      Array.from(items).forEach(function (item) {
        let itemText = item.textContent || item.innerText;
        item.style.display = itemText.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
      });
    });
  }
</script>
<!-- jQuery -->
<script src="<?php echo URL_RESOURCES; ?>adminlte/plugins/jquery/jquery.min.js"></script>
<!-- jQuery UI 1.11.4 -->
<script src="<?php echo URL_RESOURCES; ?>adminlte/plugins/jquery-ui/jquery-ui.min.js"></script>
<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
<script>
  $.widget.bridge('uibutton', $.ui.button)
</script>
<!-- Bootstrap 4 -->
<script src="<?php echo URL_RESOURCES; ?>adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- ChartJS -->
<script src="<?php echo URL_RESOURCES; ?>adminlte/plugins/chart.js/Chart.min.js"></script>
<!-- Sparkline -->
<script src="<?php echo URL_RESOURCES; ?>adminlte/plugins/sparklines/sparkline.js"></script>
<!-- JQVMap -->
<script src="<?php echo URL_RESOURCES; ?>adminlte/plugins/jqvmap/jquery.vmap.min.js"></script>
<script src="<?php echo URL_RESOURCES; ?>adminlte/plugins/jqvmap/maps/jquery.vmap.usa.js"></script>
<!-- jQuery Knob Chart -->
<script src="<?php echo URL_RESOURCES; ?>adminlte/plugins/jquery-knob/jquery.knob.min.js"></script>
<!-- daterangepicker -->
<script src="<?php echo URL_RESOURCES; ?>adminlte/plugins/moment/moment.min.js"></script>
<script src="<?php echo URL_RESOURCES; ?>adminlte/plugins/daterangepicker/daterangepicker.js"></script>
<!-- Tempusdominus Bootstrap 4 -->
<script
  src="<?php echo URL_RESOURCES; ?>adminlte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<!-- Summernote -->
<script src="<?php echo URL_RESOURCES; ?>adminlte/plugins/summernote/summernote-bs4.min.js"></script>
<!-- overlayScrollbars -->
<script
  src="<?php echo URL_RESOURCES; ?>adminlte/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<!-- AdminLTE App -->
<script src="<?php echo URL_RESOURCES; ?>adminlte/dist/js/adminlte.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const token = csrfMeta ? csrfMeta.getAttribute('content') : '';
    if (!token) return;
    document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(function (form) {
      if (!form.querySelector('input[name="_csrf"]')) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_csrf';
        input.value = token;
        form.appendChild(input);
      }
    });
  });
</script>
<!-- AdminLTE dashboard demo (This is only for demo purposes) -->
<script src="<?php echo URL_RESOURCES; ?>adminlte/dist/js/pages/dashboard.js"></script>
</body>

</html>
