<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Form Lembar Kerja Investigasi Sederhana.
 * Acuan: vault 05 - Spesifikasi Field Form.md, bagian 18-34.
 *
 * @var array|null $laporan
 * @var array|null $row
 * @var bool       $is_edit
 * @var array      $rekomendasi, $tindakan
 * @var array      $user
 */
$r = is_array($row) ? $row : array();
$lap = is_array($laporan) ? $laporan : array();

function iv(array $r, $k, $d = NULL)
{
    return isset($r[$k]) && $r[$k] !== '' ? $r[$k] : $d;
}

$idInv  = $is_edit ? (int) $r['id'] : 0;
$aksi  = $is_edit
    ? base_url('investigasi/update/' . $idInv)
    : base_url('investigasi/store/' . (int) iv($lap, 'id', 0));
?>

<?php if (! $is_edit && $lap): ?>
  <div class="container-fluid">
    <div class="alert alert-info d-flex justify-content-between align-items-center">
      <div>
        <i class="bx bx-file-blank"></i>
        Membuat lembar investigasi untuk laporan
        <strong><?php echo nilai_aman($lap['nomor']); ?></strong>
        (band <strong><?php echo nilai_aman($lap['band_kode']); ?></strong>).
      </div>
      <a href="<?php echo base_url('laporan/detail/' . (int) $lap['id']); ?>" class="btn btn-sm btn-outline-primary">
        Kembali ke Laporan
      </a>
    </div>
  </div>
<?php endif; ?>

