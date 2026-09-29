<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array $rows
 * @var int   $total, $page, $pages, $limit
 * @var string $search
 * @var array $user
 */
$qs = array_filter(array('search' => $search, 'limit' => $limit));
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0">Arsip Laporan</h4>
      <p class="text-muted mb-0">Laporan yang sudah diarsipkan. Data tidak dihapus permanen.</p>
    </div>
    <div class="col text-end">
      <a href="<?php echo base_url('laporan'); ?>" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Kembali
      </a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <?php echo form_open('laporan/arsip', array('method' => 'get', 'class' => 'row g-2')); ?>
        <div class="col-md-6">
          <input type="text" class="form-control" name="search" placeholder="Cari nomor atau kronologi"
                 value="<?php echo nilai_aman($search); ?>" />
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="bx bx-search"></i> Cari</button>
          <a href="<?php echo base_url('laporan/arsip'); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
      <?php echo form_close(); ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <h5 class="mb-0"><?php echo (int) $total; ?> laporan terarsip</h5>
      <span class="text-muted small">Halaman <?php echo (int) $page; ?> dari <?php echo max(1, (int) $pages); ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Nomor</th>
            <th>Tanggal Insiden</th>
            <th>Ruangan</th>
            <th>Jenis</th>
            <th>Status saat Arsip</th>
            <th>Diarsipkan</th>
            <th>Oleh</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Belum ada laporan terarsip.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><code><?php echo nilai_aman($row['nomor']); ?></code></td>
              <td class="text-nowrap"><?php echo waktu($row['tanggal_insiden']); ?></td>
              <td><small><?php echo nilai_aman($row['ruang_nama']); ?></small></td>
              <td><small><?php echo nilai_aman($row['jenis_nama']); ?></small></td>
              <td><small><?php echo nilai_aman($row['status_nama']); ?></small></td>
              <td class="text-nowrap"><small><?php echo waktu($row['deleted_at']); ?></small></td>
              <td><small><?php echo nilai_aman($row['dibuat_oleh']); ?></small></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
