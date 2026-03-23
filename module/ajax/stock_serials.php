<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    ajax/stock_serials.php
 * \ingroup warrantysvc
 * \brief   Returns JSON list of serial numbers currently in stock for a product.
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

// Query llx_product_batch (per-lot/serial stock levels per warehouse) joined to
// llx_product_stock (warehouse→product link). Returns serials with qty > 0.
$sql  = "SELECT DISTINCT pb.batch AS serial_number";
$sql .= " FROM ".MAIN_DB_PREFIX."product_batch pb";
$sql .= " JOIN ".MAIN_DB_PREFIX."product_stock ps ON ps.rowid = pb.fk_product_stock";
$sql .= " WHERE ps.fk_product = ".$fk_product;
$sql .= " AND pb.qty > 0";
$sql .= " AND pb.batch IS NOT NULL AND pb.batch != ''";
if ($fk_warehouse > 0) {
	$sql .= " AND ps.fk_entrepot = ".$fk_warehouse;
}
$sql .= " ORDER BY pb.batch ASC";

$serials = array();
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$serials[] = $obj->serial_number;
	}
}

header('Content-Type: application/json');
print json_encode($serials);
