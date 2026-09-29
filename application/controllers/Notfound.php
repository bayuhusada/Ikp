<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Halaman 404 dan halaman error.
 * Tidak perlu login - sengaja extends CI_Controller.
 */
class Notfound extends CI_Controller
{
    public function index()
    {
        $this->output->set_status_header(404);
        $this->_tampilkan(404, 'Halaman tidak ditemukan', 'Alamat yang Anda tuju tidak ada atau sudah dipindahkan.');
    }

    public function denied()
    {
        $this->output->set_status_header(403);
        $this->_tampilkan(403, 'Akses ditolak', 'Anda tidak memiliki izin untuk membuka halaman ini.');
    }

    public function maintenance()
    {
        $this->output->set_status_header(503);
        $this->_tampilkan(503, 'Sedang pemeliharaan', 'Sistem sedang dalam pemeliharaan. Silakan coba lagi nanti.');
    }

    private function _tampilkan($kode, $judul, $pesan)
    {
        $data = array(
            'kode'   => $kode,
            'judul'  => $judul,
            'pesan'  => $pesan,
            'base'   => $this->_base(),
        );
        $this->load->view('errors/tidak_ditemukan', $data);
    }

    private function _base()
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        return rtrim(str_replace(basename($script), '', $script), '/');
    }
}
