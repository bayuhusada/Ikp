<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Helper tampilan.
 *
 * Di dalam view CI3, $this adalah CI_Loader, bukan controller. Jadi
 * fungsi format tanggal harus menjadi helper function, bukan method controller.
 */

if (! function_exists('tgl')) {
    /**
     * Format tanggal untuk tampilan: 27/09/2026
     *
     * @param  string $nilai
     * @param  string $kosong  nilai bila input kosong
     * @return string
     */
    function tgl($nilai, $kosong = '-')
    {
        if (empty($nilai) OR $nilai === '0000-00-00' OR $nilai === '0000-00-00 00:00:00') {
            return $kosong;
        }
        $ts = strtotime($nilai);
        return $ts ? date('d/m/Y', $ts) : $kosong;
    }
}

if (! function_exists('waktu')) {
    /**
     * Format tanggal + jam untuk tampilan: 27/09/2026 10:30
     */
    function waktu($nilai, $kosong = '-')
    {
        if (empty($nilai) OR $nilai === '0000-00-00' OR $nilai === '0000-00-00 00:00:00') {
            return $kosong;
        }
        $ts = strtotime($nilai);
        return $ts ? date('d/m/Y H:i', $ts) : $kosong;
    }
}

if (! function_exists('jam')) {
    /**
     * Format jam saja: 10:30
     */
    function jam($nilai, $kosong = '-')
    {
        if (empty($nilai)) {
            return $kosong;
        }
        $ts = strtotime($nilai);
        return $ts ? date('H:i', $ts) : $kosong;
    }
}

if (! function_exists('nilai_aman')) {
    /**
     * Escape output. Dipakai di SETIAP nilai yang datang dari database
     * atau input pengguna.
     */
    function nilai_aman($nilai)
    {
        return htmlspecialchars((string) $nilai, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('badge_band')) {
    /**
     * Badge warna untuk band risiko.
     *
     * @param  string $kode  BIRU / HIJAU / KUNING / MERAH
     * @param  string $nama
     * @return string HTML
     */
    function badge_band($kode, $nama = NULL)
    {
        $peta = array(
            'BIRU'   => array('bg-label-info',    'Biru'),
            'HIJAU'  => array('bg-label-success', 'Hijau'),
            'KUNING' => array('bg-label-warning', 'Kuning'),
            'MERAH'  => array('bg-label-danger',  'Merah'),
        );
        $kode = strtoupper((string) $kode);
        if (! isset($peta[$kode])) {
            return '<span class="badge bg-label-secondary">' . nilai_aman($nama !== NULL ? $nama : $kode) . '</span>';
        }
        $teks = $nama !== NULL ? $nama : $peta[$kode][1];
        return '<span class="badge ' . $peta[$kode][0] . '">' . nilai_aman($teks) . '</span>';
    }
}

if (! function_exists('pilihan')) {
    /**
     * Bentuk <option> dari array master.
     *
     * @param array  $rows
     * @param mixed  $terpilih
     * @param string $label_key
     * @return string HTML
     */
    function pilihan(array $rows, $terpilih = NULL, $label_key = 'nama')
    {
        $out = '';
        foreach ($rows as $r) {
            $id   = isset($r['id']) ? $r['id'] : '';
            $teks = isset($r[$label_key]) ? $r[$label_key] : '';
            $sel  = ((string) $id === (string) $terpilih) ? ' selected' : '';
            $out .= '<option value="' . nilai_aman($id) . '"' . $sel . '>' . nilai_aman($teks) . '</option>';
        }
        return $out;
    }
}

if (! function_exists('fitur_ada')) {
    /**
     * Apakah fitur sudah siap dipakai?
     *
     * Status dibaca dari application/config/features.php. Dipakai di sidebar
     * supaya menu yang halamannya belum ada tidak ditampilkan.
     *
     * BUKAN penjaga akses. Controller tetap wajib require_role() sendiri;
     * menu yang disembunyikan bukan pengaman.
     *
     * Default FALSE: kalau kuncinya tidak terdaftar di config, anggap belum
     * siap. Menu yang belum terdaftar tidak boleh tampil karena tidak bisa
     * diverifikasi.
     *
     * @param  string $kunci
     * @return bool
     */
    function fitur_ada($kunci)
    {
        $fitur = config_item('fitur');

        if (! is_array($fitur) OR ! array_key_exists($kunci, $fitur)) {
            return FALSE;
        }

        return $fitur[$kunci] === 'siap';
    }
}
