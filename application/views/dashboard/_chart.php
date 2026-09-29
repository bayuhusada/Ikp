<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @var array $data
 */
$ringkasan = isset($data['ringkasan']) ? $data['ringkasan'] : array();
$mode      = isset($data['mode']) ? $data['mode'] : 'internal';
$perJenis  = isset($data['per_jenis']) ? $data['per_jenis'] : array();
$perRuang  = isset($data['per_ruangan']) ? $data['per_ruangan'] : array();
?>
<script>
(function () {
	'use strict';

	function kotak(judul, target, seri, palet) {
		var el = document.getElementById(target);
		if (!el) { return; }

		var opsi = {
			chart: { type: 'donut', height: 260 },
			series: seri,
			labels: judul,
			colors: palet,
			legend: { position: 'bottom' },
			dataLabels: { enabled: false },
			stroke: { width: 2 },
			plotOptions: {
				pie: {
					donut: { size: '70%' }
				}
			},
			legend: {
				position: 'bottom',
				formatter: function (n, opts) {
					var total = opts.w.globals.seriesTotals.reduce(function (a, b) { return a + b; }, 0);
					var v = opts.w.globals.series[opts.seriesIndex];
					var pct = total > 0 ? ((v / total) * 100).toFixed(1) : '0';
					return n + ' (' + pct + '%)';
				}
			}
		};

		if (window.ApexCharts) {
			new ApexCharts(el, opsi).render();
		}
	}

	<?php if ($mode !== 'pelapor' && ! empty($perJenis)): ?>
	kotak(
		<?php echo json_encode(array_column($perJenis, 'nama'), JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
		'chart-jenis',
		<?php echo json_encode(array_map('intval', array_column($perJenis, 'jumlah')), JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
		['#3f51b5', '#2196f3', '#4caf50', '#f44336', '#ff9800', '#9c27b0', '#607d8b', '#795548', '#9e9e9e']
	);
	<?php endif; ?>

	<?php if ($mode !== 'pelapor' && ! empty($perRuang)): ?>
	kotak(
		<?php echo json_encode(array_column($perRuang, 'nama'), JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
		'chart-ruangan',
		<?php echo json_encode(array_map('intval', array_column($perRuang, 'jumlah')), JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
		['#3f51b5', '#2196f3', '#4caf50', '#f44336', '#ff9800', '#9c27b0', '#607d8b', '#795548', '#9e9e9e', '#00bcd4']
	);
	<?php endif; ?>
})();
</script>
