<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Form Laporan Insiden - 14 bagian.
 * Acuan: vault 05 - Spesifikasi Field Form.md
 *
 * @var array|null $row
 * @var bool      $is_edit
 * @var string    $halaman
 * @var array     $ruangan_list, jenis_list, akibat_list, spesialisasi_list,
 *                umur_list, penanggung_list, pelapor_list, perawatan_list,
 *                tim_list, band_list, status_list
 * @var array     $tindakan
 * @var array     $user
 */
$r = is_array($row) ? $row : array();

function v(array $r, $k, $d = NULL)
{
    return isset($r[$k]) && $r[$k] !== '' ? $r[$k] : $d;
}

$aksi = $is_edit
    ? base_url('laporan/update/' . (int) $r['id'])
    : base_url('laporan/store');

$u = $user;
$ruangOtomatis = ($u['role'] === 'pelapor' OR $u['role'] === 'kepala_ruangan')
    && ! empty($u['ruangan_id']) && (int) $u['ruangan_id'] > 0;

$tindo = isset($tindakan) && is_array($tindakan) && ! empty($tindakan) ? $tindakan : NULL;
?>

<?php echo form_open($aksi, array('class' => 'row g-3', 'id' => 'form-laporan')); ?>
  <?php if ($is_edit): ?>
    <input type="hidden" name="id_laporan" value="<?php echo (int) $r['id']; ?>" />
    <div class="col-12">
      <div class="alert alert-info d-flex justify-content-between align-items-center mb-0">
        <div>
          <i class="bx bx-file-blank"></i>
          Nomor <strong><?php echo nilai_aman($r['nomor']); ?></strong>
          &rarr; status <strong><?php echo nilai_aman($r['status_nama']); ?></strong>
        </div>
        <a href="<?php echo base_url('laporan/detail/' . (int) $r['id']); ?>" class="btn btn-sm btn-outline-primary">
          Lihat Detail
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="col-12">
      <div class="alert alert-secondary mb-0">
        <i class="bx bx-info-circle"></i>
        Laporan disimpan sebagai <strong>Draft</strong> dulu supaya bisa diperbaiki.
        Klik <strong>Kirim Laporan</strong> di halaman detail setelah lengkap.
      </div>
    </div>
  <?php endif; ?>

  <!-- ============ BAGIAN 7: DATA PASIEN ============ -->
  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0"><span class="badge bg-label-primary me-2">7</span>Data Pasien</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-7">
            <label class="form-label" for="nama_pasien">Nama Pasien <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="nama_pasien" name="nama_pasien" required
                   maxlength="150" value="<?php echo nilai_aman(v($r, 'nama_pasien', '')); ?>" />
          </div>
          <div class="col-md-5">
            <label class="form-label" for="nomor_mr">Nomor MR <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="nomor_mr" name="nomor_mr" required
                   maxlength="50" value="<?php echo nilai_aman(v($r, 'nomor_mr', '')); ?>" />
            <div class="form-text">Satu nomor MR bisa punya beberapa laporan.</div>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="ruangan_id">Ruangan <span class="text-danger">*</span></label>
            <select class="form-select" id="ruangan_id" name="ruangan_id" required
                    <?php echo $ruangOtomatis ? 'disabled' : ''; ?>>
              <?php if (! $ruangOtomatis): ?>
                <option value="">-- Pilih Ruangan --</option>
              <?php endif; ?>
              <?php echo pilihan($ruangan_list, v($r, 'ruangan_id', $ruangOtomatis ? $u['ruangan_id'] : NULL)); ?>
            </select>
            <?php if ($ruangOtomatis): ?>
              <input type="hidden" name="ruangan_id" value="<?php echo (int) $u['ruangan_id']; ?>" />
              <div class="form-text">Terisi otomatis dari akun Anda.</div>
            <?php endif; ?>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="kelompok_umur_id">Kelompok Umur</label>
            <select class="form-select" id="kelompok_umur_id" name="kelompok_umur_id">
              <option value="">-- Tidak diisi --</option>
              <?php echo pilihan($umur_list, v($r, 'kelompok_umur_id')); ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Jenis Kelamin</label>
            <div class="pt-2">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_l" value="L"
                  <?php echo v($r, 'jenis_kelamin') === 'L' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="jk_l">Laki-laki</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_p" value="P"
                  <?php echo v($r, 'jenis_kelamin') === 'P' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="jk_p">Perempuan</label>
              </div>
            </div>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="penanggung_jawab_id">Penanggung Jawab</label>
            <select class="form-select" id="penanggung_jawab_id" name="penanggung_jawab_id">
              <option value="">-- Tidak diisi --</option>
              <?php echo pilihan($penanggung_list, v($r, 'penanggung_jawab_id')); ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="tanggal_masuk">Tanggal Masuk RS</label>
            <input type="date" class="form-control" id="tanggal_masuk" name="tanggal_masuk"
                   value="<?php echo nilai_aman(v($r, 'tanggal_masuk', '')); ?>" />
          </div>

          <div class="col-md-4">
            <label class="form-label" for="jam_masuk">Jam Masuk RS</label>
            <input type="time" class="form-control" id="jam_masuk" name="jam_masuk"
                   value="<?php echo nilai_aman(v($r, 'jam_masuk', '')); ?>" />
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ BAGIAN 8: RINCIAN KEJADIAN ============ -->
  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0"><span class="badge bg-label-primary me-2">8</span>Rincian Kejadian</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label" for="tanggal_insiden">Tanggal Insiden <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="tanggal_insiden" name="tanggal_insiden" required
                   max="<?php echo date('Y-m-d'); ?>"
                   value="<?php echo nilai_aman(v($r, 'tanggal_insiden', '')); ?>" />
          </div>
          <div class="col-md-3">
            <label class="form-label" for="jam_insiden">Jam Insiden <span class="text-danger">*</span></label>
            <input type="time" class="form-control" id="jam_insiden" name="jam_insiden" required
                   value="<?php echo nilai_aman(v($r, 'jam_insiden', '')); ?>" />
          </div>
          <div class="col-md-6">
            <label class="form-label" for="tempat_insiden">Tempat Insiden</label>
            <input type="text" class="form-control" id="tempat_insiden" name="tempat_insiden"
                   maxlength="200" placeholder="cth. Kamar 3, dekat jendela"
                   value="<?php echo nilai_aman(v($r, 'tempat_insiden', '')); ?>" />
          </div>

          <div class="col-12">
            <label class="form-label" for="insiden">Ringkasan Insiden</label>
            <textarea class="form-control" id="insiden" name="insiden" rows="2" maxlength="2000"
                      placeholder="Penjelasan singkat kejadian"><?php echo nilai_aman(v($r, 'insiden', '')); ?></textarea>
          </div>

          <div class="col-12">
            <label class="form-label" for="kronologi_insiden">Kronologi Insiden <span class="text-danger">*</span></label>
            <textarea class="form-control" id="kronologi_insiden" name="kronologi_insiden" rows="5"
                      required maxlength="5000"
                      placeholder="Urutkan kejadian dari awal hingga akhir, sertakan jam bila ada."><?php echo nilai_aman(v($r, 'kronologi_insiden', '')); ?></textarea>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ BAGIAN 9 & 16: KLASIFIKASI ============ -->
  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0"><span class="badge bg-label-primary me-2">9</span>Klasifikasi Insiden</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Jenis Insiden <span class="text-danger">*</span></label>
            <?php foreach ($jenis_list as $j): ?>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="jenis_insiden_id"
                       id="ji<?php echo (int) $j['id']; ?>" value="<?php echo (int) $j['id']; ?>"
                       <?php echo (int) v($r, 'jenis_insiden_id', 0) === (int) $j['id'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="ji<?php echo (int) $j['id']; ?>">
                  <code><?php echo nilai_aman($j['kode']); ?></code>
                  <?php echo nilai_aman($j['nama']); ?>
                </label>
              </div>
            <?php endforeach; ?>
            <div class="form-text">Pilih satu saja.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label">Akibat Insiden <span class="text-danger">*</span></label>
            <?php foreach ($akibat_list as $a): ?>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="akibat_insiden_id"
                       id="ak<?php echo (int) $a['id']; ?>" value="<?php echo (int) $a['id']; ?>"
                       <?php echo (int) v($r, 'akibat_insiden_id', 0) === (int) $a['id'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="ak<?php echo (int) $a['id']; ?>">
                  <?php echo nilai_aman($a['nama']); ?>
                </label>
              </div>
            <?php endforeach; ?>
            <div class="form-text">Grading risiko ditentukan terpisah oleh atasan.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ BAGIAN 10-15: PELAPOR & LOKASI ============ -->
  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0"><span class="badge bg-label-primary me-2">10-15</span>Pelapor dan Lokasi</h5></div>
      <div class="card-body">
        <div class="row g-3">

          <div class="col-md-6">
            <label class="form-label" for="first_reporter_id">Orang Pertama yang Melaporkan Insiden</label>
            <select class="form-select" id="first_reporter_id" name="first_reporter_id"
                    data-lainnya="first_reporter_id_lainnya" data-lainnya-aksi="tampilkan">
              <option value="">-- Tidak diisi --</option>
              <?php foreach ($pelapor_list as $p): ?>
                <option value="<?php echo (int) $p['id']; ?>"
                  <?php echo (int) v($r, 'first_reporter_id', 0) === (int) $p['id'] ? 'selected' : ''; ?>>
                  <?php echo nilai_aman($p['nama']); ?>
                </option>
              <?php endforeach; ?>
              <option value="99" <?php echo (int) v($r, 'first_reporter_id', 0) === 99 ? 'selected' : ''; ?>>Lain-lain</option>
            </select>
            <input type="text" class="form-control mt-2 d-none" id="first_reporter_id_lainnya"
                   name="first_reporter_id_lainnya" maxlength="150" placeholder="Sebutkan"
                   value="<?php echo nilai_aman(v($r, 'first_reporter_lainnya', '')); ?>" />
            <div class="form-text">Ini field formulir, bukan user yang sedang login.</div>
          </div>

          <div class="col-md-6">
            <label class="form-label" for="insiden_pada">Insiden Terjadi Pada</label>
            <select class="form-select" id="insiden_pada" name="insiden_pada"
                    data-lainnya="insiden_pada_lainnya" data-lainnya-aksi="tampilkan">
              <option value="">-- Tidak diisi --</option>
              <option value="PASIEN" <?php echo v($r, 'insiden_pada') === 'PASIEN' ? 'selected' : ''; ?>>Pasien</option>
              <option value="LAIN"  <?php echo v($r, 'insiden_pada') === 'LAIN' ? 'selected' : ''; ?>>Lain-lain</option>
            </select>
            <input type="text" class="form-control mt-2 d-none" id="insiden_pada_lainnya"
                   name="insiden_pada_lainnya" maxlength="150" placeholder="Sebutkan"
                   value="<?php echo nilai_aman(v($r, 'insiden_pada_lainnya', '')); ?>" />
          </div>

          <div class="col-md-6">
            <label class="form-label" for="care_type_id">Insiden Menyangkut Pasien</label>
            <select class="form-select" id="care_type_id" name="care_type_id"
                    data-lainnya="care_type_lainnya" data-lainnya-aksi="tampilkan">
              <option value="">-- Tidak diisi --</option>
              <?php foreach ($perawatan_list as $c): ?>
                <option value="<?php echo (int) $c['id']; ?>"
                  <?php echo (int) v($r, 'care_type_id', 0) === (int) $c['id'] ? 'selected' : ''; ?>>
                  <?php echo nilai_aman($c['nama']); ?>
                </option>
              <?php endforeach; ?>
              <option value="99" <?php echo (int) v($r, 'care_type_id', 0) === 99 ? 'selected' : ''; ?>>Lain-lain</option>
            </select>
            <input type="text" class="form-control mt-2 d-none" id="care_type_lainnya"
                   name="care_type_lainnya" maxlength="150" placeholder="Sebutkan"
                   value="<?php echo nilai_aman(v($r, 'care_type_lainnya', '')); ?>" />
          </div>

          <div class="col-md-6">
            <label class="form-label" for="spesialisasi_id">Spesialisasi / Kasus Penyakit</label>
            <select class="form-select" id="spesialisasi_id" name="spesialisasi_id">
              <option value="">-- Tidak diisi --</option>
              <?php echo pilihan($spesialisasi_list, v($r, 'specialization_id')); ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label" for="unit_terkait_id">Unit / Departemen Terkait</label>
            <select class="form-select" id="unit_terkait_id" name="unit_terkait_id">
              <option value="">-- Tidak diisi --</option>
              <?php echo pilihan($ruangan_list, v($r, 'unit_terkait_id')); ?>
            </select>
            <div class="form-text">Unit yang menyebabkan insiden, bukan tempat kejadian.</div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- ============ BAGIAN 17-19: TINDAKAN ============ -->
  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0"><span class="badge bg-label-primary me-2">17-19</span>Tindakan Setelah Kejadian</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="tindakan_segera">Tindakan Segera</label>
            <textarea class="form-control" id="tindakan_segera" name="tindakan_segera" rows="3" maxlength="5000"><?php echo nilai_aman(v($r, 'tindakan_segera', '')); ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="hasil_tindakan">Hasil Tindakan</label>
            <textarea class="form-control" id="hasil_tindakan" name="hasil_tindakan" rows="3" maxlength="5000"><?php echo nilai_aman(v($r, 'hasil_tindakan', '')); ?></textarea>
          </div>

          <div class="col-md-4">
            <label class="form-label" for="action_team_id">Tim yang Melakukan Tindakan</label>
            <select class="form-select" id="action_team_id" name="action_team_id"
                    data-lainnya="action_team_lainnya" data-lainnya-aksi="tampilkan">
              <option value="">-- Tidak diisi --</option>
              <?php foreach ($tim_list as $t): ?>
                <option value="<?php echo (int) $t['id']; ?>"
                  <?php echo (int) v($r, 'action_team_id', 0) === (int) $t['id'] ? 'selected' : ''; ?>>
                  <?php echo nilai_aman($t['nama']); ?>
                </option>
              <?php endforeach; ?>
              <option value="99" <?php echo (int) v($r, 'action_team_id', 0) === 99 ? 'selected' : ''; ?>>Petugas lainnya</option>
            </select>
            <input type="text" class="form-control mt-2 d-none" id="action_team_lainnya"
                   name="action_team_lainnya" maxlength="150" placeholder="Sebutkan"
                   value="<?php echo nilai_aman(v($r, 'action_team_lainnya', '')); ?>" />
          </div>

          <div class="col-md-8">
            <label class="form-label" for="pernah_terjadi_di_unit_lain">
              Apakah kejadian yang sama pernah terjadi di unit kerja lain?
            </label>
            <div class="pt-2">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="pernah_terjadi_di_unit_lain"
                       id="ptd_ya" value="1"
                  <?php echo (int) v($r, 'pernah_terjadi_di_unit_lain', 0) === 1 ? 'checked' : ''; ?>
                       data-sembunyikan="tindakan_pencegahan_sebelumnya">
                <label class="form-check-label" for="ptd_ya">Ya</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="pernah_terjadi_di_unit_lain"
                       id="ptd_tidak" value="0"
                  <?php echo (int) v($r, 'pernah_terjadi_di_unit_lain', 0) === 0 ? 'checked' : ''; ?>
                       data-sembunyikan="tindakan_pencegahan_sebelumnya">
                <label class="form-check-label" for="ptd_tidak">Tidak</label>
              </div>
            </div>
          </div>

          <div class="col-12 d-none" id="tindakan_pencegahan_sebelumnya-wrap">
            <label class="form-label" for="tindakan_pencegahan_sebelumnya">
              Kapan dan langkah apa yang telah diambil pada unit kerja tersebut?
            </label>
            <textarea class="form-control" id="tindakan_pencegahan_sebelumnya"
                      name="tindakan_pencegahan_sebelumnya" rows="3" maxlength="5000"><?php echo nilai_aman(v($r, 'tindakan_pencegahan_sebelumnya', '')); ?></textarea>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ BAGIAN 20: PEMBUAT & PENERIMA ============ -->
  <div class="col-12">
    <div class="card">
      <div class="card-header"><h5 class="mb-0"><span class="badge bg-label-primary me-2">20</span>Informasi Pembuat dan Penerima Laporan</h5></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" for="nama_pembuat">Nama Pembuat Laporan</label>
            <input type="text" class="form-control" id="nama_pembuat" name="nama_pembuat" maxlength="100"
                   value="<?php echo nilai_aman(v($r, 'nama_pembuat', $u['nama'])); ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label" for="paraf_pembuat">Paraf Pembuat Laporan</label>
            <input type="text" class="form-control" id="paraf_pembuat" name="paraf_pembuat" maxlength="100"
                   value="<?php echo nilai_aman(v($r, 'paraf_pembuat', '')); ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label" for="tanggal_lapor">Tanggal Lapor</label>
            <input type="date" class="form-control" id="tanggal_lapor" name="tanggal_lapor"
                   value="<?php echo nilai_aman(v($r, 'tanggal_lapor', date('Y-m-d'))); ?>" />
          </div>

          <div class="col-md-4">
            <label class="form-label" for="nama_penerima">Nama Penerima Laporan</label>
            <input type="text" class="form-control" id="nama_penerima" name="nama_penerima" maxlength="100"
                   value="<?php echo nilai_aman(v($r, 'nama_penerima', '')); ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label" for="paraf_penerima">Paraf Penerima Laporan</label>
            <input type="text" class="form-control" id="paraf_penerima" name="paraf_penerima" maxlength="100"
                   value="<?php echo nilai_aman(v($r, 'paraf_penerima', '')); ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label" for="tanggal_terima">Tanggal Terima</label>
            <input type="date" class="form-control" id="tanggal_terima" name="tanggal_terima"
                   value="<?php echo nilai_aman(v($r, 'tanggal_terima', '')); ?>" />
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="alert alert-warning mb-0 small">
      <i class="bx bx-lock"></i>
      Dokumen ini bersifat <strong>RAHASIA, TIDAK BOLEH DIFOTOCOPY</strong>
      dan wajib dilaporkan maksimal <strong>2 x 24 jam</strong> sejak kejadian.
    </div>
  </div>

  <div class="col-12 d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Simpan</button>
    <a href="<?php echo base_url('laporan'); ?>" class="btn btn-outline-secondary">Batal</a>
  </div>

<?php echo form_close(); ?>

<script>
(function () {
	'use strict';

	// Tampilkan field _lainnya bila induknya bernilai 99 (Lain-lain)
	document.querySelectorAll('[data-lainnya]').forEach(function (sel) {
		var target = document.getElementById(sel.getAttribute('data-lainnya'));
		if (!target) { return; }

		function sinkron() {
			if (sel.value === '99') {
				target.classList.remove('d-none');
				target.required = true;
			} else {
				target.classList.add('d-none');
				target.required = false;
				target.value = '';
			}
		}
		sel.addEventListener('change', sinkron);
		sinkron();
	});

	// Tampilkan textarea pencegahan bila jawaban "Ya"
	var wrap = document.getElementById('tindakan_pencegahan_sebelumnya-wrap');
	var radios = document.querySelectorAll('[data-sembunyikan]');
	function sinkronPencegahan() {
		var ya = document.getElementById('ptd_ya');
		if (!wrap || !ya) { return; }
		if (ya.checked) {
			wrap.classList.remove('d-none');
			document.getElementById('tindakan_pencegahan_sebelumnya').required = true;
		} else {
			wrap.classList.add('d-none');
			document.getElementById('tindakan_pencegahan_sebelumnya').required = false;
		}
	}
	radios.forEach(function (r) { r.addEventListener('change', sinkronPencegahan); });
	sinkronPencegahan();
})();
</script>
