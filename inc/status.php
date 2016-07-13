<?php
/* Author: Romain Dal Maso <artefact2@gmail.com>
 *
 * This program is free software. It comes without any warranty, to the
 * extent permitted by applicable law. You can redistribute it and/or
 * modify it under the terms of the Do What The Fuck You Want To Public
 * License, Version 2, as published by Sam Hocevar. See
 * http://sam.zoy.org/wtfpl/COPYING for more details. */

function status(array &$pf, $date = 'now') {
	static $fmt = [
		'Tkr' => [ '%5s' ],
		'%Wgt' => [ '%6s', '%6.2f' ],
		'Price' => [ '%9s', '%9.2f' ],
		'Quantity' => [ '%12s', '%12.4f' ],
		'Money In' => [ '%12s', '%12.2f' ],
		'Realized' => [ '%12s', '%12.2f' ],
		'Unrealized' => [ '%12s', '%12.2f' ],
	];

	print_header($fmt);
	print_sep($fmt);

	$totals = [];
	$agg = aggregate_tx($pf, [
		'before' => $date,
	], $totals);

	$totals['value'] = 0.0;
	$totals['unrealized'] = 0.0;

	foreach($agg as $tkr => &$a) {
		if(!$a['qty']) {
			$a['value'] = 0;
			continue;
		}
		
		$a['price'] = get_quote($pf, $tkr, $date);
		$totals['value'] += $a['value'] = $a['price'] * $a['qty'];
		$totals['unrealized'] += $a['unrealized'] = $a['value'] - $a['in'] + $a['out'];
	}
	unset($a);

	uasort($agg, function($a, $b) {
		if($a['qty'] && $b['qty']) return $b['value'] <=> $a['value'];
		if($a['qty'] && !$b['qty']) return -1;
		if(!$a['qty'] && $b['qty']) return 1;
		return $b['realized'] <=> $a['realized'];
	});
	
	foreach($agg as $tkr => $a) {
		if(!$a['qty']) {
			print_row($fmt, [
				'Tkr' => $tkr,
				'Realized' => colorize_percentage(0, '%12.2f', null, null, null, null, $a['realized']),
			]);
			continue;
		}
		
		print_row($fmt, [
			'Tkr' => $tkr,
			'%Wgt' => 100.0 * $a['value'] / $totals['value'],
			'Price' => $a['price'],
			'Quantity' => $a['qty'],
			'Money In' => $a['in'] - $a['out'],
			'Realized' => colorize_percentage(
				100.0 * $a['realized'] / $a['value'], '%12.2f',
				null, null, null, null, $a['realized']
			),
			'Unrealized' => colorize_percentage(
				100.0 * $a['unrealized'] / $a['value'], '%12.2f',
				null, null, null, null, $a['unrealized']
			),
		]);
	}

    print_sep($fmt);

	print_row($fmt, [
		'Tkr' => 'TOT',
		'Money In' => $totals['in'] - $totals['out'],
		'Realized' => colorize_percentage(
			100.0 * $totals['realized'] / $totals['in'], '%12.2f',
			null, null, null, null, $totals['realized']
		),
		'Unrealized' => colorize_percentage(
			100.0 * $totals['unrealized'] / $totals['in'], '%12.2f',
			null, null, null, null, $totals['unrealized']
		),
	]);
}

