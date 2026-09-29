<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array $rows
 * @var array $filter
 * @var array $aktivitas_list
 * @var int   $total, $page, $pages, $limit
 * @var array $user
 */
$q = array_filter(array(
    'search'    => $filter['search'],
    'aktivitas' => $filter['aktivitas'],
    'date_from' => $filter['date_from'],
    'date_to'   => $filter['date_to'],
    'limit'     => $limit,
));
$qs = '?' . http_build_query($q);
?>

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0">Audit Log</h4>
      <p class="text-muted mb-0">Catatan aktivitas pengguna. Tabel ini bersifat append-only.</p>
    </div>
    <div class="col text-end">
      <a href="<?php echo base_url('audit-log/export-csv') . $qs; ?>" class="btn btn-outline-secondary">
        <i class="bx bx-export"></i> Ekspor CSV
      </a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <?php echo form_open('audit-log', array('method' => 'get', 'class' => 'row g-2')); ?>
        <div class="col-md-3">
          <input type="text" class="form-control" name="search" placeholder="Cari username / aktivitas"
                 value="<?php echo htmlspecialchars($filter['search'], ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
        <div class="col-md-2">
          <select name="aktivitas" class="form-select">
            <option value="">Semua Aktivitas</option>
            <?php foreach ($aktivitas_list as $a): ?>
              <option value="<?php echo htmlspecialchars($a, ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo $filter['aktivitas'] === $a ? 'selected' : ''; ?>><?php echo htmlspecialchars($a, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($filter['date_from'], ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
        <div class="col-md-2">
          <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($filter['date_to'], ENT_QUOTES, 'UTF-8'); ?>" />
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1"><i class="bx bx-search"></i> Filter</button>
          <a href="<?php echo base_url('audit-log'); ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
      <?php echo form_close(); ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <h5 class="mb-0"><?php echo (int) $total; ?> entri</h5>
      <span class="text-muted small">Halaman <?php echo (int) $page; ?> dari <?php echo max(1, (int) $pages); ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Username</th>
            <th>Aktivitas</th>
            <th>Entitas</th>
            <th>ID</th>
            <th>Sebelum</th>
            <th>Sesudah</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Belum ada aktivitas tercatat.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="text-nowrap"><?php echo htmlspecialchars(waktu($r['waktu']), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) $r['username'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars((string) $r['aktivitas'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><small><?php echo htmlspecialchars((string) $r['entitas'], ENT_QUOTES, 'UTF-8'); ?></small></td>
              <td><small><?php echo htmlspecialchars((string) $r['entitas_id'], ENT_QUOTES, 'UTF-8'); ?></small></td>
              <td><small class="text-muted"><?php echo htmlspecialchars((string) $r['sebelum'], ENT_QUOTES, 'UTF-8'); ?></small></td>
              <td><small class="text-muted"><?php echo htmlspecialchars((string) $r['sesudah'], ENT_QUOTES, 'UTF-8'); ?></small></td>
              <td><small><?php echo htmlspecialchars((string) $r['ip'], ENT_QUOTES, 'UTF-8'); ?></small></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
