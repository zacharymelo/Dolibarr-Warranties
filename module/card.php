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

$id     = GETPOST('id', 'int');
$ref    = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');

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
if ($cancel) { $action = ''; }

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

if ($action == 'confirm_cancel' && $permwrite) {
	$object->cancel($user);
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

if ($action == 'confirm_reopen' && $permclose) {
	$object->reopen($user);
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// Create outbound shipment for replacement / component dispatch
if ($action == 'send_replacement' && $permwrite && isModEnabled('expedition')) {
	if (in_array($object->resolution_type, $types_with_outbound)) {
		$shipment_id = $object->createOutboundShipment($user);
		if ($shipment_id > 0) {
			setEventMessages($langs->trans('ShipmentCreated'), null, 'mesgs');
			// Redirect to expedition card to let warehouse staff complete it
			header('Location: '.DOL_URL_ROOT.'/expedition/card.php?id='.$shipment_id);
			exit;
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

if ($action == 'confirm_delete' && $permdelete) {
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

// Build filtered product list: show products that have at least one warranty record.
// When the serial/lot restriction setting is also on, further restrict to tobatch > 0.
// Falls back to null (full Ajax select) only if no warranties exist yet.
$filtered_product_list = array();
$sql_fp  = "SELECT DISTINCT p.rowid, p.ref, p.label FROM ".MAIN_DB_PREFIX."product p";
$sql_fp .= " INNER JOIN ".MAIN_DB_PREFIX."svc_warranty w ON w.fk_product = p.rowid";
$sql_fp .= " AND w.entity IN (".getEntity('svcwarranty').")";
$sql_fp .= " WHERE p.entity IN (".getEntity('product').")";
if (getDolGlobalString('WARRANTYSVC_WARRANTY_REQUIRES_LOTS')) {
	$sql_fp .= " AND p.tobatch > 0";
}
$sql_fp .= " ORDER BY p.ref ASC";
$res_fp = $db->query($sql_fp);
if ($res_fp) {
	while ($obj_fp = $db->fetch_object($res_fp)) {
		$filtered_product_list[$obj_fp->rowid] = $obj_fp->ref.($obj_fp->label ? ' — '.$obj_fp->label : '');
	}
}
if (empty($filtered_product_list)) {
	$filtered_product_list = null; // no warranties on record yet — fall back to full product select
}

llxHeader('', ($id ? $object->ref : $langs->trans('NewSvcRequest')), '');

if ($action == 'create') {
	// =====================================================================
	// CREATE FORM
	// =====================================================================
	print load_fiche_titre($langs->trans('NewSvcRequest'), '', 'technic');

	print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print dol_get_fiche_head(array(), '', '', -1);

	print '<table class="border centpercent tableforfieldcreate">';

	// Customer
	print '<tr><td class="fieldrequired">'.$langs->trans('Company').'</td>';
	print '<td>'.$form->select_company('', 'fk_soc', '', 1, 0, 0, array(), 0, 'minwidth300').'</td></tr>';

	// Product
	print '<tr><td class="fieldrequired">'.$langs->trans('Product').'</td>';
	print '<td>';
	if (!is_null($filtered_product_list)) {
		print Form::selectarray('fk_product', $filtered_product_list, '', 1, 0, 0, '', 0, 0, 0, '', 'flat minwidth300');
	} else {
		$form->select_produits(0, 'fk_product', '', 0, 0, -1, 0, '', 1, 0, 'minwidth300');
	}
	print '</td></tr>';

	// Serial number
	print '<tr><td>'.$form->textwithpicto($langs->trans('SerialNumber'), $langs->trans('TooltipSerialNumber')).'</td>';
	print '<td><input type="text" name="serial_number" class="minwidth200" autocomplete="off">';
	print '</td></tr>';

	// Warranty (optional manual pairing)
	print '<tr><td>'.$form->textwithpicto($langs->trans('SvcWarranty'), $langs->trans('TooltipSvcWarranty')).'</td>';
	print '<td>';
	$sql_w = "SELECT rowid, ref, serial_number, status FROM ".MAIN_DB_PREFIX."svc_warranty";
	$sql_w .= " WHERE entity = ".((int) $conf->entity)." ORDER BY ref ASC";
	$res_w = $db->query($sql_w);
	$warranty_opts = array('' => '');
	if ($res_w) {
		while ($ow = $db->fetch_object($res_w)) {
			$label = $ow->ref;
			if ($ow->serial_number) {
				$label .= ' — '.$ow->serial_number;
			}
			$label .= ' ('.$ow->status.')';
			$warranty_opts[$ow->rowid] = $label;
		}
	}
	print Form::selectarray('fk_warranty', $warranty_opts, GETPOST('fk_warranty', 'int'), 0, 0, 0, '', 0, 0, 0, '', 'minwidth300');
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

	// Project (if enabled)
	if (isModEnabled('project')) {
		print '<tr><td>'.$langs->trans('Project').'</td>';
		print '<td>';
		$formproject = new FormProjets($db);
		$formproject->select_projects(-1, '', 'fk_project', 0, 0, 1, 0, 0, 0, 0, '', 0, 0, 'minwidth300');
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
		print $form->select_company($object->fk_soc, 'fk_soc', '', 1, 0, 0, array(), 0, 'minwidth200');
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

	// =====================================================================
	// MOVEMENT PANEL — resolution-type-aware, directional
	// =====================================================================
	$res_type = $object->resolution_type;
	$s        = $object->status;

	$has_outbound     = in_array($res_type, $types_with_outbound);
	$has_return       = in_array($res_type, $types_with_return);
	$has_intervention = in_array($res_type, $types_intervention);
	$is_no_movement   = in_array($res_type, $types_no_movement);

	if (!$is_no_movement) {
		print '<br>';
		print '<div class="div-table-responsive">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td colspan="4">'.img_picto('', 'truck', 'class="pictofixedwidth"').$langs->trans('MovementTracker').'</td>';
		print '</tr>';

		// --- OUTBOUND card ---
		if ($has_outbound) {
			$outbound_done  = !empty($object->fk_shipment);
			$outbound_class = $outbound_done ? 'status4' : ($s >= SvcRequest::STATUS_IN_PROGRESS ? 'status1' : 'status0');
			$step_label = ($res_type == 'intervention') ? $langs->trans('OnSiteVisit') : $langs->trans('OutboundToCustomer');

			print '<tr class="oddeven">';
			print '<td style="width:20px; padding:8px 4px;">';
			print '<span class="badge '.$outbound_class.'" style="display:inline-block;width:12px;height:12px;border-radius:50%;vertical-align:middle;">&nbsp;</span>';
			print '</td>';
			print '<td style="padding:8px 12px; font-weight:bold;">'.img_picto('', 'rightarrow', 'class="pictofixedwidth"').$step_label.'</td>';
			print '<td style="padding:8px 12px;">';
			if ($outbound_done) {
				// Show shipment link + tracking
				print img_picto('', 'shipping', 'class="pictofixedwidth"');
				print '<a href="'.DOL_URL_ROOT.'/expedition/card.php?id='.$object->fk_shipment.'">'
					.$langs->trans('ShipmentRef').' #'.$object->fk_shipment.'</a>';
				if ($object->serial_out) {
					print ' &mdash; '.$langs->trans('SerialOut').': <strong>'.dol_escape_htmltag($object->serial_out).'</strong>';
				}
				if ($object->outbound_carrier || $object->outbound_tracking) {
					print '<br><span class="opacitymedium">'.dol_escape_htmltag($object->outbound_carrier);
					if ($object->outbound_carrier && $object->outbound_tracking) print ' &mdash; ';
					print dol_escape_htmltag($object->outbound_tracking).'</span>';
				}
			} else {
				print '<span class="opacitymedium">'.$langs->trans('NotYetDispatched').'</span>';
				// Inline edit for carrier/tracking/serial_out when in-progress
				if ($action == 'edit' && $permwrite) {
					print '<br>';
					print '<input type="text" name="serial_out" class="minwidth120" placeholder="'.$langs->trans('ReplacementSerial').'" title="'.$langs->trans('TooltipSerialOut').'" value="'.dol_escape_htmltag($object->serial_out).'">';
					print ' <input type="text" name="outbound_carrier" class="minwidth80" placeholder="'.$langs->trans('Carrier').'" value="'.dol_escape_htmltag($object->outbound_carrier).'">';
					print ' <input type="text" name="outbound_tracking" class="minwidth120" placeholder="'.$langs->trans('TrackingNumber').'" value="'.dol_escape_htmltag($object->outbound_tracking).'">';
				}
			}
			print '</td>';
			print '<td style="padding:8px 12px; text-align:right;">';
			// Action button: create shipment if not yet done and status allows it
			if (!$outbound_done && $permwrite && in_array($s, array(SvcRequest::STATUS_VALIDATED, SvcRequest::STATUS_IN_PROGRESS)) && isModEnabled('expedition')) {
				$btn_label = ($res_type == 'component' || $res_type == 'component_return')
					? $langs->trans('ShipComponents')
					: $langs->trans('ShipReplacementUnit');
				print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=send_replacement&token='.newToken().'" class="butAction" style="margin:0;">'.$btn_label.'</a>';
			}
			print '</td>';
			print '</tr>';
		}

		// --- INTERVENTION card ---
		if ($has_intervention) {
			$inter_done  = !empty($object->fk_intervention);
			$inter_class = $inter_done ? 'status4' : ($s >= SvcRequest::STATUS_IN_PROGRESS ? 'status1' : 'status0');

			print '<tr class="oddeven">';
			print '<td style="width:20px; padding:8px 4px;">';
			print '<span class="badge '.$inter_class.'" style="display:inline-block;width:12px;height:12px;border-radius:50%;vertical-align:middle;">&nbsp;</span>';
			print '</td>';
			print '<td style="padding:8px 12px; font-weight:bold;">'.img_picto('', 'intervention', 'class="pictofixedwidth"').$langs->trans('OnSiteIntervention').'</td>';
			print '<td style="padding:8px 12px;">';
			if ($inter_done) {
				print img_picto('', 'intervention', 'class="pictofixedwidth"');
				print '<a href="'.DOL_URL_ROOT.'/fichinter/card.php?id='.$object->fk_intervention.'">'
					.$langs->trans('Intervention').' #'.$object->fk_intervention.'</a>';
			} else {
				print '<span class="opacitymedium">'.$langs->trans('NotYetScheduled').'</span>';
			}
			print '</td>';
			print '<td style="padding:8px 12px; text-align:right;">';
			if (!$inter_done && $permwrite && in_array($s, array(SvcRequest::STATUS_VALIDATED, SvcRequest::STATUS_IN_PROGRESS)) && isModEnabled('ficheinter')) {
				print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=create_intervention&token='.newToken().'" class="butAction" style="margin:0;">'.$langs->trans('ScheduleIntervention').'</a>';
			}
			print '</td>';
			print '</tr>';
		}

		// --- RETURN card ---
		if ($has_return) {
			$return_done  = !empty($object->date_return_received) || !empty($object->fk_reception);
			$return_class = $return_done ? 'status4' : ($s == SvcRequest::STATUS_AWAIT_RETURN ? 'status1' : 'status0');

			// Overdue check
			$overdue = (!$return_done && $object->date_return_expected && dol_now() > $object->date_return_expected);

			print '<tr class="oddeven">';
			print '<td style="width:20px; padding:8px 4px;">';
			$dot_class = $overdue ? 'status8' : $return_class;
			print '<span class="badge '.$dot_class.'" style="display:inline-block;width:12px;height:12px;border-radius:50%;vertical-align:middle;">&nbsp;</span>';
			print '</td>';
			print '<td style="padding:8px 12px; font-weight:bold;">'.img_picto('', 'leftarrow', 'class="pictofixedwidth"').$langs->trans('ReturnFromCustomer');
			if ($overdue) {
				print ' <span class="badge status8">'.$langs->trans('Overdue').'</span>';
			}
			print '</td>';
			print '<td style="padding:8px 12px;">';
			if ($return_done) {
				if ($object->fk_reception) {
					print img_picto('', 'reception', 'class="pictofixedwidth"');
					print '<a href="'.DOL_URL_ROOT.'/reception/card.php?id='.$object->fk_reception.'">'
						.$langs->trans('Reception').' #'.$object->fk_reception.'</a>';
				}
				if ($object->serial_in) {
					print ' &mdash; '.$langs->trans('SerialIn').': <strong>'.dol_escape_htmltag($object->serial_in).'</strong>';
				}
				if ($object->date_return_received) {
					print '<br><span class="opacitymedium">'.$langs->trans('Received').': '.dol_print_date($object->date_return_received, 'day').'</span>';
				}
				if ($object->return_carrier || $object->return_tracking) {
					print '<br><span class="opacitymedium">'.dol_escape_htmltag($object->return_carrier);
					if ($object->return_carrier && $object->return_tracking) print ' &mdash; ';
					print dol_escape_htmltag($object->return_tracking).'</span>';
				}
			} else {
				// Show expected return date
				if ($action == 'edit' && $permwrite) {
					print $form->selectDate($object->date_return_expected ? $object->date_return_expected : -1, 'date_return_expected', 0, 0, 1, '', 1, 1);
				} elseif ($object->date_return_expected) {
					print $langs->trans('ExpectedBy').': '.dol_print_date($object->date_return_expected, 'day');
				} else {
					print '<span class="opacitymedium">'.$langs->trans('ReturnPending').'</span>';
				}
			}
			print '</td>';
			print '<td style="padding:8px 12px; text-align:right;">';
			if (!$return_done && $permwrite && in_array($s, array(SvcRequest::STATUS_IN_PROGRESS, SvcRequest::STATUS_AWAIT_RETURN))) {
				// "Log Return Received" inline form
				print '<a href="#" onclick="document.getElementById(\'log_return_form_'.$object->id.'\').style.display=\'block\';this.style.display=\'none\';return false;" class="butAction" style="margin:0;">'.$langs->trans('LogReturnReceived').'</a>';
				print '<div id="log_return_form_'.$object->id.'" style="display:none; margin-top:6px;">';
				print '<form action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" method="POST" style="display:inline;">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="action" value="log_return_received">';
				print '<input type="text" name="serial_in" class="minwidth120" placeholder="'.$langs->trans('SerialIn').'" title="'.$langs->trans('TooltipSerialIn').'" value="">';
				print ' <input type="text" name="return_carrier" class="minwidth80" placeholder="'.$langs->trans('Carrier').'" value="">';
				print ' <input type="text" name="return_tracking" class="minwidth120" placeholder="'.$langs->trans('TrackingNumber').'" value="">';
				print ' <input type="submit" class="butAction" style="margin:0;" value="'.$langs->trans('Confirm').'">';
				print '</form>';
				print '</div>';
			}
			if (!$return_done && $s == SvcRequest::STATUS_AWAIT_RETURN && $permwrite) {
				print '<br>';
				print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=send_reminder&token='.newToken().'" class="butActionDelete" style="margin:2px 0 0 0;">'.$langs->trans('SendReminder').'</a>';
				if (isModEnabled('facture')) {
					print '<br><a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=invoice_nonreturn&token='.newToken().'" class="butActionDelete" style="margin:2px 0 0 0;">'.$langs->trans('InvoiceNonReturn').'</a>';
				}
			}
			print '</td>';
			print '</tr>';
		}

		// --- Invoice link (if created) ---
		if ($object->fk_facture) {
			print '<tr class="oddeven">';
			print '<td style="width:20px; padding:8px 4px;"><span class="badge status4" style="display:inline-block;width:12px;height:12px;border-radius:50%;vertical-align:middle;">&nbsp;</span></td>';
			print '<td style="padding:8px 12px; font-weight:bold;">'.img_picto('', 'bill', 'class="pictofixedwidth"').$langs->trans('Invoice').'</td>';
			print '<td style="padding:8px 12px;">';
			print '<a href="'.DOL_URL_ROOT.'/compta/facture/card.php?id='.$object->fk_facture.'">'.$langs->trans('Invoice').' #'.$object->fk_facture.'</a>';
			print '</td><td></td></tr>';
		}

		print '</table>';
		print '</div>';
	}

	// =====================================================================
	// ACTION BUTTONS — filtered by resolution_type + status
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

		// RESOLVED / CLOSED → Re-open
		if (in_array($s, array(SvcRequest::STATUS_RESOLVED, SvcRequest::STATUS_CLOSED)) && $permclose) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_reopen&token='.newToken().'" class="butActionDelete">'.$langs->trans('ReopenSvcRequest').'</a>';
		}

		// DRAFT / VALIDATED → Cancel
		if (in_array($s, array(SvcRequest::STATUS_DRAFT, SvcRequest::STATUS_VALIDATED)) && $permwrite) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_cancel&token='.newToken().'" class="butActionDelete">'.$langs->trans('CancelSvcRequest').'</a>';
		}

		// DRAFT → Delete
		if ($s == SvcRequest::STATUS_DRAFT && $permdelete) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_delete&token='.newToken().'" class="butActionDelete">'.$langs->trans('Delete').'</a>';
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

	print dol_get_fiche_end();
}

llxFooter();
$db->close();
