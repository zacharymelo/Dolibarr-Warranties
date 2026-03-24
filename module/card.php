<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    card.php
 * \ingroup warrantysvc
 * \brief   Service Request create / view / edit card
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))       { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcrequest.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcrequestline.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/lib/warrantysvc.lib.php';

$langs->loadLangs(array('warrantysvc@warrantysvc', 'companies', 'bills', 'stocks'));

$id         = GETPOST('id', 'int');
$ref        = GETPOST('ref', 'alpha');
$action     = GETPOST('action', 'aZ09');
$cancel     = GETPOST('cancel', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$object = new SvcRequest($db);

// Load existing object
if ($id > 0 || $ref) {
	$result = $object->fetch($id, $ref);
	if ($result <= 0) {
		dol_print_error($db, $object->error);
		exit;
	}
}

// Permission checks
$permread    = $user->hasRight('warrantysvc', 'svcrequest', 'read');
$permwrite   = $user->hasRight('warrantysvc', 'svcrequest', 'write');
$permdelete  = $user->hasRight('warrantysvc', 'svcrequest', 'delete');
$permvalidate= $user->hasRight('warrantysvc', 'svcrequest', 'validate');
$permclose   = $user->hasRight('warrantysvc', 'svcrequest', 'close');

if (!$permread) { accessforbidden(); }

/*
 * Resolution type helpers — used throughout to gate UI sections
 */
$types_with_outbound   = array('component', 'component_return', 'swap_cross', 'swap_wait');
$types_with_return     = array('component_return', 'swap_cross', 'swap_wait');
$types_intervention    = array('intervention');
$types_no_movement     = array('guidance', 'informational');

/*
 * Actions
 */
$backurlforlist = DOL_URL_ROOT.'/custom/warrantysvc/list.php';

if (empty($backtopage) || ($cancel && empty($id))) {
	if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
		if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
			$backtopage = $backurlforlist;
		} else {
			$backtopage = DOL_URL_ROOT.'/custom/warrantysvc/card.php?id='.(($id > 0) ? $id : '__ID__');
		}
	}
}

if ($cancel) {
	header('Location: '.$backtopage);
	exit;
}

if ($action == 'add' && $permwrite) {
	$object->fk_soc            = GETPOST('fk_soc', 'int');
	$object->fk_product        = GETPOST('fk_product', 'int');
	$object->serial_number     = GETPOST('serial_number', 'alpha');
	$object->fk_contact        = GETPOST('fk_contact', 'int');
	$object->customer_site     = GETPOST('customer_site', 'alphanohtml');
	$object->fk_project        = GETPOST('fk_project', 'int');
	$object->fk_commande       = GETPOST('fk_commande', 'int');
	$object->issue_date        = dol_mktime(12, 0, 0, GETPOST('issue_datemonth', 'int'), GETPOST('issue_dateday', 'int'), GETPOST('issue_dateyear', 'int'));
	$object->reported_via      = GETPOST('reported_via', 'alpha');
	$object->issue_description = GETPOST('issue_description', 'restricthtml');
	$object->fk_user_assigned  = GETPOST('fk_user_assigned', 'int');
	$object->resolution_type   = GETPOST('resolution_type', 'alpha');

	// Manual warranty pairing
	$fk_warranty_posted = GETPOST('fk_warranty', 'int');
	if ($fk_warranty_posted > 0) {
		require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarranty.class.php';
		$w = new SvcWarranty($db);
		if ($w->fetch($fk_warranty_posted) > 0) {
			$object->fk_warranty     = $w->id;
			$object->warranty_status = $w->status;
			$object->billable        = ($w->status == 'active') ? 0 : 1;
		}
	}

	$result = $object->create($user);
	if ($result > 0) {
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$result);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'create';
	}
}