<div class="container-fluid">
  <?php echo form_open($aksi, array('class' => 'row g-3')); ?>
    <?php if ($is_edit): ?>
      <input type="hidden" name="id_investigasi" value="<?php echo $idInv; ?>" />
    <?php endif; ?>

    <?php if ($is_edit): ?>
      <div class="col-12">
        <div class="alert alert-secondary d-flex justify-content-between align-items-center mb-0">
          <span>Nomor <strong><?php echo nilai_aman($r['nomor']); ?></strong>
            &rarr; status <strong><?php echo nilai_aman($r['status_nama']); ?></strong></span>
          <a href="<?php echo base_url('investigasi/detail/' . $idInv); ?>" class="btn btn-sm btn-outline-primary">
            Lihat Detail
          </a>
        </div>
      </div>
    <?php endif; ?>

    <!-- ===== Data Dasar (bagian 18) ===== -->
    <div class="col-12">
      <div class="card">
        <div class="card-header"><h5 class="mb-0"><span class="badge bg-label-primary me-2">18</span>Data Dasar</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="tanggal_investigasi">Tanggal Investigasi <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="tanggal_investigasi" name="tanggal_investigasi" required
                     value="<?php echo nilai_aman(iv($r, 'tanggal_investigasi', date('Y-m-d'))); ?>" />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="ruangan_id">Ruangan <span class="text-danger">*</span></label>
              <input type="text" class="form-control" disabled
                     value="<?php echo nilai_aman(iv($lap, 'ruang_nama', iv($r, 'ruang_nama', ''))); ?>" />
              <input type="hidden" name="ruangan_id" value="<?php echo (int) iv($lap, 'ruangan_id', iv($r, 'ruangan_id', 0)); ?>" />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="jenis_insiden_id">Jenis Insiden</label>
              <input type="text" class="form-control" disabled
                     value="<?php echo nilai_aman(iv($lap, 'jenis_kode', iv($r, 'jenis_kode', '')) . ' - ' . iv($lap, 'jenis_nama', iv($r, 'jenis_nama', ''))); ?>" />
              <input type="hidden" name="jenis_insiden_id" value="<?php echo (int) iv($lap, 'jenis_insiden_id', iv($r, 'jenis_insiden_id', 0)); ?>" />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="risk_band_id">Band Risiko</label>
              <input type="text" class="form-control" disabled
                     value="<?php echo nilai_aman(iv($lap, 'band_kode', iv($r, 'band_kode', ''))); ?>" />
              <input type="hidden" name="risk_band_id" value="<?php echo (int) iv($lap, 'risk_band_id', iv($r, 'risk_band_id', 0)); ?>" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Penyebab Langsung & Akar Masalah (bagian 26-27) ===== -->
    <div class="col-12">
      <div class="card">
        <div class="card-header"><h5 class="mb-0"><span class="badge bg-label-primary me-2">26-27</span>Analisis</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="penyebab_langsung">
                Penyebab Langsung Insiden <span class="text-danger">*</span>
              </label>
              <textarea class="form-control" id="penyebab_langsung" name="penyebab_langsung" rows="6" required
                        maxlength="5000"
                        placeholder="Apa yang terjadi secara langsung?"><?php echo nilai_aman(iv($r, 'penyebab_langsung', '')); ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="akar_masalah">
                Penyebab yang Melatarbelakangi / Akar Masalah <span class="text-danger">*</span>
              </label>
              <textarea class="form-control" id="akar_masalah" name="akar_masalah" rows="6" required
                        maxlength="5000"
                        placeholder="Mengapa hal itu bisa terjadi?"><?php echo nilai_aman(iv($r, 'akar_masalah', '')); ?></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Rekomendasi (bagian 28-29) ===== -->
    <div class="col-12">
      <div class="card">
        <div class="card-header"><h5 class="mb-0"><span class="badge bg-label-primary me-2">28-29</span>Rekomendasi</h5></div>
        <div class="card-body">
          <table class="table table-sm align-middle" id="tbl-rekomendasi">
            <thead>
              <tr>
                <th style="width:50%">Rekomendasi</th>
                <th style="width:25%">Penanggung Jawab</th>
                <th style="width:15%">Tanggal</th>
                <th style="width:10%"></th>
              </tr>
            </thead>
            <tbody>
            <?php if (! empty($rekomendasi)): ?>
              <?php foreach ($rekomendasi as $i => $rec): ?>
                <tr>
                  <td><input type="text" class="form-control" name="rekomendasi_rows[<?php echo $i; ?>][rekomendasi]"
                             value="<?php echo nilai_aman($rec['rekomendasi']); ?>" /></td>
                  <td><input type="text" class="form-control" name="rekomendasi_rows[<?php echo $i; ?>][penanggung_jawab]"
                             value="<?php echo nilai_aman($rec['penanggung_jawab']); ?>" /></td>
                  <td><input type="date" class="form-control" name="rekomendasi_rows[<?php echo $i; ?>][tanggal_rekomendasi]"
                             value="<?php echo nilai_aman($rec['tanggal_rekomendasi']); ?>" /></td>
                  <td>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-hapus-baris>
                      <i class="bx bx-x"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
          <button type="button" class="btn btn-sm btn-outline-primary" data-tambah="tbl-rekomendasi"
                  data-prefix="rekomendasi_rows" data-isian="rekomendasi" data-tanggal="tanggal_rekomendasi">
            <i class="bx bx-plus"></i> Tambah Baris Rekomendasi
          </button>
        </div>
      </div>
    </div>

    <!-- ===== Tindakan (bagian 30-31) ===== -->
    <div class="col-12">
      <div class="card">
        <div class="card-header"><h5 class="mb-0"><span class="badge bg-label-primary me-2">30-31</span>Tindakan yang Akan Dilakukan</h5></div>
        <div class="card-body">
          <table class="table table-sm align-middle" id="tbl-tindakan">
            <thead>
              <tr>
                <th style="width:50%">Tindakan</th>
                <th style="width:25%">Penanggung Jawab</th>
                <th style="width:15%">Tanggal</th>
                <th style="width:10%"></th>
              </tr>
            </thead>
            <tbody>
            <?php if (! empty($tindakan)): ?>
              <?php foreach ($tindakan as $i => $t): ?>
                <tr>
                  <td><input type="text" class="form-control" name="tindakan_rows[<?php echo $i; ?>][tindakan]"
                             value="<?php echo nilai_aman($t['tindakan']); ?>" /></td>
                  <td><input type="text" class="form-control" name="tindakan_rows[<?php echo $i; ?>][penanggung_jawab]"
                             value="<?php echo nilai_aman($t['penanggung_jawab']); ?>" /></td>
                  <td><input type="date" class="form-control" name="tindakan_rows[<?php echo $i; ?>][tanggal_tindakan]"
                             value="<?php echo nilai_aman($t['tanggal_tindakan']); ?>" /></td>
                  <td>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-hapus-baris>
                      <i class="bx bx-x"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
          <button type="button" class="btn btn-sm btn-outline-primary" data-tambah="tbl-tindakan"
                  data-prefix="tindakan_rows" data-isian="tindakan" data-tanggal="tanggal_tindakan">
            <i class="bx bx-plus"></i> Tambah Baris Tindakan
          </button>
        </div>
      </div>
    </div>

    <!-- ===== Persetujuan (bagian 32) ===== -->
    <div class="col-12">
      <div class="card">
        <div class="card-header"><h5 class="mb-0"><span class="badge bg-label-primary me-2">32</span>Manager / Kepala Bagian / Kepala Unit</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="nama_penyetuju">Nama Penyetuju</label>
              <input type="text" class="form-control" id="nama_penyetuju" name="nama_penyetuju" maxlength="100"
                     value="<?php echo nilai_aman(iv($r, 'nama_penyetuju', '')); ?>" />
              <div class="form-text">Tanda tangan diambil dari gambar tanda tangan milik user, bila sudah diunggah di menu Profil.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="tanggal_mulai_investigasi">Tanggal Mulai</label>
              <input type="date" class="form-control" id="tanggal_mulai_investigasi"
                     name="tanggal_mulai_investigasi"
                     value="<?php echo nilai_aman(iv($r, 'tanggal_mulai_investigasi', '')); ?>" />
            </div>
            <div class="col-md-3">
              <label class="form-label" for="tanggal_selesai_investigasi">Tanggal Selesai</label>
              <input type="date" class="form-control" id="tanggal_selesai_investigasi"
                     name="tanggal_selesai_investigasi"
                     value="<?php echo nilai_aman(iv($r, 'tanggal_selesai_investigasi', '')); ?>" />
            </div>
            <?php if (! empty($r['tanda_tangan'])): ?>
              <div class="col-12">
                <div class="border rounded p-2 bg-light d-inline-block">
                  <img src="<?php echo base_url('uploads/ttd/' . rawurlencode($r['tanda_tangan'])); ?>"
                       alt="Tanda tangan" style="max-height:80px;" />
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Simpan</button>
      <a href="<?php echo $is_edit ? base_url('investigasi/detail/' . $idInv) : base_url('investigasi'); ?>"
         class="btn btn-outline-secondary">Batal</a>
    </div>

  <?php echo form_close(); ?>
