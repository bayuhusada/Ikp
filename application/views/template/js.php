
    <!-- Core JS -->
    <script src="<?php echo base_url('assets/sneat'); ?>/assets/vendor/libs/jquery/jquery.js"></script>
    <script src="<?php echo base_url('assets/sneat'); ?>/assets/vendor/libs/popper/popper.js"></script>
    <script src="<?php echo base_url('assets/sneat'); ?>/assets/vendor/js/bootstrap.js"></script>
    <script src="<?php echo base_url('assets/sneat'); ?>/assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="<?php echo base_url('assets/sneat'); ?>/assets/vendor/js/menu.js"></script>

    <!-- Vendors JS -->
    <script src="<?php echo base_url('assets/sneat'); ?>/assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="<?php echo base_url('assets/sneat'); ?>/assets/vendor/libs/sweetalert/sweetalert2.all.min.js"></script>
    <script src="<?php echo base_url('assets/sneat'); ?>/assets/vendor/libs/data-tables/datatables.min.js"></script>

    <!-- Main JS -->
    <script src="<?php echo base_url('assets/sneat'); ?>/assets/js/main.js"></script>

    <!-- Notifikasi -->
    <?php if ($this->session->flashdata('success')): ?>
      <script>
        Swal.fire({ icon: 'success', title: 'Berhasil', text: <?php echo json_encode($this->session->flashdata('success'), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, confirmButtonText: 'Mengerti' });
      </script>
    <?php endif; ?>
    <?php if ($this->session->flashdata('error')): ?>
      <script>
        Swal.fire({ icon: 'error', title: 'Gagal', text: <?php echo json_encode($this->session->flashdata('error'), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP); ?>, confirmButtonText: 'Mengerti' });
      </script>
    <?php endif; ?>

    <!-- Script khusus halaman -->
    <?php if (isset($page_script) && $page_script && is_file(VIEWPATH . $page_script)): ?>
      <?php $this->load->view($page_script); ?>
    <?php endif; ?>

  </body>
</html>
