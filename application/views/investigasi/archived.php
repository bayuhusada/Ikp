<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array $rows
 * @var array $user
 */
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0">Arsip Investigasi</h4>
      <p class="text-muted mb-0">Lembar investigasi yang sudah diarsipkan. Data tidak dihapus permanen.</p>
    </div>
    <div class="col text-end">
      <a href="<?php echo base_url('investigasi'); ?>" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Kembali
      </a>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Nomor</th>
            <th>Nomor Laporan</th>
            <th>Ruangan</th>
            <th>Band</th>
            <th>Status saat Arsip</th>
            <th>Diarsipkan</th>
            <th>Oleh</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Belum ada lembar investigasi terarsip.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><code><?php echo nilai_aman($row['nomor']); ?></code></td>
              <td><small><?php echo nilai_aman($row['nomor_laporan']); ?></small></td>
              <td><small><?php echo nilai_aman($row['ruang_nama']); ?></small></td>
              <td><small><?php echo nilai_aman($row['band_kode']); ?></small></td>
              <td><small><?php echo nilai_aman($row['status_nama']); ?></small></td>
              <td class="text-nowrap"><small><?php echo waktu($row['deleted_at']); ?></small></td>
              <td><small><?php echo nilai_aman($row['dihapus_oleh']); ?></small></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