</div>

<script>
(function () {
	'use strict';

	/** Indeks berikutnya = max indeks yang ada + 1, bukan jumlah baris.
	 *  Kalau pengguna menghapus baris di tengah, jumlah baris bisa turun
	 *  dan indeks baru menabrak indeks yang masih terpakai. */
	function nextIndex(tbody, prefix) {
		var max = -1;
		var re = new RegExp('^' + prefix + '\\[(\\d+)\\]');
		tbody.querySelectorAll('input[name]').forEach(function (el) {
			var m = re.exec(el.getAttribute('name'));
			if (m && parseInt(m[1], 10) > max) {
				max = parseInt(m[1], 10);
			}
		});
		return max + 1;
	}

	function cell(className, name, type) {
		var td = document.createElement('td');
		var input = document.createElement('input');
		input.type = type;
		input.className = className;
		input.name = name;
		td.appendChild(input);
		return { td: td, input: input };
	}

	document.querySelectorAll('[data-tambah]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var tbody = document.querySelector('#' + btn.getAttribute('data-tambah') + ' tbody');
			if (!tbody) { return; }

			var prefix = btn.getAttribute('data-prefix');
			var isian  = btn.getAttribute('data-isian');
			var tgl    = btn.getAttribute('data-tanggal');
			var i      = nextIndex(tbody, prefix);

			var c1 = cell('form-control', prefix + '[' + i + '][' + isian + ']', 'text');
			var c2 = cell('form-control', prefix + '[' + i + '][penanggung_jawab]', 'text');
			var c3 = cell('form-control', prefix + '[' + i + '][' + tgl + ']', 'date');

			var td4 = document.createElement('td');
			var tr   = document.createElement('tr');
			var hapus = document.createElement('button');
			hapus.type = 'button';
			hapus.className = 'btn btn-sm btn-outline-danger';
			hapus.setAttribute('data-hapus-baris', '');
			hapus.innerHTML = '<i class="bx bx-x"></i>';
			hapus.addEventListener('click', function () { tr.remove(); });
			td4.appendChild(hapus);

			tr.appendChild(c1.td);
			tr.appendChild(c2.td);
			tr.appendChild(c3.td);
			tr.appendChild(td4);

			tbody.appendChild(tr);
			c1.input.focus();
		});
	});

	// Tombol hapus pada baris yang dirender server (mode edit)
	document.querySelectorAll('tbody [data-hapus-baris]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var tr = btn.closest('tr');
			if (tr) { tr.remove(); }
		});
	});
})();
</script>