if ($action == 'update' && $permwrite) {
	$object->fk_soc              = GETPOST('fk_soc', 'int');
	$object->fk_product          = GETPOST('fk_product', 'int');
	$object->serial_number       = GETPOST('serial_number', 'alpha');
	$object->fk_contact          = GETPOST('fk_contact', 'int');
	$object->customer_site       = GETPOST('customer_site', 'alphanohtml');
	$object->fk_project          = GETPOST('fk_project', 'int');
	$object->fk_commande         = GETPOST('fk_commande', 'int');
	$object->issue_date          = dol_mktime(12, 0, 0, GETPOST('issue_datemonth', 'int'), GETPOST('issue_dateday', 'int'), GETPOST('issue_dateyear', 'int'));
	$object->reported_via        = GETPOST('reported_via', 'alpha');
	$object->fk_pbxcall          = GETPOST('fk_pbxcall', 'int');
	$object->issue_description   = GETPOST('issue_description', 'restricthtml');
	$object->resolution_type     = GETPOST('resolution_type', 'alpha');
	$object->resolution_notes    = GETPOST('resolution_notes', 'restricthtml');
	$object->serial_in           = GETPOST('serial_in', 'alpha');
	$object->serial_out          = GETPOST('serial_out', 'alpha');
	$object->fk_warehouse_source = GETPOST('fk_warehouse_source', 'int');
	$object->fk_warehouse_return = GETPOST('fk_warehouse_return', 'int');
	$object->fk_user_assigned    = GETPOST('fk_user_assigned', 'int');
	$object->billable            = GETPOST('billable', 'int');
	$object->outbound_carrier    = GETPOST('outbound_carrier', 'alphanohtml');
	$object->outbound_tracking   = GETPOST('outbound_tracking', 'alphanohtml');
	$object->return_carrier      = GETPOST('return_carrier', 'alphanohtml');
	$object->return_tracking     = GETPOST('return_tracking', 'alphanohtml');
	$object->date_return_expected = dol_mktime(12, 0, 0, GETPOST('date_return_expectedmonth', 'int'), GETPOST('date_return_expectedday', 'int'), GETPOST('date_return_expectedyear', 'int'));
	$object->note_private        = GETPOST('note_private', 'restricthtml');
	$object->note_public         = GETPOST('note_public', 'restricthtml');

	$result = $object->update($user);
	if ($result >= 0) {
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

if ($action == 'confirm_validate' && $permvalidate) {
	$result = $object->validate($user);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessages($langs->trans('Validated'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

if ($action == 'confirm_setinprogress' && $permwrite) {
	$object->setInProgress($user);
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

if ($action == 'confirm_resolve' && $permwrite) {
	$object->resolve($user);
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

if ($action == 'confirm_close' && $permclose) {
	$result = $object->close($user);
	if ($result < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

if ($action == 'confirm_cancel' && GETPOST('confirm', 'alpha') == 'yes' && $permwrite) {
	$result = $object->cancel($user);
	if ($result > 0 && !empty($object->fk_shipment)) {
		$shipment_url = DOL_URL_ROOT.'/expedition/card.php?id='.((int) $object->fk_shipment);
		setEventMessages($langs->trans('SvcRequestCancelledWithShipmentWarning', $object->fk_shipment, $shipment_url), null, 'warnings');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

if ($action == 'confirm_reopen' && GETPOST('confirm', 'alpha') == 'yes' && $permclose) {
	$object->reopen($user);
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Create return reception (warehouse chosen in inline form)
if ($action == 'create_return_reception' && $permwrite && isModEnabled('reception')) {
	if (in_array($object->resolution_type, $types_with_return) && empty($object->fk_reception)) {
		$rec_warehouse = GETPOST('rec_warehouse', 'int');
		$object->serial_in = GETPOST('serial_in', 'alpha');
		$rec_id = $object->createReturnReception($user, $rec_warehouse);
		if ($rec_id > 0) {
			setEventMessages($langs->trans('ReceptionCreated'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Step 5 — Validate return reception
if ($action == 'validate_return_reception' && $permwrite && isModEnabled('reception')) {
	if (!empty($object->fk_reception)) {
		$result = $object->validateReception($user);
		if ($result > 0) {
			setEventMessages($langs->trans('ReceptionValidated'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Log that a return was received (manually, before Reception module workflow)
if ($action == 'log_return_received' && $permwrite) {
	$object->date_return_received = dol_now();
	$object->return_carrier   = GETPOST('return_carrier', 'alphanohtml');
	$object->return_tracking  = GETPOST('return_tracking', 'alphanohtml');
	$object->serial_in        = GETPOST('serial_in', 'alpha');
	$result = $object->update($user);
	if ($result >= 0) {
		// Advance to In Progress if waiting for return
		if ($object->status == SvcRequest::STATUS_AWAIT_RETURN) {
			$object->setInProgress($user);
		}
		setEventMessages($langs->trans('ReturnLogged'), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

if ($action == 'create_intervention' && $permwrite && isModEnabled('ficheinter')) {
	if (in_array($object->resolution_type, $types_intervention)) {
		$fichinter_id = $object->createLinkedIntervention($user);
		if ($fichinter_id > 0) {
			setEventMessages($langs->trans('InterventionCreated'), null, 'mesgs');
			header('Location: '.DOL_URL_ROOT.'/fichinter/card.php?id='.$fichinter_id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

if ($action == 'send_reminder' && $permwrite) {
	$object->sendReturnReminder($user);
	setEventMessages($langs->trans('ReminderSent'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

if ($action == 'invoice_nonreturn' && $permwrite && isModEnabled('facture')) {
	$inv_id = $object->invoiceForNonReturn($user);
	if ($inv_id > 0) {
		setEventMessages($langs->trans('InvoiceCreated'), null, 'mesgs');
		header('Location: '.DOL_URL_ROOT.'/compta/facture/card.php?id='.$inv_id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Add component line
if ($action == 'addline' && $permwrite) {
	$line = new SvcRequestLine($db);
	$line->fk_rma_case  = $object->id;
	$line->fk_product   = GETPOST('product_id', 'int');
	$line->qty          = GETPOST('qty', 'int');
	$line->description  = GETPOST('line_desc', 'alphanohtml');
	$line->line_type    = GETPOST('line_type', 'alpha');
	$line->subprice     = price2num(GETPOST('subprice', 'alpha'));
	$result = $line->insert($user);
	if ($result < 0) {
		setEventMessages($line->error, $line->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id.'#components');
	exit;
}

// Delete component line
if ($action == 'deleteline' && $permwrite) {
	$lineid = GETPOST('lineid', 'int');
	$line   = new SvcRequestLine($db);
	$line->fetch($lineid);
	if ($line->fk_rma_case == $object->id) {
		$line->delete($user);
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id.'#components');
	exit;
}

if ($action == 'confirm_delete' && GETPOST('confirm', 'alpha') == 'yes' && $permdelete) {
	$result = $object->delete($user);
	if ($result > 0) {
		header('Location: '.DOL_URL_ROOT.'/custom/warrantysvc/list.php');
		exit;
	}
	setEventMessages($object->error, $object->errors, 'errors');
}

/*
 * View
 */
$form = new Form($db);
$formcompany = new FormCompany($db);

// Product list is now loaded dynamically via AJAX (ajax/sr_products.php) when the customer changes.
// No server-side product list needed for the create form.

llxHeader('', ($id ? $object->ref : $langs->trans('NewSvcRequest')), '');

if ($action == 'create') {
	// =====================================================================
	// CREATE FORM
	// =====================================================================

	print load_fiche_titre($langs->trans('NewSvcRequest'), '', 'technic');

	print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';

	print dol_get_fiche_head(array(), '', '', -1);

	print '<table class="border centpercent tableforfieldcreate">';

	// Pre-fill values from GET params (e.g. when arriving from warranty card button)
	$prefill_soc     = GETPOST('fk_soc', 'int');
	$prefill_product = GETPOST('fk_product', 'int');
	$prefill_serial  = GETPOST('serial_number', 'alpha');
	$prefill_project = GETPOST('fk_project', 'int');

	// Customer
	print '<tr><td class="fieldrequired">'.$langs->trans('Company').'</td>';
	print '<td>'.$form->select_company($prefill_soc, 'fk_soc', '(s.client:IN:1,3)', 1, 0, 0, array(), 0, 'minwidth300').'</td></tr>';

	// Product — disabled until customer is selected; populated via AJAX (ajax/sr_products.php).
	// When arriving from warranty card with fk_product prefilled, JS re-enables and sets value after customer loads.
	print '<tr><td class="fieldrequired">'.$langs->trans('Product').'</td>';
	print '<td>';
	print '<select name="fk_product" id="fk_product" class="flat minwidth300" '.($prefill_soc > 0 ? '' : 'disabled').'>';
	print '<option value="">'.dol_escape_htmltag($langs->trans('SelectCustomerFirst')).'</option>';
	print '</select>';
	print '</td></tr>';

	// Serial number — select populated server-side when product is known, AJAX when changed interactively.
	// No 'flat' class so Select2 does not own this element; native DOM updates work reliably.
	// Serials for SRs come from registered warranties — not from shipment records.
	// Override-mode warranties have serials typed manually; they exist in svc_warranty
	// but not in expeditiondet_batch. Scope to customer if known.
	$prefill_serials = array();
	if ($prefill_product > 0) {
		// Query 1: serials from warranty records (primary source for SRs)
		$sql_ser  = "SELECT DISTINCT w.serial_number AS serial_number";
		$sql_ser .= " FROM ".MAIN_DB_PREFIX."svc_warranty w";
		$sql_ser .= " WHERE w.fk_product = ".((int) $prefill_product);
		$sql_ser .= " AND w.serial_number IS NOT NULL AND w.serial_number != ''";
		$sql_ser .= " AND w.status != 'voided'";
		if ($prefill_soc > 0) {
			$sql_ser .= " AND w.fk_soc = ".((int) $prefill_soc);
		}
		$sql_ser .= " AND w.entity IN (".getEntity('svcwarranty').")";
		$sql_ser .= " ORDER BY serial_number ASC";
		$res_ser = $db->query($sql_ser);
		if ($res_ser) {
			while ($oser = $db->fetch_object($res_ser)) {
				$prefill_serials[] = $oser->serial_number;
			}
		}
		// Query 2: serials from validated shipments via lot tracking (secondary; skipped if table unavailable)
		$sql_ser2  = "SELECT DISTINCT pl.batch AS serial_number";
		$sql_ser2 .= " FROM ".MAIN_DB_PREFIX."expeditiondet_batch edl";
		$sql_ser2 .= " JOIN ".MAIN_DB_PREFIX."product_lot pl ON pl.rowid = edl.fk_lot";
		$sql_ser2 .= " JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.rowid = edl.fk_expeditiondet";
		$sql_ser2 .= " JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
		$sql_ser2 .= " WHERE ed.fk_product = ".((int) $prefill_product);
		$sql_ser2 .= " AND e.fk_statut >= 1";
		if ($prefill_soc > 0) {
			$sql_ser2 .= " AND e.fk_soc = ".((int) $prefill_soc);
		}
		$sql_ser2 .= " AND e.entity IN (".getEntity('expedition').")";
		$sql_ser2 .= " AND pl.batch IS NOT NULL AND pl.batch != ''";
		$sql_ser2 .= " ORDER BY serial_number ASC";
		$res_ser2 = $db->query($sql_ser2);
		if ($res_ser2) {
			while ($oser2 = $db->fetch_object($res_ser2)) {
				$prefill_serials[] = $oser2->serial_number;
			}
		}
		$prefill_serials = array_values(array_unique($prefill_serials));
		sort($prefill_serials);
	}
	print '<tr><td>'.$form->textwithpicto($langs->trans('SerialNumber'), $langs->trans('TooltipSerialNumber')).'</td>';
	print '<td>';
	if ($prefill_product > 0) {
		print '<select name="serial_number" id="serial_number" class="minwidth200">';
		print '<option value="">— '.dol_escape_htmltag($langs->trans('SelectSerial')).' —</option>';
		foreach ($prefill_serials as $s) {
			$sel = ($s === $prefill_serial) ? ' selected' : '';
			print '<option value="'.dol_escape_htmltag($s).'"'.$sel.'>'.dol_escape_htmltag($s).'</option>';
		}
		print '</select>';
	} else {
		print '<select name="serial_number" id="serial_number" class="minwidth200" disabled>';
		print '<option value="">'.dol_escape_htmltag($langs->trans('SelectProductFirst')).'</option>';
		print '</select>';
	}
	print '</td></tr>';

	// Warranty — selecting one auto-fills product + serial; selecting a serial auto-fills this.
	// Render manually so we can embed data-serial and data-product on each option.
	print '<tr><td>'.$form->textwithpicto($langs->trans('SvcWarranty'), $langs->trans('TooltipSvcWarranty')).'</td>';
	print '<td>';
	$sql_w  = "SELECT rowid, ref, serial_number, fk_product, status FROM ".MAIN_DB_PREFIX."svc_warranty";
	$sql_w .= " WHERE entity = ".((int) $conf->entity);
	$sql_w .= " AND status != 'voided'";
	if ($prefill_soc > 0) {
		$sql_w .= " AND fk_soc = ".((int) $prefill_soc);
	}
	$sql_w .= " ORDER BY ref ASC";
	$res_w = $db->query($sql_w);
	$prefill_warranty = (int) GETPOST('fk_warranty', 'int');

	// Auto-select: if no explicit warranty is specified but product + customer context exists,
	// find the single active warranty for that combination and pre-select it.
	if ($prefill_warranty === 0 && $prefill_product > 0 && $prefill_soc > 0) {
		$sql_aw  = "SELECT rowid FROM ".MAIN_DB_PREFIX."svc_warranty";
		$sql_aw .= " WHERE fk_product = ".((int) $prefill_product);
		$sql_aw .= " AND fk_soc = ".((int) $prefill_soc);
		$sql_aw .= " AND status = 'active'";
		$sql_aw .= " AND entity IN (".getEntity('svcwarranty').")";
		$res_aw = $db->query($sql_aw);
		if ($res_aw && $db->num_rows($res_aw) === 1) {
			$row_aw = $db->fetch_object($res_aw);
			$prefill_warranty = (int) $row_aw->rowid;
		}
		// 0 or >1 results → leave blank; user picks manually
	}

	print '<select name="fk_warranty" id="fk_warranty" class="minwidth300">';
	print '<option value=""></option>';
	if ($res_w) {
		while ($ow = $db->fetch_object($res_w)) {
			$label = dol_escape_htmltag($ow->ref);
			if ($ow->serial_number) {
				$label .= ' — '.dol_escape_htmltag($ow->serial_number);
			}
			$label .= ' ('.dol_escape_htmltag($ow->status).')';
			$sel = ($prefill_warranty === (int) $ow->rowid) ? ' selected' : '';
			print '<option value="'.(int) $ow->rowid.'"'
				.' data-serial="'.dol_escape_htmltag($ow->serial_number).'"'
				.' data-product="'.(int) $ow->fk_product.'"'
				.$sel.'>'.$label.'</option>';
		}
	}
	print '</select>';
	print '</td></tr>';

	// Resolution type — chosen at intake so the workflow is immediately clear
	print '<tr><td class="fieldrequired">'.$form->textwithpicto($langs->trans('ResolutionType'), $langs->trans('TooltipResolutionType')).'</td>';
	print '<td>';
	print Form::selectarray('resolution_type', svcrequest_resolution_types(), '', 1, 0, 0, '', 0, 0, 0, '', 'flat minwidth300');
	print '</td></tr>';

	// Reported via
	print '<tr><td>'.$form->textwithpicto($langs->trans('ReportedVia'), $langs->trans('TooltipReportedVia')).'</td>';
	print '<td>';
	$via_options = array(
		'phone'   => $langs->trans('ReportedViaPhone'),
		'email'   => $langs->trans('ReportedViaEmail'),
		'onsite'  => $langs->trans('ReportedViaOnSite'),
		'other'   => $langs->trans('ReportedViaOther'),
	);
	print Form::selectarray('reported_via', $via_options, 'phone', 0, 0, 0, '', 0, 0, 0, '', 'flat');
	print '</td></tr>';

	// Issue date
	print '<tr><td>'.$form->textwithpicto($langs->trans('IssueDate'), $langs->trans('TooltipIssueDate')).'</td>';
	print '<td>'.$form->selectDate(dol_now(), 'issue_date', 0, 0, 0, '', 1, 1).'</td></tr>';

	// Issue description
	print '<tr><td class="fieldrequired tdtop">'.$form->textwithpicto($langs->trans('IssueDescription'), $langs->trans('TooltipIssueDescription')).'</td>';
	print '<td><textarea name="issue_description" class="centpercent" rows="4" placeholder="'.$langs->trans('IssueDescriptionPlaceholder').'"></textarea></td></tr>';

	// Customer site note
	print '<tr><td>'.$form->textwithpicto($langs->trans('CustomerSite'), $langs->trans('TooltipCustomerSite')).'</td>';
	print '<td><input type="text" name="customer_site" class="minwidth300" placeholder="e.g. Main warehouse, Building A"></td></tr>';

	// Assigned to
	print '<tr><td>'.$langs->trans('AssignedTo').'</td>';
	print '<td>'.$form->select_dolusers($user->id, 'fk_user_assigned', 1).'</td></tr>';

	// Project (if enabled) — disabled until a customer is chosen; populated via AJAX
	if (isModEnabled('project')) {
		print '<tr><td>'.$langs->trans('Project').'</td>';
		print '<td>';
		print '<select name="fk_project" id="fk_project" class="flat minwidth300" disabled>';
		print '<option value="">'.dol_escape_htmltag($langs->trans('SelectCustomerFirst')).'</option>';
		print '</select>';
		print '</td></tr>';
	}

	print '</table>';
	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans('Create').'">';
	print ' &nbsp; ';
	print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
	print '</div>';

	print '</form>';

	print '<script>(function(){
	var serialAjaxUrl    = "'.DOL_URL_ROOT.'/custom/warrantysvc/ajax/serials.php?mode=svcrequest"';
	print ';
	var srProductAjaxUrl = "'.DOL_URL_ROOT.'/custom/warrantysvc/ajax/sr_products.php'.'";
	var projectAjaxUrl   = "'.DOL_URL_ROOT.'/custom/warrantysvc/ajax/projects.php'.'";
	var selSer  = document.getElementById("serial_number");
	var selProj = document.getElementById("fk_project");
	var noSerial  = "'.dol_escape_js($langs->trans('NoSerialsAvailable')).'";
	var pickProd  = "'.dol_escape_js($langs->trans('SelectProductFirst')).'";
	var pickSel   = "'.dol_escape_js($langs->trans('SelectProduct')).'";
	var noProd    = "'.dol_escape_js($langs->trans('NoProductForCustomer')).'";
	var pickSer   = "\u2014 '.dol_escape_js($langs->trans('SelectSerial')).' \u2014";
	var pickCust  = "'.dol_escape_js($langs->trans('SelectCustomerFirst')).'";
	var noProj    = "'.dol_escape_js($langs->trans('NoProjectForCustomer')).'";
	var pickProj  = "\u2014 '.dol_escape_js($langs->trans('SelectProject')).' \u2014";
	// pending* vars are consumed after an async product/serial load
	var pendingProduct  = '.((int) $prefill_product).';
	var pendingSerial   = "'.dol_escape_js($prefill_serial).'";
	var selWar = document.getElementById("fk_warranty");

	function notifySelect2(el){
		if(typeof jQuery !== "undefined" && jQuery.fn.select2){
			jQuery(el).trigger("change.select2");
		}
	}

	function setSerialOptions(serials){
		selSer.innerHTML = "";
		if(!serials || !serials.length){
			selSer.disabled = true;
			var opt = document.createElement("option");
			opt.value = "";
			opt.textContent = noSerial;
			selSer.appendChild(opt);
		} else {
			selSer.disabled = false;
			var blank = document.createElement("option");
			blank.value = "";
			blank.textContent = pickSer;
			selSer.appendChild(blank);
			serials.forEach(function(s){
				var opt = document.createElement("option");
				opt.value = s;
				opt.textContent = s;
				selSer.appendChild(opt);
			});
		}
		// Auto-select serial requested by warranty sync
		if(pendingSerial){
			selSer.value = pendingSerial;
			pendingSerial = "";
		}
	}

	// Warranty → serial + product sync
	function onWarrantyChange(){
		if(!selWar) return;
		var opt = selWar.options[selWar.selectedIndex];
		if(!opt || !opt.value) return;
		var serial  = opt.dataset.serial  || "";
		var product = parseInt(opt.dataset.product, 10) || 0;
		var prodEl = document.getElementById("fk_product") || document.querySelector("[name=fk_product]");
		if(!product || !prodEl) return;
		// Check if the product option exists in the current list
		var hasOption = prodEl.querySelector("option[value=\"" + product + "\"]");
		if(!hasOption){
			// Product options not yet loaded — queue and trigger a product load
			pendingProduct = product;
			pendingSerial  = serial;
			loadSrProducts();
			return;
		}
		if(parseInt(prodEl.value, 10) !== product){
			// Product is in list but not selected — set it (triggers loadSerials via change event)
			pendingSerial = serial;
			prodEl.value  = product;
			prodEl.dispatchEvent(new Event("change", {bubbles:true}));
		} else if(serial && selSer){
			// Product already correct — just set the serial directly
			selSer.value = serial;
		}
	}

	// Serial → warranty sync
	function onSerialChange(){
		if(!selWar || !selSer) return;
		var serial = selSer.value;
		for(var i = 0; i < selWar.options.length; i++){
			if(selWar.options[i].dataset.serial === serial){
				selWar.selectedIndex = i;
				return;
			}
		}
		// No matching warranty found — clear the warranty field
		selWar.selectedIndex = 0;
	}

	function loadSerials(){
		var el  = document.getElementById("fk_product");
		var pid = el ? parseInt(el.value, 10) || 0 : 0;
		if(!pid){
			selSer.innerHTML = "<option value=\'\'>" + pickProd + "</option>";
			selSer.disabled = true;
			return;
		}
		var socEl = document.querySelector("[name=fk_soc]");
		var sid   = socEl ? parseInt(socEl.value, 10) || 0 : 0;
		fetch(serialAjaxUrl + "&fk_product=" + pid + (sid > 0 ? "&fk_soc=" + sid : ""), {credentials:"same-origin"})
			.then(function(r){ return r.json(); })
			.then(function(data){ setSerialOptions(data); })
			.catch(function(){ setSerialOptions([]); });
	}

	function loadSrProducts(){
		var socEl = document.querySelector("[name=fk_soc]");
		var sid   = socEl ? parseInt(socEl.value, 10) || 0 : 0;
		var prodEl = document.getElementById("fk_product");
		if(!prodEl) return;
		if(!sid){
			prodEl.innerHTML = "<option value=\'\'>" + pickCust + "</option>";
			prodEl.disabled = true;
			// Reset serial when customer is cleared
			if(selSer){ selSer.innerHTML = "<option value=\'\'>" + pickProd + "</option>"; selSer.disabled = true; }
			return;
		}
		fetch(srProductAjaxUrl + "?socid=" + sid, {credentials:"same-origin"})
			.then(function(r){ return r.json(); })
			.then(function(data){
				prodEl.innerHTML = "";
				var blank = document.createElement("option");
				blank.value = "";
				blank.textContent = data.length ? ("\u2014 " + pickSel + " \u2014") : noProd;
				prodEl.appendChild(blank);
				data.forEach(function(p){
					var opt = document.createElement("option");
					opt.value = p.rowid;
					opt.textContent = p.label;
					prodEl.appendChild(opt);
				});
				prodEl.disabled = (data.length === 0);
				// Consume pending product (e.g. arrived via URL param or warranty sync)
				if(pendingProduct && prodEl.querySelector("option[value=\"" + pendingProduct + "\"]")){
					prodEl.value   = pendingProduct;
					pendingProduct = 0;
					loadSerials();
				} else if(!pendingProduct){
					// Customer changed interactively — reset serial
					if(selSer){ selSer.innerHTML = "<option value=\'\'>" + pickProd + "</option>"; selSer.disabled = true; }
				}
			})
			.catch(function(){ prodEl.disabled = true; });
	}

	function loadProjects(){
		if(!selProj) return;
		var el  = document.querySelector("[name=fk_soc]");
		var sid = el ? parseInt(el.value, 10) || 0 : 0;
		selProj.innerHTML = "";
		selProj.disabled  = true;
		if(!sid){
			var opt = document.createElement("option");
			opt.value = "";
			opt.textContent = pickCust;
			selProj.appendChild(opt);
			notifySelect2(selProj);
			return;
		}
		fetch(projectAjaxUrl + "?socid=" + sid, {credentials:"same-origin"})
			.then(function(r){ return r.json(); })
			.then(function(data){
				var blank = document.createElement("option");
				blank.value = "";
				blank.textContent = data.length ? pickProj : noProj;
				selProj.appendChild(blank);
				data.forEach(function(p){
					var opt = document.createElement("option");
					opt.value = p.rowid;
					opt.textContent = p.label;
					selProj.appendChild(opt);
				});
				selProj.disabled = (data.length === 0);
				notifySelect2(selProj);
			})
			.catch(function(){ selProj.disabled = true; });
	}

	if(typeof jQuery !== "undefined"){
		jQuery(document).on("select2:select select2:clear", "[name=fk_product]", loadSerials);
		jQuery(document).on("select2:select select2:clear", "[name=fk_soc]", function(){ loadSrProducts(); loadProjects(); });
	}
	document.addEventListener("change", function(e){
		if(e.target && e.target.name === "fk_product")   { loadSerials(); }
		if(e.target && e.target.name === "fk_soc")       { loadSrProducts(); loadProjects(); }
		if(e.target && e.target.name === "fk_warranty")  { onWarrantyChange(); }
		if(e.target && e.target.name === "serial_number"){ onSerialChange(); }
	});

	// On init: load products and projects for pre-selected customer (if any)
	loadSrProducts();
	loadProjects();
})();</script>';

} else {
	// =====================================================================
	// VIEW / EDIT
	// =====================================================================
	if (!$object->id) {
		dol_print_error($db, 'Record not found');
		llxFooter();
		exit;
	}

	$head = warrantysvc_prepare_head($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('SvcRequest'), -1, 'technic');

	// Confirmation dialogs
	$formconfirm = '';
	if ($action == 'cancel') {
		$formconfirm = $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('CancelSvcRequest'),
			$langs->trans('ConfirmCancelSvcRequest'),
			'confirm_cancel',
			'',
			0,
			1
		);
	}
	if ($action == 'reopen') {
		$formconfirm = $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('ReopenSvcRequest'),
			$langs->trans('ConfirmReopenSvcRequest'),
			'confirm_reopen',
			'',
			0,
			1
		);
	}
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm(
			$_SERVER['PHP_SELF'].'?id='.$object->id,
			$langs->trans('DeleteSvcRequest'),
			$langs->trans('ConfirmDeleteSvcRequest', $object->ref),
			'confirm_delete',
			'',
			0,
			1
		);
	}
	print $formconfirm;

	// Object banner
	$linkback = '<a href="'.DOL_URL_ROOT.'/custom/warrantysvc/list.php">'
		.img_picto($langs->trans('BackToList'), 'back', 'class="pictofixedwidth"')
		.$langs->trans('BackToList').'</a>';

	$morehtmlref = '';
	if ($object->fk_soc) {
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$soc = new Societe($db);
		$soc->fetch($object->fk_soc);
		$morehtmlref .= ' &mdash; <a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$soc->id.'">'
			.dol_escape_htmltag($soc->name).'</a>';
	}

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	// Wrap entire edit form around two-column layout so inputs are captured
	if ($action == 'edit') {
		print '<form action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" method="POST">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update">';
	}

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<table class="border centpercent tableforfield">';

	// Customer
	print '<tr><td class="titlefield">'.$langs->trans('Company').'</td><td>';
	if ($action == 'edit' && $permwrite) {
		print $form->select_company($object->fk_soc, 'fk_soc', '(s.client:IN:1,3)', 1, 0, 0, array(), 0, 'minwidth200');
	} else {
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$soc = new Societe($db);
		if ($soc->fetch($object->fk_soc) > 0) {
			print '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$soc->id.'">'.dol_escape_htmltag($soc->name).'</a>';
		}
	}
	print '</td></tr>';

	// Product
	print '<tr><td>'.$langs->trans('Product').'</td><td>';
	if ($action == 'edit' && $permwrite) {
		if (!is_null($filtered_product_list)) {
			print Form::selectarray('fk_product', $filtered_product_list, $object->fk_product, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth200');
		} else {
			$form->select_produits($object->fk_product, 'fk_product', '', 0, 0, -1, 0, '', 1, 0, 'minwidth200');
		}
	} else {
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$prod = new Product($db);
		if ($prod->fetch($object->fk_product) > 0) {
			print '<a href="'.DOL_URL_ROOT.'/product/card.php?id='.$prod->id.'">'
				.dol_escape_htmltag($prod->ref).' &mdash; '.dol_escape_htmltag($prod->label).'</a>';
		}
	}
	print '</td></tr>';

	// Serial number (customer's defective unit)
	print '<tr><td>'.$form->textwithpicto($langs->trans('SerialNumber'), $langs->trans('TooltipSerialNumber')).'</td><td>';
	if ($action == 'edit' && $permwrite) {
		print '<input type="text" name="serial_number" id="serial_number" class="minwidth200"'
			.' value="'.dol_escape_htmltag($object->serial_number).'" list="serial_suggestions">';
		$serials = $object->getCustomerSerials($object->fk_soc, $object->fk_product);
		if ($serials) {
			print '<datalist id="serial_suggestions">';
			foreach ($serials as $s) {
				print '<option value="'.dol_escape_htmltag($s['serial']).'">'.dol_escape_htmltag($s['serial']).' ('.$s['product_ref'].')</option>';
			}
			print '</datalist>';
		}
	} else {
		print '<strong>'.dol_escape_htmltag($object->serial_number).'</strong>';
	}
	print '</td></tr>';

	// Issue date
	print '<tr><td>'.$form->textwithpicto($langs->trans('IssueDate'), $langs->trans('TooltipIssueDate')).'</td><td>';
	if ($action == 'edit' && $permwrite) {
		print $form->selectDate($object->issue_date, 'issue_date', 0, 0, 0, '', 1, 1);
	} else {
		print dol_print_date($object->issue_date, 'day');
	}
	print '</td></tr>';

	// Reported via
	print '<tr><td>'.$form->textwithpicto($langs->trans('ReportedVia'), $langs->trans('TooltipReportedVia')).'</td><td>';
	if ($action == 'edit' && $permwrite) {
		$via_options = array('phone'=>$langs->trans('ReportedViaPhone'),'email'=>$langs->trans('ReportedViaEmail'),'onsite'=>$langs->trans('ReportedViaOnSite'),'other'=>$langs->trans('ReportedViaOther'));
		print Form::selectarray('reported_via', $via_options, $object->reported_via, 0, 0, 0, '', 0, 0, 0, '', 'flat');
	} else {
		$via_labels = array('phone'=>'ReportedViaPhone','email'=>'ReportedViaEmail','onsite'=>'ReportedViaOnSite','other'=>'ReportedViaOther');
		print $langs->trans(isset($via_labels[$object->reported_via]) ? $via_labels[$object->reported_via] : 'ReportedViaOther');
	}
	print '</td></tr>';

	// Linked Call (pbxcalls integration — optional)
	if (isModEnabled('pbxcalls')) {
		print '<tr><td>'.$form->textwithpicto($langs->trans('LinkedCall'), $langs->trans('TooltipLinkedCall')).'</td><td>';
		if ($action == 'edit' && $permwrite) {
			// Dropdown of recent calls for this company
			$call_options = array(0 => '('.$langs->trans('None').')');
			if (!empty($object->fk_soc)) {
				global $conf;
				$sql_calls  = "SELECT rowid, ref, caller_number, caller_name, call_start";
				$sql_calls .= " FROM ".MAIN_DB_PREFIX."pbxcalls_call";
				$sql_calls .= " WHERE fk_soc = ".((int) $object->fk_soc);
				$sql_calls .= " AND entity = ".((int) $conf->entity);
				$sql_calls .= " ORDER BY call_start DESC LIMIT 30";
				$res_calls = $db->query($sql_calls);
				if ($res_calls) {
					while ($c = $db->fetch_object($res_calls)) {
						$call_label = dol_escape_htmltag($c->ref);
						if ($c->caller_name) {
							$call_label .= ' — '.dol_escape_htmltag($c->caller_name);
						} elseif ($c->caller_number) {
							$call_label .= ' — '.dol_escape_htmltag($c->caller_number);
						}
						if ($c->call_start) {
							$call_label .= ' ('.dol_print_date($db->jdate($c->call_start), 'dayhour').')';
						}
						$call_options[(int) $c->rowid] = $call_label;
					}
				}
			}
			print Form::selectarray('fk_pbxcall', $call_options, (int) $object->fk_pbxcall, 0, 0, 0, '', 0, 0, 0, '', 'flat');
		} else {
			if (!empty($object->fk_pbxcall)) {
				print img_picto('', 'phoning', 'class="pictofixedwidth"');
				print '<a href="'.dol_buildpath('/pbxcalls/call_card.php', 1).'?id='.$object->fk_pbxcall.'">';
				// Fetch call ref for display
				$sql_cr  = "SELECT ref, caller_name, caller_number FROM ".MAIN_DB_PREFIX."pbxcalls_call";
				$sql_cr .= " WHERE rowid = ".((int) $object->fk_pbxcall)." AND entity = ".((int) $conf->entity);
				$res_cr = $db->query($sql_cr);
				if ($res_cr && ($cr = $db->fetch_object($res_cr))) {
					print dol_escape_htmltag($cr->ref);
					if ($cr->caller_name) {
						print ' — '.dol_escape_htmltag($cr->caller_name);
					} elseif ($cr->caller_number) {
						print ' — '.dol_escape_htmltag($cr->caller_number);
					}
				} else {
					print '#'.$object->fk_pbxcall;
				}
				print '</a>';
			} else {
				print '<span class="opacitymedium">'.$langs->trans('None').'</span>';
			}
		}
		print '</td></tr>';
	}

	// Assigned to
	print '<tr><td>'.$langs->trans('AssignedTo').'</td><td>';
	if ($action == 'edit' && $permwrite) {
		print $form->select_dolusers($object->fk_user_assigned, 'fk_user_assigned', 1);
	} else {
		if ($object->fk_user_assigned) {
			$u = new User($db);
			$u->fetch($object->fk_user_assigned);
			print dol_escape_htmltag($u->getFullName($langs));
		}
	}
	print '</td></tr>';

	// Customer site
	print '<tr><td>'.$form->textwithpicto($langs->trans('CustomerSite'), $langs->trans('TooltipCustomerSite')).'</td><td>';
	if ($action == 'edit' && $permwrite) {
		print '<input type="text" name="customer_site" class="minwidth200" value="'.dol_escape_htmltag($object->customer_site).'">';
	} else {
		print dol_escape_htmltag($object->customer_site);
	}
	print '</td></tr>';

	// Project link (view only)
	if (isModEnabled('project') && $object->fk_project) {
		require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
		$proj = new Project($db);
		if ($proj->fetch($object->fk_project) > 0) {
			print '<tr><td>'.$langs->trans('Project').'</td>';
			print '<td><a href="'.DOL_URL_ROOT.'/projet/card.php?id='.$proj->id.'">'.dol_escape_htmltag($proj->ref).' &mdash; '.dol_escape_htmltag($proj->title).'</a></td></tr>';
		}
	}

	print '</table>';
	print '</div>'; // fichehalfleft

	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">';

	// Issue description
	print '<tr><td class="titlefield tdtop">'.$form->textwithpicto($langs->trans('IssueDescription'), $langs->trans('TooltipIssueDescription')).'</td><td>';
	if ($action == 'edit' && $permwrite) {
		print '<textarea name="issue_description" class="centpercent" rows="5">'.dol_escape_htmltag($object->issue_description, 1, 1).'</textarea>';
	} else {
		print dol_nl2br(dol_escape_htmltag($object->issue_description, 0, 1));
	}
	print '</td></tr>';

	// Resolution type
	print '<tr><td>'.$form->textwithpicto($langs->trans('ResolutionType'), $langs->trans('TooltipResolutionType')).'</td><td>';
	if ($action == 'edit' && $permwrite) {
		print Form::selectarray('resolution_type', svcrequest_resolution_types(), $object->resolution_type, 1, 0, 0, '', 0, 0, 0, '', 'flat minwidth200');
	} else {
		print svcrequest_resolution_label($object->resolution_type);
	}
	print '</td></tr>';

	// Warranty
	print '<tr><td>'.$langs->trans('WarrantyStatus').'</td><td>';
	print svcwarranty_status_badge($object->warranty_status);
	if ($object->fk_warranty) {
		print ' &nbsp;<a href="'.DOL_URL_ROOT.'/custom/warrantysvc/warranty_card.php?id='.$object->fk_warranty.'">'.$langs->trans('ViewWarranty').'</a>';
	}
	print '</td></tr>';

	// Billable
	print '<tr><td>'.$form->textwithpicto($langs->trans('Billable'), $langs->trans('TooltipBillable')).'</td><td>';
	if ($action == 'edit' && $permwrite) {
		print '<input type="checkbox" name="billable" value="1"'.($object->billable ? ' checked' : '').'>';
	} else {
		print $object->billable
			? '<span class="badge status8">'.$langs->trans('Yes').'</span>'
			: '<span class="opacitymedium">'.$langs->trans('No').'</span>';
	}
	print '</td></tr>';

	// Resolution notes (visible once in progress)
	if (!in_array($object->status, array(SvcRequest::STATUS_DRAFT, SvcRequest::STATUS_VALIDATED)) || $action == 'edit') {
		print '<tr><td class="tdtop">'.$form->textwithpicto($langs->trans('ResolutionNotes'), $langs->trans('TooltipResolutionNotes')).'</td><td>';
		if ($action == 'edit' && $permwrite) {
			print '<textarea name="resolution_notes" class="centpercent" rows="3">'.dol_escape_htmltag($object->resolution_notes, 1, 1).'</textarea>';
		} else {
			print dol_nl2br(dol_escape_htmltag($object->resolution_notes, 0, 1));
		}
		print '</td></tr>';
	}

	print '</table>';
	print '</div>'; // fichehalfright
	print '</div>'; // fichecenter
	print '<div class="clearboth"></div>'; // clear floated half-columns before movement panel

	// =====================================================================
	// RMA ACTION PANEL — Replacement Order & Return Reception
	// =====================================================================
	$res_type = $object->resolution_type;
	$s        = $object->status;

	$has_outbound     = in_array($res_type, $types_with_outbound);
	$has_return       = in_array($res_type, $types_with_return);
	$has_intervention = in_array($res_type, $types_intervention);
	$is_no_movement   = in_array($res_type, $types_no_movement);

	if (!$is_no_movement) {
		// Fetch warranty product + serial for pre-population of return reception
		$warranty_product_id    = 0;
		$warranty_product_label = '';
		$warranty_serial        = '';
		if (!empty($object->fk_warranty)) {
			require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarranty.class.php';
			$_war = new SvcWarranty($db);
			if ($_war->fetch($object->fk_warranty) > 0) {
				$warranty_product_id = (int) $_war->fk_product;
				$warranty_serial     = $_war->serial;
				if ($warranty_product_id > 0) {
					require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
					$_wprod = new Product($db);
					if ($_wprod->fetch($warranty_product_id) > 0) {
						$warranty_product_label = $_wprod->label;
					}
				}
			}
		}

		$active_status = in_array($s, array(SvcRequest::STATUS_VALIDATED, SvcRequest::STATUS_IN_PROGRESS, SvcRequest::STATUS_AWAIT_RETURN));

		print '<br>';
		print '<div class="div-table-responsive">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td colspan="2">'.img_picto('', 'truck', 'class="pictofixedwidth"').$langs->trans('RMAActions').'</td>';
		print '</tr>';

		// --- Replacement Order row ---
		if ($has_outbound) {
			print '<tr class="oddeven">';
			print '<td style="padding:8px 12px; font-weight:bold; width:220px;">'.img_picto('', 'rightarrow', 'class="pictofixedwidth"').$langs->trans('ReplacementOrder').'</td>';
			print '<td style="padding:8px 12px;">';

			if (!empty($object->fk_commande)) {
				require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
				$_ord = new Commande($db);
				$_ord_label = ($_ord->fetch($object->fk_commande) > 0 && $_ord->ref) ? $_ord->ref : '#'.$object->fk_commande;
				print img_picto('', 'order', 'class="pictofixedwidth"');
				print '<a href="'.DOL_URL_ROOT.'/commande/card.php?id='.$object->fk_commande.'">'.$langs->trans('Order').' '.$_ord_label.'</a>';
				if ($object->serial_out) {
					print ' &mdash; '.$langs->trans('Serial').': <strong>'.dol_escape_htmltag($object->serial_out).'</strong>';
				}
			} elseif ($active_status && $permwrite && isModEnabled('order')) {
				$create_so_url = DOL_URL_ROOT.'/commande/card.php?action=create'
					.'&socid='.((int) $object->fk_soc)
					.'&origin=svcrequest'
					.'&origin_id='.((int) $object->id)
					.'&backtopage='.urlencode(DOL_URL_ROOT.'/custom/warrantysvc/card.php?id='.$object->id);
				print '<a href="'.dol_escape_htmltag($create_so_url).'" class="butAction" style="margin:0;">'.$langs->trans('CreateReplacementOrder').'</a>';
			} else {
				print '<span class="opacitymedium">'.$langs->trans('NoReplacementOrderYet').'</span>';
			}

			print '</td>';
			print '</tr>';
		}

		// --- Return Reception row ---
		if ($has_return) {
			print '<tr class="oddeven">';
			print '<td style="padding:8px 12px; font-weight:bold; width:220px;">'.img_picto('', 'leftarrow', 'class="pictofixedwidth"').$langs->trans('ReturnReception').'</td>';
			print '<td style="padding:8px 12px;">';

			if (!empty($object->fk_reception)) {
				require_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';
				$_rec = new Reception($db);
				$_rec_label = ($_rec->fetch($object->fk_reception) > 0 && $_rec->ref) ? $_rec->ref : '#'.$object->fk_reception;
				print img_picto('', 'reception', 'class="pictofixedwidth"');
				print '<a href="'.DOL_URL_ROOT.'/reception/card.php?id='.$object->fk_reception.'">'.$langs->trans('Reception').' '.$_rec_label.'</a>';
				if ($object->serial_in) {
					print ' &mdash; '.$langs->trans('SerialIn').': <strong>'.dol_escape_htmltag($object->serial_in).'</strong>';
				}
			} elseif ($active_status && $permwrite && isModEnabled('reception')) {
				$form_id2 = 'form_rec_'.$object->id;
				print '<a href="#" onclick="document.getElementById(\''.$form_id2.'\').style.display=\'block\';this.style.display=\'none\';return false;" class="butAction" style="margin:0;">'.$langs->trans('CreateReturnReception').'</a>';
				print '<div id="'.$form_id2.'" style="display:none; margin-top:8px;">';
				print '<form action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" method="POST">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="create_return_reception">';
				if ($warranty_product_id > 0) {
					print '<input type="hidden" name="rec_product_id" value="'.$warranty_product_id.'">';
					print '<strong>'.dol_escape_htmltag($warranty_product_label ?: $langs->trans('Product').' #'.$warranty_product_id).'</strong>';
					print ' ';
				}
				if ($warranty_serial) {
					print '<input type="hidden" name="serial_in" value="'.dol_escape_htmltag($warranty_serial).'">';
					print '<span class="opacitymedium">'.dol_escape_htmltag($warranty_serial).'</span>';
					print ' ';
				} else {
					print '<input type="text" name="serial_in" class="minwidth120" placeholder="'.$langs->trans('SerialIn').'" value=""> ';
				}
				print $form->select_warehouse($object->fk_warehouse_return > 0 ? $object->fk_warehouse_return : 0, 'rec_warehouse', 1, '', 1, 0, '', 0, 0, array(), 'minwidth150');
				print ' <input type="submit" class="butAction" style="margin:0;" value="'.$langs->trans('Confirm').'">';
				print '</form>';
				print '</div>';
			} else {
				print '<span class="opacitymedium">'.$langs->trans('NoReturnReceptionYet').'</span>';
			}

			print '</td>';
			print '</tr>';
		}

		// --- Intervention row ---
		if ($has_intervention) {
			$inter_done = !empty($object->fk_intervention);
			print '<tr class="oddeven">';
			print '<td style="padding:8px 12px; font-weight:bold; width:220px;">'.img_picto('', 'intervention', 'class="pictofixedwidth"').$langs->trans('OnSiteIntervention').'</td>';
			print '<td style="padding:8px 12px;">';
			if ($inter_done) {
				print img_picto('', 'intervention', 'class="pictofixedwidth"');
				print '<a href="'.DOL_URL_ROOT.'/fichinter/card.php?id='.$object->fk_intervention.'">'.$langs->trans('Intervention').' #'.$object->fk_intervention.'</a>';
			} elseif ($permwrite && in_array($s, array(SvcRequest::STATUS_VALIDATED, SvcRequest::STATUS_IN_PROGRESS)) && isModEnabled('ficheinter')) {
				print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=create_intervention&token='.newToken().'" class="butAction" style="margin:0;">'.$langs->trans('ScheduleIntervention').'</a>';
			} else {
				print '<span class="opacitymedium">'.$langs->trans('NotYetScheduled').'</span>';
			}
			print '</td>';
			print '</tr>';
		}

		// --- Invoice link ---
		if ($object->fk_facture) {
			print '<tr class="oddeven">';
			print '<td style="padding:8px 12px; font-weight:bold; width:220px;">'.img_picto('', 'bill', 'class="pictofixedwidth"').$langs->trans('Invoice').'</td>';
			print '<td style="padding:8px 12px;">';
			print '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?id='.$object->fk_facture.'">'.$langs->trans('Invoice').' #'.$object->fk_facture.'</a>';
			print '</td>';
			print '</tr>';
		}

		print '</table>';
		print '</div>';
	}

	print dol_get_fiche_end();

	// ---- Linked objects block ----
	if ($action != 'edit' && $object->id > 0) {
		$tmparray = $form->showLinkToObjectBlock($object, array(), array('svcrequest'), 1);
		$linktoelem = isset($tmparray['linktoelem']) ? $tmparray['linktoelem'] : '';
		$htmltoenteralink = isset($tmparray['htmltoenteralink']) ? $tmparray['htmltoenteralink'] : '';
		print $htmltoenteralink;
		$somethingshown = $form->showLinkedObjectBlock($object, $linktoelem);
	}

	// =====================================================================
	// ACTION BUTTONS — outside the fiche card per Dolibarr standard layout
	// =====================================================================
	print '<div class="tabsAction">';

	if ($action == 'edit') {
		print '<input type="submit" class="butAction" value="'.$langs->trans('Save').'">';
		print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" class="butActionDelete">'.$langs->trans('Cancel').'</a>';
	} else {
		// Edit: available while request is not yet closed/cancelled
		if ($permwrite && in_array($s, array(SvcRequest::STATUS_DRAFT, SvcRequest::STATUS_VALIDATED, SvcRequest::STATUS_IN_PROGRESS, SvcRequest::STATUS_AWAIT_RETURN))) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit&token='.newToken().'" class="butAction">'.$langs->trans('Modify').'</a>';
		}

		// DRAFT → Validate
		if ($s == SvcRequest::STATUS_DRAFT && $permvalidate) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_validate&token='.newToken().'" class="butAction">'.$langs->trans('ValidateSvcRequest').'</a>';
		}

		// VALIDATED → Set In Progress
		if ($s == SvcRequest::STATUS_VALIDATED && $permwrite) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_setinprogress&token='.newToken().'" class="butAction">'.$langs->trans('SetInProgress').'</a>';
		}

		// IN PROGRESS — resolution-type-specific next actions
		if ($s == SvcRequest::STATUS_IN_PROGRESS && $permwrite) {
			// Guidance / Informational: nothing to dispatch — just resolve
			if ($is_no_movement) {
				print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_resolve&token='.newToken().'" class="butAction">'.$langs->trans('MarkResolved').'</a>';
			}
			// Mark resolved available for all types once movement is done
			if (!$is_no_movement) {
				print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_resolve&token='.newToken().'" class="butAction">'.$langs->trans('MarkResolved').'</a>';
			}
		}

		// AWAITING RETURN — can still mark resolved (e.g., return confirmed out-of-band)
		if ($s == SvcRequest::STATUS_AWAIT_RETURN && $permwrite) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_resolve&token='.newToken().'" class="butAction">'.$langs->trans('MarkResolved').'</a>';
		}

		// RESOLVED / IN_PROGRESS / AWAIT_RETURN → Close
		if (in_array($s, array(SvcRequest::STATUS_RESOLVED, SvcRequest::STATUS_IN_PROGRESS, SvcRequest::STATUS_AWAIT_RETURN)) && $permclose) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_close&token='.newToken().'" class="butAction">'.$langs->trans('CloseSvcRequest').'</a>';
		}

		// RESOLVED / CLOSED / CANCELLED → Re-open
		if (in_array($s, array(SvcRequest::STATUS_RESOLVED, SvcRequest::STATUS_CLOSED, SvcRequest::STATUS_CANCELLED)) && $permclose) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=reopen&token='.newToken().'" class="butActionDelete">'.$langs->trans('ReopenSvcRequest').'</a>';
		}

		// DRAFT / VALIDATED / IN_PROGRESS / AWAIT_RETURN → Cancel
		if (in_array($s, array(SvcRequest::STATUS_DRAFT, SvcRequest::STATUS_VALIDATED, SvcRequest::STATUS_IN_PROGRESS, SvcRequest::STATUS_AWAIT_RETURN)) && $permwrite) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=cancel&token='.newToken().'" class="butActionDelete">'.$langs->trans('CancelSvcRequest').'</a>';
		}

		// DRAFT / CANCELLED → Delete
		if (in_array($s, array(SvcRequest::STATUS_DRAFT, SvcRequest::STATUS_CANCELLED)) && $permdelete) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken().'" class="butActionDelete">'.$langs->trans('Delete').'</a>';
		}
	}

	print '</div>'; // tabsAction

	// Close edit form
	if ($action == 'edit') {
		print '</form>';
	}

	// =====================================================================
	// COMPONENT LINES — only relevant for component dispatch types
	// =====================================================================
	if (in_array($res_type, array('component', 'component_return'))) {
		print '<br>';
		print '<a name="components"></a>';
		print '<div class="div-table-responsive">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.img_picto('', 'product', 'class="pictofixedwidth"').$langs->trans('ComponentLines').'</td>';
		print '<td>'.$langs->trans('Description').'</td>';
		print '<td class="center">'.$langs->trans('Qty').'</td>';
		print '<td>'.$langs->trans('Type').'</td>';
		print '<td class="center">'.$langs->trans('Status').'</td>';
		if ($permwrite && in_array($s, array(SvcRequest::STATUS_VALIDATED, SvcRequest::STATUS_IN_PROGRESS, SvcRequest::STATUS_AWAIT_RETURN))) {
			print '<td></td>';
		}
		print '</tr>';

		if (!empty($object->lines)) {
			foreach ($object->lines as $line) {
				require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
				$prod = new Product($db);
				$prod->fetch($line->fk_product);
				$shipped_badge = $line->shipped
					? '<span class="badge status6">'.$langs->trans('Shipped').'</span>'
					: '<span class="badge status0">'.$langs->trans('Pending').'</span>';
				print '<tr class="oddeven">';
				print '<td><a href="'.DOL_URL_ROOT.'/product/card.php?id='.$prod->id.'">'.dol_escape_htmltag($prod->ref).'</a></td>';
				print '<td>'.dol_escape_htmltag($line->description).'</td>';
				print '<td class="center">'.dol_escape_htmltag($line->qty).'</td>';
				print '<td>'.dol_escape_htmltag($line->line_type).'</td>';
				print '<td class="center">'.$shipped_badge.'</td>';
				if ($permwrite && in_array($s, array(SvcRequest::STATUS_VALIDATED, SvcRequest::STATUS_IN_PROGRESS, SvcRequest::STATUS_AWAIT_RETURN))) {
					print '<td class="center"><a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=deleteline&lineid='.$line->id.'&token='.newToken().'" class="reposition">'.img_picto($langs->trans('Delete'), 'delete').'</a></td>';
				}
				print '</tr>';
			}
		}

		// Add line form
		if ($permwrite && in_array($s, array(SvcRequest::STATUS_VALIDATED, SvcRequest::STATUS_IN_PROGRESS, SvcRequest::STATUS_AWAIT_RETURN))) {
			print '<tr>';
			print '<form action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" method="POST">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="addline">';
			print '<td>'.$form->select_produits(0, 'product_id', '', 0, 0, 1, 2, '', 0, array(), 0, 0, 0, 'minwidth150').'</td>';
			print '<td><input type="text" name="line_desc" class="minwidth150" placeholder="'.$langs->trans('Description').'"></td>';
			print '<td class="center"><input type="number" name="qty" value="1" min="1" style="width:50px"></td>';
			print '<td>';
			$line_types = array('component_out'=>$langs->trans('ComponentOut'),'component_in'=>$langs->trans('ComponentIn'));
			print Form::selectarray('line_type', $line_types, 'component_out', 0, 0, 0, '', 0, 0, 0, '', 'flat');
			print '</td>';
			print '<td colspan="2"><input type="submit" name="addline" class="button small" value="'.$langs->trans('Add').'"></td>';
			print '</form>';
			print '</tr>';
		}

		print '</table>';
		print '</div>';
	}
}

llxFooter();
$db->close();
