<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    ajax/warranty_serials.php
 * \ingroup warrantysvc
 * \brief   Returns JSON list of unassigned serials shipped to a specific customer
 *          for a specific product, along with the source sales order and shipment FKs.
 *
 * GET params:
 *   socid       (int, required) — customer to scope to
 *   fk_product  (int, required) — product to scope to
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

if (!$user->id || !$user->hasRight('warrantysvc', 'svcwarranty', 'read')) {
	http_response_code(403);
	exit;
}

$socid      = (int) GETPOST('socid', 'int');
$fk_product = (int) GETPOST('fk_product', 'int');

if ($socid <= 0 || $fk_product <= 0) {
	header('Content-Type: application/json');
	print '[]';
	exit;
}

// Unassigned serials for this customer + product, with source shipment and order
$sql  = "SELECT edb.batch AS serial_number,";
$sql .= " e.rowid AS fk_expedition, e.ref AS expedition_ref,";
$sql .= " ee.fk_source AS fk_commande, c.ref AS commande_ref";
$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet_batch edb";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.rowid = edb.fk_expeditiondet";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee";
$sql .= "   ON ee.fk_target = e.rowid AND ee.targettype = 'shipping' AND ee.sourcetype = 'commande'";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commande c ON c.rowid = ee.fk_source";
$sql .= " WHERE e.fk_soc = ".((int) $socid);
$sql .= " AND ed.fk_product = ".((int) $fk_product);
$sql .= " AND e.fk_statut >= 1";
$sql .= " AND e.entity IN (".getEntity('expedition').")";
$sql .= " AND edb.batch IS NOT NULL AND edb.batch != ''";
$sql .= " AND edb.batch NOT IN (";
$sql .= "   SELECT w.serial_number FROM ".MAIN_DB_PREFIX."svc_warranty w";
$sql .= "   WHERE w.serial_number IS NOT NULL AND w.serial_number != ''";
$sql .= "   AND w.entity IN (".getEntity('svcwarranty').")";
$sql .= " )";
$sql .= " ORDER BY e.rowid DESC, edb.batch ASC";

$resql = $db->query($sql);
$serials = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$serials[] = array(
			'serial'         => $obj->serial_number,
			'fk_expedition'  => (int) $obj->fk_expedition,
			'expedition_ref' => (string) $obj->expedition_ref,
			'fk_commande'    => $obj->fk_commande ? (int) $obj->fk_commande : 0,
			'commande_ref'   => (string) $obj->commande_ref,
		);
	}
}

header('Content-Type: application/json');
print json_encode($serials);
