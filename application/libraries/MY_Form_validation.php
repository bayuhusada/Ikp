<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Form validasi dengan rule tambahan.
 *
 * CI3 hanya menyediakan sedikit rule bawaan:
 *   required, regex_match, matches, is_numeric, is_integer, is_integer_like,
 *   is_bool, is_url, is_email, is_ip, is_float, is_array, is_json
 *
 * Rule seperti is_natural_no atau valid_date TIDAK termasuk bawaan.
 * Kalau dipakai tanpa didefinisikan di kelas ini, CI3 memperlakukannya
 * sebagai callback yang tidak ditemukan dan validasi SELALU gagal dengan
 * pesan "Unable to access an error message corresponding to your field name".
 *
 * CI3 memuat library turunan dengan pola MY_<Nama>. Dia membaca
 * system/libraries/Form_validation.php lebih dulu, lalu application/libraries/
 * MY_Form_validation.php. Karena itu prefix MY_ itu WAJIB, dan nama file
 * TIDAK boleh sama dengan nama library bawaan.
 *
 * PENTING: kelas ini HANYA untuk rule yang CI3 cari sebagai method library
 * (is_natural_no, valid_date, valid_time). Rule `callback_xxx` milik
 *_search_ yang lain TIDAK boleh ditaruh di sini. CI3 mencari method callback
 * di controller:
 *
 *     system/libraries/Form_validation.php:716
 *     if ( ! method_exists($this->CI, $rule))   <-- $this->CI = controller
 *
 * Contoh nyata yang pernah bikin frustrasi: `callback_password_check`
 * diletakkan di sini, tapi tetap tidak jalan. Sekarang ada di
 * MY_Controller::password_check().
 */
class MY_Form_validation extends CI_Form_validation
{
    /**
     * Angka bulat positif. Tanpa tanda, tanpa desimal, tanpa nol di depan
     * yang tidak perlu. Nol TIDAK diterima karena semua yang divalidasi
     * di aplikasi ini adalah foreign key.
     *
     * @param  string $str
     * @return bool
     */
    public function is_natural_no($str)
    {
        if (is_array($str) OR $str === NULL) {
            $this->set_message('is_natural_no', 'Kolom {field} harus berupa angka.');
            return FALSE;
        }
        if ($str === '' OR preg_match('/^[1-9][0-9]*$/', (string) $str) !== 1) {
            $this->set_message('is_natural_no', 'Kolom {field} harus berupa angka.');
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Angka bulat, nol diperbolehkan.
     */
    public function is_natural($str)
    {
        if (is_array($str) OR $str === NULL) {
            $this->set_message('is_natural', 'Kolom {field} harus berupa angka.');
            return FALSE;
        }
        if ($str === '' OR preg_match('/^[0-9]+$/', (string) $str) !== 1) {
            $this->set_message('is_natural', 'Kolom {field} harus berupa angka.');
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Tanggal kalender format Y-m-d.
     *
     * Sengaja tidak memakai strtotime() karena strtotime menerima
     * "besok", "2026/13/45", dan lain-lain. Yang dimaksud di sini hanya
     * tanggal yang benar-benar ada di kalender.
     */
    public function valid_date($str)
    {
        if (is_array($str) OR $str === NULL OR $str === '') {
            $this->set_message('valid_date', 'Kolom {field} harus berupa tanggal yang benar.');
            return FALSE;
        }
        $d = DateTime::createFromFormat('Y-m-d', (string) $str);
        if ($d !== FALSE && $d->format('Y-m-d') === (string) $str) {
            return TRUE;
        }
        $this->set_message('valid_date', 'Kolom {field} harus berupa tanggal yang benar.');
        return FALSE;
    }

    /**
     * Jam format H:i atau H:i:s.
     */
    public function valid_time($str)
    {
        if (is_array($str) OR $str === NULL OR $str === '') {
            $this->set_message('valid_time', 'Kolom {field} harus berupa jam yang benar.');
            return FALSE;
        }
        if (preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', (string) $str) !== 1) {
            $this->set_message('valid_time', 'Kolom {field} harus berupa jam yang benar.');
            return FALSE;
        }
        return TRUE;
    }
}
