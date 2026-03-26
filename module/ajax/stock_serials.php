<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    ajax/stock_serials.php
 * \ingroup warrantysvc
 * \brief   Returns JSON list of serial numbers currently in stock for a product,
 *          sorted by the configured replacement strategy (fifo, least_serviced,
 *          best_condition, or manual).
 *
 * GET params:
 *   fk_product   (int, required) — product to query
 *   fk_warehouse (int, optional) — limit to a specific warehouse
 *   token        (string)        — Dolibarr CSRF token
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))    { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))  { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

if (!$user->id || !$user->hasRight('warrantysvc', 'svcrequest', 'read')) {
	http_response_code(403);
	exit;
}

$fk_product   = (int) GETPOST('fk_product', 'int');
$fk_warehouse = (int) GETPOST('fk_warehouse', 'int');

if ($fk_product <= 0) {
	header('Content-Type: application/json');
	print '[]';
	exit;
}

require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcservicelog.class.php';

$strategy = getDolGlobalString('WARRANTYSVC_REPLACEMENT_STRATEGY', 'fifo');

// Use SvcServiceLog to get serials sorted by condition/service history
$results = SvcServiceLog::getAvailableSerials($db, $fk_product, $fk_warehouse, $strategy);

// Return as simple string array for datalist compatibility,
// but include condition info as data attributes via a richer format
// when the request asks for it
$detailed = GETPOST('detailed', 'int');

if ($detailed) {
	// Return full objects for rich UI rendering
	header('Content-Type: application/json');
	print json_encode($results);
} else {
	// Return simple serial list for <datalist> compatibility
	$serials = array();
	foreach ($results as $row) {
		$serials[] = $row['serial'];
	}
	header('Content-Type: application/json');
	print json_encode($serials);
}
