<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    ajax/warranty_products.php
 * \ingroup warrantysvc
 * \brief   Returns JSON list of products with unassigned serials that were
 *          shipped to a specific customer. Used to populate the Standard mode
 *          product select on the New Warranty form.
 *
 * GET params:
 *   socid  (int, required) — customer (fk_soc) to scope results to
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

if (!$user->id || !$user->hasRight('warrantysvc', 'svcwarranty', 'read')) {
	http_response_code(403);
	exit;
}

$socid = (int) GETPOST('socid', 'int');
if ($socid <= 0) {
	header('Content-Type: application/json');
	print '[]';
	exit;
}

// Products shipped to this customer that still have at least one unassigned serial
// (serial exists in product_lot but not yet in svc_warranty)
// Note: batch/serial is in product_lot.batch, referenced via expeditiondet_batch.fk_lot
$sql  = "SELECT DISTINCT p.rowid, p.ref, p.label";
$sql .= " FROM ".MAIN_DB_PREFIX."product p";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.fk_product = p.rowid";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expeditiondet_batch edb ON edb.fk_expeditiondet = ed.rowid";
$sql .= " WHERE e.fk_soc = ".((int) $socid);
$sql .= " AND e.fk_statut >= 1";
$sql .= " AND e.entity IN (".getEntity('expedition').")";
$sql .= " AND p.entity IN (".getEntity('product').")";
$sql .= " AND edb.batch IS NOT NULL AND edb.batch != ''";
$sql .= " AND NOT EXISTS (";
$sql .= "   SELECT 1 FROM ".MAIN_DB_PREFIX."svc_warranty w";
$sql .= "   WHERE w.serial_number = edb.batch";
$sql .= "   AND w.fk_product = p.rowid";
$sql .= "   AND w.status != 'voided'";
$sql .= "   AND w.serial_number IS NOT NULL AND w.serial_number != ''";
$sql .= "   AND w.entity IN (".getEntity('svcwarranty').")";
$sql .= " )";
$sql .= " ORDER BY p.ref ASC";

$resql = $db->query($sql);
$products = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$products[] = array(
			'rowid' => (int) $obj->rowid,
			'label' => $obj->ref.($obj->label ? ' — '.$obj->label : ''),
		);
	}
}

header('Content-Type: application/json');
print json_encode($products);