function perf(array &$pf, $date = 'now', $columns = 'default') {
	$ts = maybe_strtotime($date);
    
	$fmt = [
		'Ticker' => [ '%8s' ],
	];

	switch($columns) {
		
	case 'default':
		$startday = strtotime('yesterday', $ts);
		$periods[] = [
			'Day', $startday, $ts, '%7.2f', '%7s'
		];
		
		$periods[] = [
			'WtD', strtotime('last sunday', $ts), $ts, '%5.1f', '%5s'
		];

		$startmonth = strtotime('last day of last month', $ts);
		$periods[] = [
			'MtD', $startmonth, $ts, '%5.1f', '%5s'
		];

		$startyear = strtotime('-1 year', strtotime(date('Y-12-31', $ts)));
		$periods[] = [
			'YtD', $startyear, $ts, '%5.1f', '%5s'
		];

		for($i = 0; $i < 3; ++$i) {
			$prevmonth = strtotime('last day of last month', $startmonth);
			$periods[] = [
				date('M', $startmonth), $prevmonth, $startmonth, '%5.1f', '%5s'
			];
			$startmonth = $prevmonth;
		}

		for($i = 0; $i < 3; ++$i) {
			$prevyear = strtotime('-1 year', $startyear);
			$periods[] = [
				date('Y', $startyear), $prevyear, $startyear, '%5.1f', '%5s'
			];
			$startyear = $prevyear;
		}
		break;

	case 'days':
		$start = strtotime('yesterday', $ts);
		$periods[] = [
			date('W-N', $ts), $start, $ts, '%7.2f', '%7s'
		];

		for($i = 0; $i < 9; ++$i) {
			$prev = strtotime('yesterday', $start);
			if(in_array(date('N', $start), [ '6', '7' ], true)) {
				--$i;
			} else {
				$periods[] = [
					date('W-N', $start), $prev, $start, '%5.1f', '%5s'
				];
			}
			$start = $prev;
		}
		break;

	case 'weeks':
		$start = strtotime(date('Y-m-d', strtotime('last Sunday', $ts)));
		$periods[] = [
			'WtD', $start, $ts, '%7.2f', '%7s'
		];

		for($i = 0; $i < 9; ++$i) {
			$prev = strtotime('last Sunday', $start);
			$periods[] = [
				date('\WW', $start), $prev, $start, '%5.1f', '%5s'
			];
			$start = $prev;
		}
		break;

	case 'months':
		$startmonth = strtotime('last day of last month', $ts);
		$periods[] = [
			'MtD', $startmonth, $ts, '%7.2f', '%7s'
		];

		for($i = 0; $i < 9; ++$i) {
			$prevmonth = strtotime('last day of last month', $startmonth);
			$periods[] = [
				date('M', $startmonth), $prevmonth, $startmonth, '%5.1f', '%5s'
			];
			$startmonth = $prevmonth;
		}
		break;

	case 'years':
		$startyear = strtotime('-1 year', strtotime(date('Y-12-31', $ts)));
		$periods[] = [
			'YtD', $startyear, $ts, '%7.2f', '%7s'
		];

		for($i = 0; $i < 9; ++$i) {
			$prevyear = strtotime('-1 year', $startyear);
			$periods[] = [
				date('Y', $prevyear), $prevyear, $startyear, '%5.1f', '%5s'
			];
			$startyear = $prevyear;
		}
		break;

	default:
		fatal("perf(): unknown column type %s\n", $columns);
		break;
		
	}

	foreach($periods as $p) {			
		$fmt[$p[0]] = [
			$p[4], $p[4]
		];
	}

	print_header($fmt);
	print_sep($fmt);

	$ftable = [];
	$ftotal = [ 'Ticker' => 'TOT' ];
	$sortdata = [];

	foreach($periods as $i => $p) {
		list($k, $start, $end) = $p;

		$agg = [];
		/* XXX, for obvious reasons */
		foreach(iterate_tx($pf, $start, $end, '+1000 years') as $a) {
			$agg[] = $a;
		}
		assert(count($agg) === 2);
		list($astart, $aend) = $agg;

		$ts = 0.0;
		$te = 0.0;
		$tg = 0.0;

		foreach($aend['agg'] as $tkr => $enda) {
			$starta = $astart['agg'][$tkr] ?? [ 'in' => 0.0, 'out' => 0.0, 'qty' => 0.0, 'realized' => 0.0 ];
			$delta = $aend['delta'][$tkr] ?? [ 'in' => 0.0, 'out' => 0.0, 'qty' => 0.0, 'realized' => 0.0 ];

			$endval = $enda['qty'] ? get_quote($pf, $tkr, $end) * $enda['qty'] : 0.0;
			$startval = $starta['qty'] ? get_quote($pf, $tkr, $start) * $starta['qty'] : 0.0;
			
			if($i === 0) {
				$sortdata[$tkr][0] = $endval;
				$sortdata[$tkr][1] = $enda['realized'];
			}

			$startval += $delta['in'];
			$endval += $delta['out'];

			$ts += $startval;
			$te += $endval;
			$tg += $delta['realized'];

			/* XXX: probably a bad idea to === floats */
			if($startval === 0.0 || $endval === $startval) continue;
			
			$ftable[$tkr][$k] = colorize_percentage(
				100.0 * ($endval - $startval + $delta['realized']) / $startval,
				$i === 0 ? '%7.2f' : '%5.1f'
			);
		}

		/* XXX: same here */
		if($ts === 0.0 || $te === $ts) continue;
		$ftotal[$k] = colorize_percentage(
			100.0 * ($te - $ts + $tg) / $ts,
			$i === 0 ? '%7.2f' : '%5.1f'
		);
	}

	uksort($ftable, function($ka, $kb) use(&$sortdata) {
			if(!isset($sortdata[$ka])) $sortdata[$ka] = [ 0.0, 0.0 ];
			if(!isset($sortdata[$kb])) $sortdata[$kb] = [ 0.0, 0.0 ];
			$a = $sortdata[$ka];
			$b = $sortdata[$kb];

			if($a[0] && $b[0]) return $b[0] <=> $a[0];
			if($a[0] && !$b[0]) return -1;
			if(!$a[0] && $b[0]) return 1;
			return $b[1] <=> $a[1];
		});
	
	foreach($ftable as $ticker => $row) {
		$row['Ticker'] = $ticker;
		print_row($fmt, $row);
	}

	print_sep($fmt);
	print_row($fmt, $ftotal);
}
