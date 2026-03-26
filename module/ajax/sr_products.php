<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    ajax/sr_products.php
 * \ingroup warrantysvc
 * \brief   Returns JSON list of products shipped to a specific customer.
 *          Used to populate the Standard mode product select on the New SR form.
 *
 * GET params:
 *   socid  (int, required) — customer (fk_soc) to scope results to
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

if (!$user->id || !$user->hasRight('warrantysvc', 'svcrequest', 'read')) {
	http_response_code(403);
	exit;
}

$socid = (int) GETPOST('socid', 'int');
if ($socid <= 0) {
	header('Content-Type: application/json');
	print '[]';
	exit;
}

// Products from validated shipments to this customer (no warranty-coverage filter — SRs are valid for any shipped unit)
$sql  = "SELECT DISTINCT p.rowid, p.ref, p.label";
$sql .= " FROM ".MAIN_DB_PREFIX."product p";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.fk_product = p.rowid";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
$sql .= " WHERE e.fk_soc = ".((int) $socid);
$sql .= " AND e.fk_statut >= 1";
$sql .= " AND e.entity IN (".getEntity('expedition').")";
$sql .= " AND p.entity IN (".getEntity('product').")";
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
