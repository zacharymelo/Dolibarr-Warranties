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

$fk_product = GETPOST('fk_product', 'int');
if ($fk_product <= 0) {
	header('Content-Type: application/json');
	print '[]';
	exit;
}

$sql  = "SELECT DISTINCT pl.batch AS serial_number";
$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet_batch edl";
$sql .= " JOIN ".MAIN_DB_PREFIX."product_lot pl ON pl.rowid = edl.fk_lot";
$sql .= " JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.rowid = edl.fk_expeditiondet";
$sql .= " WHERE ed.fk_product = ".((int) $fk_product);
$sql .= " AND pl.batch IS NOT NULL AND pl.batch != ''";
$sql .= " AND pl.batch NOT IN (";
$sql .= "   SELECT serial_number FROM ".MAIN_DB_PREFIX."svc_warranty";
$sql .= "   WHERE serial_number IS NOT NULL AND serial_number != ''";
$sql .= " )";
$sql .= " ORDER BY pl.batch ASC";

$resql = $db->query($sql);

$serials = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$serials[] = $obj->serial_number;
	}
}

header('Content-Type: application/json');
print json_encode($serials);
