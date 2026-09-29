<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * View generik untuk daftar master data.
 *
 * @var array  $rows, $fields, $spec
 * @var int    $total
 * @var string $search
 * @var bool   $ada_referensi
 * @var array  $user
 *
 * Kolom diambil dari $fields, jadi controller cukup menentukan definisi kolom
 * tanpa perlu mengubah view ini.
 */

$slug  = $spec['slug'];
$label = ucfirst($spec['nama_tunggal']);
$qs    = $search !== '' ? '?search=' . urlencode($search) : '';

// Kolom yang ditampilkan di kartu: semua kecuali is_active (bisa jadi badge).
$shown = array();
foreach ($fields as $f) {
	if ($f['jenis'] !== 'switch') {
		$shown[] = $f;
	}
}
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0"><?php echo nilai_aman($spec['judul']); ?></h4>
      <p class="text-muted mb-0">
        <?php echo (int) $total; ?> data. Dipakai di form pelaporan insiden.
      </p>
    </div>
    <div class="col text-end">
      <a href="<?php echo base_url('master/' . $slug . '/form'); ?>" class="btn btn-primary">
        <i class="bx bx-plus-circle"></i> Tambah <?php echo nilai_aman($label); ?>
      </a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <?php echo form_open('master/' . $slug, array('method' => 'get', 'class' => 'row g-2')); ?>
        <div class="col-md-9">
          <input type="text" class="form-control" name="search"
                 placeholder="Cari kode atau nama" value="<?php echo nilai_aman($search); ?>" />
        </div>
        <div class="col-md-3 d-flex gap-1">
          <button type="submit" class="btn btn-primary w-100"><i class="bx bx-search"></i> Cari</button>
          <a href="<?php echo base_url('master/' . $slug); ?>" class="btn btn-outline-secondary">
            <i class="bx bx-x"></i>
          </a>
        </div>
      <?php echo form_close(); ?>
    </div>
  </div>

  <?php if (empty($rows)): ?>
    <div class="card">
      <div class="card-body text-center text-muted py-5">
        <i class="bx bx-inbox" style="font-size:2.5rem;"></i>
        <p class="mb-1"><?php echo $search !== ''
            ? 'Tidak ada data yang cocok dengan pencarian.'
            : 'Belum ada data. Klik "Tambah ' . nilai_aman($label) . '" untuk membuat.'; ?></p>
      </div>
    </div>
  <?php else: ?>

    <div class="row g-3">
      <?php foreach ($rows as $r): ?>
        <div class="col-md-6 col-xl-4">
          <div class="card h-100">
            <div class="card-header d-flex align-items-start justify-content-between gap-2">
              <div>
                <code class="fs-6"><?php echo nilai_aman($r['kode']); ?></code>
                <div class="fw-bold"><?php echo nilai_aman($r['nama']); ?></div>
              </div>
              <div class="d-inline-flex gap-1 flex-shrink-0">
                <a href="<?php echo base_url('master/' . $slug . '/form/' . (int) $r['id']); ?>"
                   class="btn btn-sm btn-outline-primary" title="Ubah">
                  <i class="bx bx-edit-alt"></i>
                </a>
              </div>
            </div>

            <div class="card-body">
              <dl class="row mb-0 small">
                <?php foreach ($shown as $f): ?>
                  <?php if ($f['nama'] === 'kode') { continue; } ?>
                  <dt class="col-5"><?php echo nilai_aman($f['label']); ?></dt>
                  <dd class="col-7">
                    <?php
                    $v = isset($r[$f['nama']]) ? $r[$f['nama']] : NULL;
                    if ($f['jenis'] === 'textarea') {
                        echo $v === NULL OR $v === ''
                            ? '<span class="text-muted">-</span>'
                            : '<span style="white-space:pre-line;">' . nilai_aman($v) . '</span>';
                    } else {
                        echo $v === NULL OR $v === ''
                            ? '<span class="text-muted">-</span>'
                            : nilai_aman($v);
                    }
                    ?>
                  </dd>
                <?php endforeach; ?>

                <dt class="col-5">Status</dt>
                <dd class="col-7">
                  <?php if ((int) $r['is_active'] === 1): ?>
                    <span class="badge bg-label-success">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-label-danger">Nonaktif</span>
                  <?php endif; ?>
                </dd>

                <?php if ($ada_referensi): ?>
                  <dt class="col-5">Dipakai</dt>
                  <dd class="col-7">
                    <?php if ((int) $r['dipakai'] > 0): ?>
                      <span class="badge bg-label-info"><?php echo (int) $r['dipakai']; ?> data</span>
                    <?php else: ?>
                      <span class="text-muted">belum dipakai</span>
                    <?php endif; ?>
                  </dd>
                <?php endif; ?>
              </dl>
            </div>

            <div class="card-footer d-flex gap-1">
              <?php // WAJIB form_open(): CI3 menolak POST tanpa csrf_token dengan 403. ?>
              <?php echo form_open('master/' . $slug . '/toggle/' . (int) $r['id'] . ($qs !== '' ? '?' . $qs : ''), array('class' => 'd-inline')); ?>
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                  <i class="bx bx-power-off"></i>
                  <?php echo (int) $r['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan'; ?>
                </button>
              <?php echo form_close(); ?>

              <?php echo form_open('master/' . $slug . '/hapus/' . (int) $r['id'], array('class' => 'd-inline ms-auto')); ?>
                <button type="submit" class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Hapus permanen &quot;<?php echo nilai_aman($r['nama']); ?>&quot;?<?php
                        echo (int) $r['dipakai'] > 0 ? '\n\nData ini masih dipakai, jadi penghapusan akan ditolak.' : ''; ?>')">
                  <i class="bx bx-trash"></i> Hapus
                </button>
              <?php echo form_close(); ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

</div>
