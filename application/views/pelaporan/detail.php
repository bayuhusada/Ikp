<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array  $row, tindakan, riwayat, band_list, status_list
 * @var bool   $boleh_ubah, $boleh_grading, $boleh_kirim, $boleh_hapus
 * @var array|null $investigasi
 * @var array  $user
 */
$id = (int) $row['id'];
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0">Laporan <?php echo nilai_aman($row['nomor']); ?></h4>
      <p class="text-muted mb-0">
        Status
        <span class="badge bg-label-primary"><?php echo nilai_aman($row['status_nama']); ?></span>
        <?php if (! empty($row['band_kode'])): ?>
          &middot; Band
          <span class="badge" style="background-color: <?php echo nilai_aman($row['band_warna'] ?: '#888'); ?>; color:#fff;">
            <?php echo nilai_aman($row['band_kode']); ?>
          </span>
        <?php endif; ?>
      </p>
    </div>
    <div class="col text-end">
      <div class="btn-group">
        <?php if ($boleh_ubah): ?>
          <a href="<?php echo base_url('laporan/edit/' . $id); ?>" class="btn btn-outline-primary">
            <i class="bx bx-edit-alt"></i> Edit
          </a>
        <?php endif; ?>

        <?php if ($boleh_kirim): ?>
          <?php echo form_open('laporan/kirim/' . $id, array('class' => 'd-inline')); ?>
            <button type="submit" class="btn btn-primary">
              <i class="bx bx-send"></i> Kirim Laporan
            </button>
          <?php echo form_close(); ?>
        <?php endif; ?>

        <?php if ($boleh_grading): ?>
          <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modal-grading">
            <i class="bx bx-traffic-cone"></i> Grading Risiko
          </button>
        <?php endif; ?>

        <?php if (! empty($investigasi)): ?>
          <a href="<?php echo base_url('investigasi/detail/' . (int) $investigasi['id']); ?>"
             class="btn btn-outline-success">
            <i class="bx bx-file-blank"></i> <?php echo nilai_aman($investigasi['nomor']); ?>
          </a>
        <?php elseif (! empty($row['band_kode']) && $user['role'] === 'admin'): ?>
          <a href="<?php echo base_url('investigasi/input/' . $id); ?>" class="btn btn-outline-success">
            <i class="bx bx-file-plus"></i> Buat Investigasi
          </a>
        <?php endif; ?>

        <?php if ($boleh_hapus): ?>
          <?php echo form_open('laporan/hapus/' . $id, array('class' => 'd-inline')); ?>
            <button type="submit" class="btn btn-outline-danger"
                    onclick="return confirm('Arsipkan laporan <?php echo nilai_aman($row['nomor']); ?>?')">
              <i class="bx bx-archive"></i> Arsip
            </button>
          <?php echo form_close(); ?>
        <?php endif; ?>
      </div>
      <a href="<?php echo base_url('laporan'); ?>" class="btn btn-outline-secondary mt-2">
        <i class="bx bx-arrow-back"></i> Kembali
      </a>
    </div>
  </div>

  <div class="alert alert-danger d-flex justify-content-between align-items-center">
    <span class="small">
      <i class="bx bx-lock"></i>
      <strong>RAHASIA, TIDAK BOLEH DIFOTOCOPY.</strong> Wajib lapor maksimal 2 x 24 jam.
    </span>
  </div>

  <?php
  $bagian = array(
      'Data Pasien' => array('nama_pasien', 'nomor_mr', 'ruang_nama', 'kelompok_umur_nama',
                             'jenis_kelamin', 'penanggung_jawab_nama', 'tanggal_masuk', 'jam_masuk'),
      'Rincian Kejadian' => array('tanggal_insiden', 'jam_insiden', 'tempat_insiden', 'insiden', 'kronologi_insiden'),
      'Klasifikasi' => array('jenis_kode', 'jenis_nama', 'akibat_nama', 'spesialisasi_nama', 'kategori_nama'),
      'Pelapor dan Lokasi' => array('pelapor_pertama_nama', 'first_reporter_lainnya', 'insiden_pada',
                                     'insiden_pada_lainnya', 'care_type_nama', 'care_type_lainnya', 'unit_terkait_nama'),
      'Tindakan Setelah Kejadian' => array('tindakan_segera', 'hasil_tindakan', 'tim_tindakan_nama',
                                            'action_team_lainnya', 'pernah_terjadi_di_unit_lain',
                                            'tindakan_pencegahan_sebelumnya'),
      'Pembuat dan Penerima' => array('nama_pembuat', 'paraf_pembuat', 'nama_penerima',
                                       'paraf_penerima', 'tanggal_terima', 'tanggal_lapor'),
      'Grading' => array('band_kode', 'band_nama', 'graded_by_nama', 'graded_at'),
  );
  ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <?php foreach ($bagian as $judul => $fields): ?>
        <div class="card mb-3">
          <div class="card-header"><h6 class="mb-0"><?php echo $judul; ?></h6></div>
          <div class="card-body">
            <dl class="row mb-0 small">
              <?php foreach ($fields as $f): ?>
                <?php
                  $val = isset($row[$f]) ? $row[$f] : NULL;
                  if ($val === NULL OR $val === '') { continue; }
                  $label = ucwords(str_replace('_', ' ', $f));
                ?>
                <dt class="col-sm-4"><?php echo nilai_aman($label); ?></dt>
                <dd class="col-sm-8">
                  <?php
                    if (in_array($f, array('insiden', 'kronologi_insiden', 'tindakan_segera',
                                           'hasil_tindakan', 'tindakan_pencegahan_sebelumnya'), TRUE)) {
                        echo nl2br(nilai_aman($val));
                    } elseif (strpos($f, 'tanggal') === 0 OR strpos($f, 'jam_') === 0
                              OR $f === 'graded_at') {
                        echo waktu($val);
                    } elseif ($f === 'pernah_terjadi_di_unit_lain') {
                        echo ((int) $val === 1) ? 'Ya' : 'Tidak';
                    } elseif ($f === 'jenis_kelamin') {
                        echo $val === 'L' ? 'Laki-laki' : 'Perempuan';
                    } else {
                        echo nilai_aman($val);
                    }
                  ?>
                </dd>
              <?php endforeach; ?>
            </dl>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Riwayat Perubahan</h6></div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
              <tr><th>Waktu</th><th>Aksi</th><th>Oleh</th><th>Sebelum</th><th>Sesudah</th></tr>
            </thead>
            <tbody>
            <?php if (empty($riwayat)): ?>
              <tr><td colspan="5" class="text-center text-muted py-3">Belum ada riwayat.</td></tr>
            <?php else: ?>
              <?php foreach ($riwayat as $h): ?>
                <tr>
                  <td class="text-nowrap"><small><?php echo waktu($h['waktu']); ?></small></td>
                  <td><small><?php echo nilai_aman($h['action']); ?></small></td>
                  <td><small><?php echo nilai_aman($h['user_nama']); ?></small></td>
                  <td><small class="text-muted"><?php echo nilai_aman($h['sebelum']); ?></small></td>
                  <td><small class="text-muted"><?php echo nilai_aman($h['sesudah']); ?></small></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Tindakan Setelah Kejadian (rincian)</h6></div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Tindakan</th><th>Hasil</th><th>Petugas</th></tr></thead>
            <tbody>
            <?php if (empty($tindakan)): ?>
              <tr><td colspan="3" class="text-center text-muted py-3">Belum ada rincian.</td></tr>
            <?php else: ?>
              <?php foreach ($tindakan as $t): ?>
                <tr>
                  <td><small><?php echo nl2br(nilai_aman($t['tindakan'])); ?></small></td>
                  <td><small><?php echo nl2br(nilai_aman($t['hasil'])); ?></small></td>
                  <td><small><?php echo nilai_aman($t['petugas']); ?></small></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h6 class="mb-0">Informasi Dokumen</h6></div>
        <div class="card-body small">
          <dl class="row mb-0">
            <dt class="col-5">Nomor</dt><dd class="col-7"><?php echo nilai_aman($row['nomor']); ?></dd>
            <dt class="col-5">Dibuat</dt><dd class="col-7"><?php echo waktu($row['created_at']); ?></dd>
            <dt class="col-5">Diperbarui</dt><dd class="col-7"><?php echo waktu($row['updated_at']); ?></dd>
            <dt class="col-5">ID</dt><dd class="col-7"><?php echo (int) $id; ?></dd>
          </dl>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal grading -->
  <?php if ($boleh_grading): ?>
    <div class="modal fade" id="modal-grading" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <?php echo form_open('laporan/grading/' . $id); ?>
            <div class="modal-header">
              <h5 class="modal-title">Grading Risiko</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
              <p class="small text-muted">
                Pilih band risiko. Kriteria tiap band ditampilkan sebagai panduan.
              </p>
              <?php foreach ($band_list as $b): ?>
                <div class="form-check mb-2">
                  <input class="form-check-input" type="radio" name="risk_band_id"
                         id="band<?php echo (int) $b['id']; ?>" value="<?php echo (int) $b['id']; ?>"
                         data-kriteria="<?php echo nilai_aman($b['kriteria']); ?>"
                         data-warna="<?php echo nilai_aman($b['warna_hex']); ?>"
                         data-perlu-inv="<?php echo (int) $b['perlu_investigasi_sederhana']; ?>">
                  <label class="form-check-label" for="band<?php echo (int) $b['id']; ?>">
                    <span class="badge" style="background-color: <?php echo nilai_aman($b['warna_hex']); ?>; color:#fff;">
                      <?php echo nilai_aman($b['kode']); ?>
                    </span>
                    <?php echo nilai_aman($b['nama']); ?>
                    <?php if (empty(trim((string) $b['kriteria']))): ?>
                      <span class="badge bg-label-warning">Kriteria belum diisi</span>
                    <?php endif; ?>
                  </label>
                  <div class="kriteria-box small text-muted ms-4 mt-1" style="white-space: pre-line;"></div>
                </div>
              <?php endforeach; ?>

              <div class="mt-3">
                <label class="form-label" for="catatan_grading">Catatan Grading</label>
                <textarea class="form-control" id="catatan_grading" name="catatan_grading"
                          rows="3" maxlength="1000"
                          placeholder="Alasan pemilihan band risiko (opsional)"></textarea>
              </div>

              <div class="alert alert-secondary small mt-3 mb-0" id="info-alur">
                Pilih band untuk melihat jalur selanjutnya.
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary">Simpan Grading</button>
            </div>
          <?php echo form_close(); ?>
        </div>
      </div>
    </div>

    <script>
    (function () {
      'use strict';
      var radios = document.querySelectorAll('#modal-grading input[name="risk_band_id"]');
      radios.forEach(function (r) {
        r.addEventListener('change', function () {
          document.querySelectorAll('.kriteria-box').forEach(function (b) { b.textContent = ''; });
          var box = r.closest('.form-check').querySelector('.kriteria-box');
          box.textContent = r.getAttribute('data-kriteria') || 'Kriteria belum diisi.';

          var perluInv = r.getAttribute('data-perlu-inv') === '1';
          document.getElementById('info-alur').innerHTML = perluInv
            ? 'Band ini <strong>perlu Lembar Kerja Investigasi Sederhana</strong>. Tombol "Buat Investigasi" akan muncul setelah grading disimpan.'
            : 'Band ini <strong>tidak memakai</strong> Investigasi Sederhana. Proses dilanjutkan mengikuti SOP di luar sistem ini.';
        });
      });
    })();
    </script>
  <?php endif; ?>

</div>
