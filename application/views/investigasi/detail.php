<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array  $row, rekomendasi, tindakan, status_list, status_inv, band_list, tindakan_status
 * @var bool   $boleh_ubah, $boleh_pmkp
 * @var array  $user
 */
$id = (int) $row['id'];
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0">Lembar Investigasi <?php echo nilai_aman($row['nomor']); ?></h4>
      <p class="text-muted mb-0">
        Dari laporan <code><?php echo nilai_aman($row['nomor_laporan']); ?></code>
        &middot; status
        <span class="badge bg-label-primary"><?php echo nilai_aman($row['status_nama']); ?></span>
        &middot; band
        <span class="badge" style="background-color:<?php echo nilai_aman($row['band_warna'] ?: '#888'); ?>;color:#fff;">
          <?php echo nilai_aman($row['band_kode']); ?>
        </span>
      </p>
    </div>
    <div class="col text-end">
      <?php if ($boleh_ubah): ?>
        <a href="<?php echo base_url('investigasi/edit/' . $id); ?>" class="btn btn-outline-primary">
          <i class="bx bx-edit-alt"></i> Edit
        </a>
      <?php endif; ?>
      <a href="<?php echo base_url('investigasi'); ?>" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Kembali
      </a>
    </div>
  </div>

  <!-- Ubah status -->
  <div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <div class="text-muted small">Status saat ini</div>
        <div class="fw-bold"><?php echo nilai_aman($row['status_nama']); ?></div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <?php echo form_open('investigasi/status/' . $id, array('class' => 'd-flex gap-2 align-items-center')); ?>
          <select class="form-select form-select-sm" name="status_kode" style="width:auto;">
            <?php foreach ($status_inv as $s): ?>
              <?php if ((int) $s['urutan'] < (int) $row['status_urutan']) { continue; } ?>
              <option value="<?php echo nilai_aman($s['kode']); ?>"
                <?php echo $s['id'] == $row['status_id'] ? 'selected' : ''; ?>>
                <?php echo nilai_aman($s['nama']); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-primary">Simpan Status</button>
        <?php echo form_close(); ?>
      </div>
    </div>
  </div>

  <div class="row g-3">

    <div class="col-lg-8">

      <!-- Data dasar -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Data Dasar</h6></div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-sm-4">Nomor</dt><dd class="col-sm-8"><?php echo nilai_aman($row['nomor']); ?></dd>
            <dt class="col-sm-4">Tanggal Investigasi</dt><dd class="col-sm-8"><?php echo waktu($row['tanggal_investigasi']); ?></dd>
            <dt class="col-sm-4">Ruangan</dt><dd class="col-sm-8"><?php echo nilai_aman($row['ruang_nama']); ?></dd>
            <dt class="col-sm-4">Jenis Insiden</dt>
            <dd class="col-sm-8"><?php echo nilai_aman($row['jenis_kode'] . ' - ' . $row['jenis_nama']); ?></dd>
            <dt class="col-sm-4">Akibat Insiden</dt><dd class="col-sm-8"><?php echo nilai_aman($row['akibat_nama']); ?></dd>
            <dt class="col-sm-4">Tanggal Insiden</dt><dd class="col-sm-8"><?php echo waktu($row['tanggal_insiden']); ?></dd>
            <dt class="col-sm-4">Nama Pasien</dt><dd class="col-sm-8"><?php echo nilai_aman($row['nama_pasien']); ?> (MR <?php echo nilai_aman($row['nomor_mr']); ?>)</dd>
          </dl>
        </div>
      </div>

      <!-- Kronologi laporan asal -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Kronologi Insiden (dari laporan)</h6></div>
        <div class="card-body small">
          <?php echo nl2br(nilai_aman($row['kronologi_insiden'])); ?>
        </div>
      </div>

      <!-- Analisis -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Analisis</h6></div>
        <div class="card-body">
          <label class="form-label small fw-bold">Penyebab Langsung Insiden</label>
          <div class="border rounded p-2 mb-3 small bg-light"><?php echo nl2br(nilai_aman($row['penyebab_langsung'])); ?></div>

          <label class="form-label small fw-bold">Penyebab yang Melatarbelakangi / Akar Masalah</label>
          <div class="border rounded p-2 small bg-light"><?php echo nl2br(nilai_aman($row['akar_masalah'])); ?></div>
        </div>
      </div>

      <!-- Rekomendasi -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Rekomendasi</h6></div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Rekomendasi</th><th>PIC</th><th>Tanggal</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($rekomendasi)): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">Belum ada rekomendasi.</td></tr>
            <?php else: ?>
              <?php foreach ($rekomendasi as $rec): ?>
                <tr>
                  <td><small><?php echo nl2br(nilai_aman($rec['rekomendasi'])); ?></small></td>
                  <td><small><?php echo nilai_aman($rec['penanggung_jawab']); ?></small></td>
                  <td><small><?php echo waktu($rec['tanggal_rekomendasi']); ?></small></td>
                  <td>
                    <?php if ($boleh_ubah): ?>
                      <?php echo form_open('investigasi/rekomendasi/hapus/' . $id . '/' . (int) $rec['id'], array('class' => 'd-inline')); ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Hapus rekomendasi ini?')"><i class="bx bx-trash"></i></button>
                      <?php echo form_close(); ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($boleh_ubah): ?>
          <div class="card-body border-top">
            <?php echo form_open('investigasi/rekomendasi/tambah/' . $id, array('class' => 'row g-2')); ?>
              <div class="col-md-6">
                <input type="text" class="form-control" name="rekomendasi" required maxlength="5000"
                       placeholder="Rekomendasi baru" />
              </div>
              <div class="col-md-3">
                <input type="text" class="form-control" name="penanggung_jawab" maxlength="150"
                       placeholder="Penanggung jawab" />
              </div>
              <div class="col-md-2">
                <input type="date" class="form-control" name="tanggal_rekomendasi" />
              </div>
              <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="bx bx-plus"></i></button>
              </div>
            <?php echo form_close(); ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Tindakan -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Tindakan yang Akan Dilakukan</h6></div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Tindakan</th><th>PIC</th><th>Tanggal</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($tindakan)): ?>
              <tr><td colspan="5" class="text-center text-muted py-3">Belum ada tindakan.</td></tr>
            <?php else: ?>
              <?php foreach ($tindakan as $t): ?>
                <tr>
                  <td><small><?php echo nl2br(nilai_aman($t['tindakan'])); ?></small></td>
                  <td><small><?php echo nilai_aman($t['penanggung_jawab']); ?></small></td>
                  <td><small><?php echo waktu($t['tanggal_tindakan']); ?></small></td>
                  <td>
                    <?php if ($boleh_ubah): ?>
                      <?php echo form_open('investigasi/tindakan/status/' . $id . '/' . (int) $t['id'], array('class' => 'd-inline')); ?>
                        <select class="form-select form-select-sm" name="status_id" onchange="this.form.submit()">
                          <?php foreach ($tindakan_status as $ts): ?>
                            <option value="<?php echo (int) $ts['id']; ?>"
                              <?php echo (int) $t['status_id'] === (int) $ts['id'] ? 'selected' : ''; ?>>
                              <?php echo nilai_aman($ts['nama']); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      <?php echo form_close(); ?>
                    <?php else: ?>
                      <span class="badge bg-label-secondary"><?php echo nilai_aman($t['status_nama']); ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($boleh_ubah): ?>
                      <?php echo form_open('investigasi/tindakan/hapus/' . $id . '/' . (int) $t['id'], array('class' => 'd-inline')); ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Hapus tindakan ini?')"><i class="bx bx-trash"></i></button>
                      <?php echo form_close(); ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($boleh_ubah): ?>
          <div class="card-body border-top">
            <?php echo form_open('investigasi/tindakan/tambah/' . $id, array('class' => 'row g-2')); ?>
              <div class="col-md-6">
                <input type="text" class="form-control" name="tindakan" required maxlength="5000"
                       placeholder="Tindakan baru" />
              </div>
              <div class="col-md-3">
                <input type="text" class="form-control" name="penanggung_jawab" maxlength="150"
                       placeholder="Penanggung jawab" />
              </div>
              <div class="col-md-2">
                <input type="date" class="form-control" name="tanggal_tindakan" />
              </div>
              <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="bx bx-plus"></i></button>
              </div>
            <?php echo form_close(); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="col-lg-4">
      <!-- Penyetuju -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Manager / Kepala Bagian / Kepala Unit</h6></div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-5">Penyetuju</dt><dd class="col-7"><?php echo nilai_aman($row['nama_penyetuju']); ?></dd>
            <dt class="col-5">Mulai</dt><dd class="col-7"><?php echo waktu($row['tanggal_mulai_investigasi']); ?></dd>
            <dt class="col-5">Selesai</dt><dd class="col-7"><?php echo waktu($row['tanggal_selesai_investigasi']); ?></dd>
          </dl>
          <?php if (! empty($row['tanda_tangan'])): ?>
            <div class="border rounded p-2 mt-2 bg-light d-inline-block">
              <img src="<?php echo base_url('uploads/ttd/' . rawurlencode($row['tanda_tangan'])); ?>"
                   alt="Tanda tangan" style="max-height:70px;" />
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Keputusan Komite PMKP -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Keputusan Komite PMKP</h6></div>
        <div class="card-body">
          <?php if ($boleh_pmkp): ?>
            <?php echo form_open('investigasi/pmkp/' . $id); ?>
              <div class="mb-2">
                <label class="form-label" for="investigasi_lanjut">Investigasi Lanjut</label>
                <select class="form-select" id="investigasi_lanjut" name="investigasi_lanjut">
                  <option value="0" <?php echo (int) $row['investigasi_lanjut'] === 0 ? 'selected' : ''; ?>>Tidak</option>
                  <option value="1" <?php echo (int) $row['investigasi_lanjut'] === 1 ? 'selected' : ''; ?>>Ya</option>
                </select>
              </div>
              <div class="mb-2">
                <label class="form-label" for="tanggal_keputusan_pmkp">Tanggal Keputusan</label>
                <input type="date" class="form-control" id="tanggal_keputusan_pmkp"
                       name="tanggal_keputusan_pmkp"
                       value="<?php echo nilai_aman($row['tanggal_keputusan_pmkp']); ?>" />
              </div>
              <div class="mb-2">
                <label class="form-label" for="grading_ulang">Grading Ulang</label>
                <select class="form-select" id="grading_ulang" name="grading_ulang">
                  <option value="">-- Tidak ada --</option>
                  <?php foreach ($band_list as $b): ?>
                    <option value="<?php echo nilai_aman($b['kode']); ?>"
                      <?php echo $row['grading_ulang'] === $b['kode'] ? 'selected' : ''; ?>>
                      <?php echo nilai_aman($b['kode']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-2">
                <label class="form-label" for="catatan_pmkp">Catatan</label>
                <textarea class="form-control" id="catatan_pmkp" name="catatan_pmkp" rows="3"
                          maxlength="2000"><?php echo nilai_aman($row['catatan_pmkp']); ?></textarea>
              </div>
              <button type="submit" class="btn btn-primary btn-sm">
                <i class="bx bx-save"></i> Simpan Keputusan
              </button>
            <?php echo form_close(); ?>
          <?php else: ?>
            <dl class="row mb-0 small">
              <dt class="col-6">Investigasi Lanjut</dt>
              <dd class="col-6">
                <?php if ($row['investigasi_lanjut'] === NULL) { echo '-'; }
                      else { echo ((int) $row['investigasi_lanjut'] === 1) ? 'Ya' : 'Tidak'; } ?>
              </dd>
              <dt class="col-6">Tanggal Keputusan</dt><dd class="col-6"><?php echo waktu($row['tanggal_keputusan_pmkp']); ?></dd>
              <dt class="col-6">Grading Ulang</dt><dd class="col-6"><?php echo nilai_aman($row['grading_ulang']); ?></dd>
              <dt class="col-6">Catatan</dt>
              <dd class="col-6"><?php echo nl2br(nilai_aman($row['catatan_pmkp'])); ?></dd>
            </dl>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($user['role'] === 'admin'): ?>
        <div class="card">
          <div class="card-body">
            <?php echo form_open('investigasi/hapus/' . $id, array('class' => 'd-inline')); ?>
              <button type="submit" class="btn btn-outline-danger w-100"
                      onclick="return confirm('Arsipkan lembar investigasi <?php echo nilai_aman($row['nomor']); ?>?')">
                <i class="bx bx-archive"></i> Arsipkan
              </button>
            <?php echo form_close(); ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>
