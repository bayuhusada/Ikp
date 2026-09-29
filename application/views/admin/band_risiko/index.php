<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array $rows
 * @var int   $belum_kriteria
 * @var array $user
 */
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0">Master Band Risiko</h4>
      <p class="text-muted mb-0">Empat band: BIRU, HIJAU, KUNING, MERAH.</p>
    </div>
    <div class="col text-end">
      <a href="<?php echo base_url('master/band-risiko/form'); ?>" class="btn btn-primary">
        <i class="bx bx-plus-circle"></i> Tambah Band
      </a>
    </div>
  </div>

  <?php if ($belum_kriteria > 0): ?>
    <div class="alert alert-warning">
      <i class="bx bx-error"></i>
      <strong><?php echo (int) $belum_kriteria; ?> band belum punya kriteria.</strong>
      Fitur grading risiko akan menampilkan peringatan dan tidak bisa dipakai
      sampai seluruh kriteria terisi. Kriteria ini wajib diambil dari kebijakan
      resmi fasilitas kesehatan, bukan ditebak sistem.
    </div>
  <?php else: ?>
    <div class="alert alert-success">
      <i class="bx bx-check-circle"></i>
      Seluruh band sudah punya kriteria. Fitur grading risiko siap dipakai.
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <?php foreach ($rows as $r): ?>
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header d-flex align-items-center justify-content-between">
            <div>
              <span class="badge fs-6"
                    style="background-color: <?php echo nilai_aman($r['warna_hex'] ?: '#888'); ?>; color: #fff;">
                <?php echo nilai_aman($r['kode']); ?>
              </span>
              <strong class="ms-2"><?php echo nilai_aman($r['nama']); ?></strong>
            </div>
            <div class="d-inline-flex gap-1">
              <a href="<?php echo base_url('master/band-risiko/form/' . (int) $r['id']); ?>"
                 class="btn btn-sm btn-outline-primary"><i class="bx bx-edit-alt"></i></a>
            </div>
          </div>
          <div class="card-body">
            <dl class="row mb-0 small">
              <dt class="col-4">Urutan</dt>
              <dd class="col-8"><?php echo (int) $r['urutan']; ?></dd>

              <dt class="col-4">Investigasi Sederhana</dt>
              <dd class="col-8">
                <?php if ((int) $r['perlu_investigasi_sederhana'] === 1): ?>
                  <span class="badge bg-label-success">Ya</span>
                <?php else: ?>
                  <span class="badge bg-label-secondary">Tidak</span>
                <?php endif; ?>
              </dd>

              <dt class="col-4">Status</dt>
              <dd class="col-8">
                <?php if ((int) $r['is_active'] === 1): ?>
                  <span class="badge bg-label-success">Aktif</span>
                <?php else: ?>
                  <span class="badge bg-label-danger">Nonaktif</span>
                <?php endif; ?>
              </dd>

              <dt class="col-4">Kriteria</dt>
              <dd class="col-8">
                <?php if (empty(trim((string) $r['kriteria']))): ?>
                  <span class="badge bg-label-warning">
                    <i class="bx bx-error"></i> Belum diisi
                  </span>
                  <div class="text-muted mt-1">Grading tidak bisa dipakai untuk band ini.</div>
                <?php else: ?>
                  <div style="white-space: pre-line;"><?php echo nilai_aman($r['kriteria']); ?></div>
                <?php endif; ?>
              </dd>
            </dl>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</div>
