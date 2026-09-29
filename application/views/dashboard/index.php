<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array  $ringkasan
 * @var string $mode       'pelapor' atau 'internal'
 * @var array  $per_jenis
 * @var array  $per_ruangan
 * @var array  $status_list
 * @var array  $user
 */
$mode = isset($mode) ? $mode : 'internal';
$r    = isset($ringkasan) ? $ringkasan : array();
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0">Dashboard</h4>
      <p class="text-muted mb-0">
        Selamat datang,
        <strong><?php echo htmlspecialchars($user['nama'], ENT_QUOTES, 'UTF-8'); ?></strong>
        (<?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>)
      </p>
    </div>
  </div>

  <?php if ($mode === 'pelapor'): ?>
    <!-- ============ DASHBOARD PELAPOR ============ -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Laporan Saya</div>
            <div class="fs-3 fw-bold"><?php echo (int) $r['total']; ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Perlu Dilengkapi</div>
            <div class="fs-3 fw-bold text-warning"><?php echo (int) $r['draft']; ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Sedang Diproses</div>
            <div class="fs-3 fw-bold text-primary"><?php echo (int) $r['diproses']; ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Dibuat Hari Ini</div>
            <div class="fs-3 fw-bold"><?php echo (int) $r['hari_ini']; ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <p class="text-muted mb-3">
          Laporan yang sudah dikirim tidak dapat diubah sendiri. Hubungi Kepala Ruangan bila
          ada kesalahan.
        </p>
        <a href="<?php echo base_url('laporan/input'); ?>" class="btn btn-primary">
          <i class="bx bx-edit-alt"></i> Input Laporan Insiden
        </a>
        <a href="<?php echo base_url('laporan'); ?>" class="btn btn-outline-primary">
          <i class="bx bx-list-ul"></i> Laporan Saya
        </a>
      </div>
    </div>

  <?php else: ?>
    <!-- ============ DASHBOARD ADMIN / KEPALA RUANGAN ============ -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Total Laporan</div>
            <div class="fs-3 fw-bold"><?php echo (int) $r['total']; ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Hari Ini</div>
            <div class="fs-3 fw-bold text-info"><?php echo (int) $r['hari_ini']; ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Menunggu Grading</div>
            <div class="fs-3 fw-bold text-warning"><?php echo (int) $r['menunggu_grading']; ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="card">
          <div class="card-body">
            <div class="text-muted small">Selesai</div>
            <div class="fs-3 fw-bold text-success"><?php echo (int) $r['selesai']; ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">Laporan per Jenis Insiden</h5>
          </div>
          <div class="card-body">
            <?php if (empty($per_jenis)): ?>
              <p class="text-muted mb-0">Belum ada laporan.</p>
            <?php else: ?>
              <div id="chart-jenis" class="w-100" style="min-height: 260px;"></div>
              <table class="table table-sm mt-3 mb-0">
                <thead>
                  <tr><th>Kode</th><th>Jenis</th><th class="text-end">Jumlah</th></tr>
                </thead>
                <tbody>
                <?php foreach ($per_jenis as $j): ?>
                  <tr>
                    <td><code><?php echo htmlspecialchars($j['kode'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                    <td><?php echo htmlspecialchars($j['nama'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-end"><?php echo (int) $j['jumlah']; ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">Laporan per Ruangan</h5>
          </div>
          <div class="card-body">
            <?php if (empty($per_ruangan)): ?>
              <p class="text-muted mb-0">Belum ada laporan.</p>
            <?php else: ?>
              <div id="chart-ruangan" class="w-100" style="min-height: 260px;"></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php
// Script grafik hanya untuk dashboard internal
if ($mode !== 'pelapor') {
    $this->load->view('dashboard/_chart');
}
?>
