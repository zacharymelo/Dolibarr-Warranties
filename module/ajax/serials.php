<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    ajax/serials.php
 * \ingroup warrantysvc
 * \brief   Returns JSON list of shipped serials for a product that have no warranty yet
 *
 * GET params:
 *   fk_product  (int, required) — product to query
 *   token       (string)        — Dolibarr CSRF token
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))    { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))  { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

// Must be logged in and have read permission
if (!$user->id || !$user->hasRight('warrantysvc', 'svcrequest', 'read')) {
	http_response_code(403);
	exit;
}

$fk_product = (int) GETPOST('fk_product', 'int');
$fk_soc     = (int) GETPOST('fk_soc', 'int');
if ($fk_product <= 0) {
	header('Content-Type: application/json');
	print '[]';
	exit;
}

// mode=svcrequest: serials come from registered warranties — the correct source for SR filing.
// Override-mode warranties have manually typed serials that live only in svc_warranty,
// not in expeditiondet_batch. Scope to customer if supplied.
// default (warranty creation flow): shipped serials not yet covered by a warranty.
$mode = GETPOST('mode', 'alpha');

if ($mode === 'svcrequest') {
	// Serials from warranty records
	$sql  = "SELECT DISTINCT w.serial_number AS serial_number";
	$sql .= " FROM ".MAIN_DB_PREFIX."svc_warranty w";
	$sql .= " WHERE w.fk_product = ".$fk_product;
	$sql .= " AND w.serial_number IS NOT NULL AND w.serial_number != ''";
	$sql .= " AND w.status != 'voided'";
	if ($fk_soc > 0) {
		$sql .= " AND w.fk_soc = ".$fk_soc;
	}
	$sql .= " AND w.entity IN (".getEntity('svcwarranty').")";
	// UNION serials from validated shipments (covers units without a warranty record)
	$sql .= " UNION SELECT DISTINCT pl.batch AS serial_number";
	$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet_batch edl";
	$sql .= " JOIN ".MAIN_DB_PREFIX."product_lot pl ON pl.rowid = edl.fk_lot";
	$sql .= " JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.rowid = edl.fk_expeditiondet";
	$sql .= " JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
	$sql .= " WHERE ed.fk_product = ".$fk_product;
	$sql .= " AND e.fk_statut >= 1";
	if ($fk_soc > 0) {
		$sql .= " AND e.fk_soc = ".$fk_soc;
	}
	$sql .= " AND e.entity IN (".getEntity('expedition').")";
	$sql .= " AND pl.batch IS NOT NULL AND pl.batch != ''";
	$sql .= " ORDER BY serial_number ASC";
} else {
	$sql  = "SELECT DISTINCT pl.batch AS serial_number";
	$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet_batch edl";
	$sql .= " JOIN ".MAIN_DB_PREFIX."product_lot pl ON pl.rowid = edl.fk_lot";
	$sql .= " JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.rowid = edl.fk_expeditiondet";
	$sql .= " WHERE ed.fk_product = ".$fk_product;
	$sql .= " AND pl.batch IS NOT NULL AND pl.batch != ''";
	$sql .= " AND NOT EXISTS (";
	$sql .= "   SELECT 1 FROM ".MAIN_DB_PREFIX."svc_warranty w";
	$sql .= "   WHERE w.serial_number = pl.batch";
	$sql .= "   AND w.fk_product = ed.fk_product";
	$sql .= "   AND w.status != 'voided'";
	$sql .= "   AND w.serial_number IS NOT NULL AND w.serial_number != ''";
	$sql .= "   AND w.entity IN (".getEntity('svcwarranty').")";
	$sql .= " )";
	$sql .= " ORDER BY pl.batch ASC";
}

$resql = $db->query($sql);

$serials = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$serials[] = $obj->serial_number;
	}
}

header('Content-Type: application/json');
print json_encode($serials);
