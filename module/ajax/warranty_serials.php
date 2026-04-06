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
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
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

// Unassigned serials for this customer + product, with source shipment and order.
// Serial string is stored directly on expeditiondet_batch.batch
$sql  = "SELECT edb.batch AS serial_number,";
$sql .= " e.rowid AS fk_expedition, e.ref AS expedition_ref,";
$sql .= " e.date_expedition, e.date_delivery,";
$sql .= " c.date_commande, f.datef AS date_invoice,";
$sql .= " ee.fk_source AS fk_commande, c.ref AS commande_ref";
$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet_batch edb";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.rowid = edb.fk_expeditiondet";
$sql .= " INNER JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_element ee";
$sql .= "   ON ee.fk_target = e.rowid AND ee.targettype = 'shipping' AND ee.sourcetype = 'commande'";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."commande c ON c.rowid = ee.fk_source";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_element eef";
$sql .= "   ON eef.fk_source = c.rowid AND eef.sourcetype = 'commande' AND eef.targettype = 'facture'";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = eef.fk_target";
$sql .= " WHERE e.fk_soc = ".((int) $socid);
$sql .= " AND ed.fk_product = ".((int) $fk_product);
$sql .= " AND e.fk_statut >= 1";
$sql .= " AND e.entity IN (".getEntity('expedition').")";
$sql .= " AND edb.batch IS NOT NULL AND edb.batch != ''";
$sql .= " AND NOT EXISTS (";
$sql .= "   SELECT 1 FROM ".MAIN_DB_PREFIX."svc_warranty w";
$sql .= "   WHERE w.serial_number = edb.batch";
$sql .= "   AND w.fk_product = ed.fk_product";
$sql .= "   AND w.status != 'voided'";
$sql .= "   AND w.serial_number IS NOT NULL AND w.serial_number != ''";
$sql .= "   AND w.entity IN (".getEntity('svcwarranty').")";
$sql .= " )";
$sql .= " ORDER BY e.rowid DESC, edb.batch ASC";

$resql = $db->query($sql);
$serials = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		// Resolve best date: shipment → delivery → invoice → order
		$origin_ts    = 0;
		$origin_label = '';
		if (!empty($obj->date_expedition) && $obj->date_expedition !== '0000-00-00 00:00:00') {
			$origin_ts    = $db->jdate($obj->date_expedition);
			$origin_label = $langs->trans('DateShipping');
		} elseif (!empty($obj->date_delivery) && $obj->date_delivery !== '0000-00-00 00:00:00') {
			$origin_ts    = $db->jdate($obj->date_delivery);
			$origin_label = $langs->trans('DateDeliveryPlanned');
		} elseif (!empty($obj->date_invoice) && $obj->date_invoice !== '0000-00-00') {
			$origin_ts    = $db->jdate($obj->date_invoice);
			$origin_label = $langs->trans('DateInvoice');
		} elseif (!empty($obj->date_commande) && $obj->date_commande !== '0000-00-00 00:00:00') {
			$origin_ts    = $db->jdate($obj->date_commande);
			$origin_label = $langs->trans('OrderDate');
		}

		$origin_date_display = '';
		$origin_day   = 0;
		$origin_month = 0;
		$origin_year  = 0;
		$origin_input = '';
		if (!empty($origin_ts)) {
			$dArr = dol_getdate($origin_ts);
			$origin_day   = (int) $dArr['mday'];
			$origin_month = (int) $dArr['mon'];
			$origin_year  = (int) $dArr['year'];
			$origin_date_display = dol_print_date($origin_ts, 'day');
			$origin_input = dol_print_date($origin_ts, 'dayinputnoreduce');
		}

		$serials[] = array(
			'serial'         => $obj->serial_number,
			'fk_expedition'  => (int) $obj->fk_expedition,
			'expedition_ref' => (string) $obj->expedition_ref,
			'fk_commande'    => $obj->fk_commande ? (int) $obj->fk_commande : 0,
			'commande_ref'   => (string) $obj->commande_ref,
			'origin_date'    => $origin_date_display,
			'origin_label'   => $origin_label,
			'origin_day'     => $origin_day,
			'origin_month'   => $origin_month,
			'origin_year'    => $origin_year,
			'origin_input'   => $origin_input,
		);
	}
}

header('Content-Type: application/json');
print json_encode($serials);
