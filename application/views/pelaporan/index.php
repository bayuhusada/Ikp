<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array  $rows, filter, ruangan_list, jenis_list, band_list, status_list
 * @var int    $total, $page, $pages, $limit
 * @var array  $user
 */
$f = isset($filter) ? $filter : array();
$f = array_merge(array(
    'search' => '', 'date_from' => '', 'date_to' => '',
    'ruangan_id' => 0, 'jenis_insiden_id' => 0, 'risk_band_id' => 0, 'status_id' => 0,
), $f);

$qs = array_filter(array(
    'search' => $f['search'], 'date_from' => $f['date_from'], 'date_to' => $f['date_to'],
    'ruangan_id' => $f['ruangan_id'] ?: null,
    'jenis_insiden_id' => $f['jenis_insiden_id'] ?: null,
    'risk_band_id' => $f['risk_band_id'] ?: null,
    'status_id' => $f['status_id'] ?: null,
    'limit' => $limit,
));

// Status laporan saja, jangan tercampur status investigasi/tindakan
$statusLaporan = array();
foreach ($status_list as $s) {
    if (isset($s['untuk']) && $s['untuk'] === 'laporan') {
        $statusLaporan[] = $s;
    }
}
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0"><?php echo nilai_aman($title); ?></h4>
      <p class="text-muted mb-0">
        <?php if ($user['role'] === 'pelapor'): ?>
          Hanya laporan yang Anda buat sendiri.
        <?php elseif ($user['role'] === 'kepala_ruangan'): ?>
          Hanya laporan dari ruangan Anda.
        <?php else: ?>
          Seluruh laporan dari semua ruangan.
        <?php endif; ?>
      </p>
    </div>
    <div class="col text-end">
      <a href="<?php echo base_url('laporan/input'); ?>" class="btn btn-primary">
        <i class="bx bx-edit-alt"></i> Input Laporan
      </a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <?php echo form_open('laporan', array('method' => 'get', 'class' => 'row g-2')); ?>
        <div class="col-md-4">
          <input type="text" class="form-control" name="search"
                 placeholder="Cari nomor, kronologi, atau tempat"
                 value="<?php echo nilai_aman($f['search']); ?>" />
        </div>
        <div class="col-md-2">
          <input type="date" class="form-control" name="date_from" value="<?php echo nilai_aman($f['date_from']); ?>" />
        </div>
        <div class="col-md-2">
          <input type="date" class="form-control" name="date_to" value="<?php echo nilai_aman($f['date_to']); ?>" />
        </div>
        <div class="col-md-2">
          <select name="ruangan_id" class="form-select">
            <option value="">Semua Ruangan</option>
            <?php echo pilihan($ruangan_list, $f['ruangan_id'] ?: null); ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="jenis_insiden_id" class="form-select">
            <option value="">Semua Jenis</option>
            <?php echo pilihan($jenis_list, $f['jenis_insiden_id'] ?: null); ?>
          </select>
        </div>

        <div class="col-md-2">
          <select name="risk_band_id" class="form-select">
            <option value="">Semua Band</option>
            <?php echo pilihan($band_list, $f['risk_band_id'] ?: null); ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="status_id" class="form-select">
            <option value="">Semua Status</option>
            <?php echo pilihan($statusLaporan, $f['status_id'] ?: null); ?>
          </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">
            <i class="bx bx-search"></i> Filter
          </button>
          <a href="<?php echo base_url('laporan'); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
      <?php echo form_close(); ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <h5 class="mb-0"><?php echo (int) $total; ?> laporan</h5>
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
            <th>Akibat</th>
            <th>Band Risiko</th>
            <th>Status</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="8" class="text-center text-muted py-4">
              Belum ada laporan yang cocok dengan filter.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><code><?php echo nilai_aman($row['nomor']); ?></code></td>
              <td class="text-nowrap"><?php echo waktu($row['tanggal_insiden']); ?></td>
              <td><small><?php echo nilai_aman($row['ruang_nama']); ?></small></td>
              <td>
                <span class="badge bg-label-secondary"><?php echo nilai_aman($row['jenis_kode']); ?></span>
                <small class="ms-1"><?php echo nilai_aman($row['jenis_nama']); ?></small>
              </td>
              <td><small><?php echo nilai_aman($row['akibat_nama']); ?></small></td>
              <td>
                <?php if (empty($row['band_kode'])): ?>
                  <span class="text-muted">-</span>
                <?php else: ?>
                  <span class="badge"
                        style="background-color: <?php echo nilai_aman($row['band_warna'] ?: '#888'); ?>; color: #fff;">
                    <?php echo nilai_aman($row['band_kode']); ?>
                  </span>
                <?php endif; ?>
              </td>
              <td><span class="badge bg-label-secondary"><?php echo nilai_aman($row['status_nama']); ?></span></td>
              <td class="text-end">
                <a href="<?php echo base_url('laporan/detail/' . (int) $row['id']); ?>"
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
            <a class="page-link" href="<?php echo base_url('laporan') . '?' . http_build_query($qs + array('page' => $i)); ?>">
              <?php echo $i; ?>
            </a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>

</div>
