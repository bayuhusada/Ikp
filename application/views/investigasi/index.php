<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array  $rows, filter, ruangan_list, band_list, status_list
 * @var int    $total, $page, $pages, $limit
 * @var array  $user
 */
$f = isset($filter) ? $filter : array();
$f = array_merge(array('search' => '', 'ruangan_id' => 0, 'status_id' => 0, 'risk_band_id' => 0), $f);
$qs = array_filter(array(
    'search' => $f['search'],
    'ruangan_id' => $f['ruangan_id'] ?: null,
    'status_id' => $f['status_id'] ?: null,
    'risk_band_id' => $f['risk_band_id'] ?: null,
    'limit' => $limit,
));
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0"><?php echo nilai_aman($title); ?></h4>
      <p class="text-muted mb-0">
        <?php if ($user['role'] === 'admin'): ?>
          Semua lembar investigasi.<a href="<?php echo base_url('investigasi/arsip'); ?>"> Lihat arsip</a>.
        <?php else: ?>
          Hanya lembar investigasi dari ruangan Anda.
        <?php endif; ?>
      </p>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <?php echo form_open('investigasi', array('method' => 'get', 'class' => 'row g-2')); ?>
        <div class="col-md-4">
          <input type="text" class="form-control" name="search"
                 placeholder="Cari nomor, penyebab, atau akar masalah"
                 value="<?php echo nilai_aman($f['search']); ?>" />
        </div>
        <div class="col-md-3">
          <select name="status_id" class="form-select">
            <option value="">Semua Status</option>
            <?php echo pilihan($status_list, $f['status_id'] ?: null); ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="risk_band_id" class="form-select">
            <option value="">Semua Band</option>
            <?php echo pilihan($band_list, $f['risk_band_id'] ?: null); ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="ruangan_id" class="form-select">
            <option value="">Semua Ruangan</option>
            <?php echo pilihan($ruangan_list, $f['ruangan_id'] ?: null); ?>
          </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
          <button type="submit" class="btn btn-primary w-100"><i class="bx bx-search"></i></button>
        </div>
        <div class="col-md-12 d-flex gap-2">
          <a href="<?php echo base_url('investigasi'); ?>" class="btn btn-outline-secondary btn-sm">Reset Filter</a>
        </div>
      <?php echo form_close(); ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <h5 class="mb-0"><?php echo (int) $total; ?> lembar investigasi</h5>
      <span class="text-muted small">Halaman <?php echo (int) $page; ?> dari <?php echo max(1, (int) $pages); ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Nomor</th>
            <th>Nomor Laporan</th>
            <th>Tanggal Investigasi</th>
            <th>Ruangan</th>
            <th>Band</th>
            <th>Status</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="7" class="text-center text-muted py-4">
              Belum ada lembar investigasi.<br>
              <small>Buat dari halaman detail laporan yang sudah di-grading band BIRU atau HIJAU.</small>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><code><?php echo nilai_aman($row['nomor']); ?></code></td>
              <td><small><?php echo nilai_aman($row['nomor_laporan']); ?></small></td>
              <td class="text-nowrap"><small><?php echo waktu($row['tanggal_investigasi']); ?></small></td>
              <td><small><?php echo nilai_aman($row['ruang_nama']); ?></small></td>
              <td>
                <span class="badge" style="background-color:<?php echo nilai_aman($row['band_warna'] ?: '#888'); ?>;color:#fff;">
                  <?php echo nilai_aman($row['band_kode']); ?>
                </span>
              </td>
              <td><span class="badge bg-label-secondary"><?php echo nilai_aman($row['status_nama']); ?></span></td>
              <td class="text-end">
                <a href="<?php echo base_url('investigasi/detail/' . (int) $row['id']); ?>"
                   class="btn btn-sm btn-outline-primary">
                  <i class="bx bx-show-alt"></i> Detail
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <li class="page-item <?php echo $i === (int) $page ? 'active' : ''; ?>">
            <a class="page-link" href="<?php echo base_url('investigasi') . '?' . http_build_query($qs + array('page' => $i)); ?>">
              <?php echo $i; ?>
            </a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>

</div>
