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
 * Actions
 */
if ($cancel) { $action = ''; }

if ($action == 'add' && $permwrite) {
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
	$object->fk_user_assigned    = GETPOST('fk_user_assigned', 'int');
	$object->resolution_type     = GETPOST('resolution_type', 'alpha');

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

if ($action == 'create_intervention' && $permwrite) {
	$fichinter_id = $object->createLinkedIntervention($user);
	if ($fichinter_id > 0) {
		setEventMessages($langs->trans('InterventionCreated'), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
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

if ($action == 'invoice_nonreturn' && $permwrite) {
	$inv_id = $object->invoiceForNonReturn($user);
	if ($inv_id > 0) {
		setEventMessages($langs->trans('InvoiceCreated'), null, 'mesgs');
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
	print '<td>'.$form->select_produits(0, 'fk_product', '', 0, 0, 1, 2, '', 0, array(), 0, 0, 0, 'minwidth300').'</td></tr>';

	// Serial number
	print '<tr><td>'.$langs->trans('SerialNumber').'</td>';
	print '<td><input type="text" name="serial_number" class="minwidth200" autocomplete="off">';
	print '</td></tr>';

	// Reported via
	print '<tr><td>'.$langs->trans('ReportedVia').'</td>';
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
	print '<tr><td>'.$langs->trans('IssueDate').'</td>';
	print '<td>'.$form->selectDate(dol_now(), 'issue_date', 0, 0, 0, '', 1, 1).'</td></tr>';

	// Issue description
	print '<tr><td class="fieldrequired tdtop">'.$langs->trans('IssueDescription').'</td>';
	print '<td><textarea name="issue_description" class="centpercent" rows="4"></textarea></td></tr>';

	// Customer site note
	print '<tr><td>'.$langs->trans('CustomerSite').'</td>';
	print '<td><input type="text" name="customer_site" class="minwidth300"></td></tr>';

	// Assigned to
	print '<tr><td>'.$langs->trans('AssignedTo').'</td>';
	print '<td>'.$form->select_dolusers($user->id, 'fk_user_assigned', 1).'</td></tr>';

	// Project (if enabled)
	if (isModEnabled('project')) {
		print '<tr><td>'.$langs->trans('Project').'</td>';
		$formproject = new FormProjets($db);
		print '<td>'.$formproject->select_projects(-1, '', 'fk_project', 0, 0, 1, 0, 0, 0, 0, '', 0, 0, 'minwidth300').'</td></tr>';
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

	// ---- Object banner ----
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

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';

	print '<table class="border centpercent tableforfield">';

	// Customer
	print '<tr><td class="titlefield">'.$langs->trans('Company').'</td>';
	print '<td>';
	if ($action == 'edit' && $permwrite) {
		print $form->select_company($object->fk_soc, 'fk_soc', '', 1, 0, 0, array(), 0, 'minwidth200');
	} else {
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$soc = new Societe($db);
		if ($soc->fetch($object->fk_soc) > 0) {
			print '<a href="'.DOL_URL_ROOT.'/societe/card.php?socid='.$soc->id.'">'
				.dol_escape_htmltag($soc->name).'</a>';
		}
	}
	print '</td></tr>';

	// Product + serial
	print '<tr><td>'.$langs->trans('Product').'</td>';
	print '<td>';
	if ($action == 'edit' && $permwrite) {
		print $form->select_produits($object->fk_product, 'fk_product', '', 0, 0, 1, 2, '', 0, array(), 0, 0, 0, 'minwidth200');
	} else {
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$prod = new Product($db);
		if ($prod->fetch($object->fk_product) > 0) {
			print '<a href="'.DOL_URL_ROOT.'/product/card.php?id='.$prod->id.'">'
				.dol_escape_htmltag($prod->ref).' &mdash; '.dol_escape_htmltag($prod->label).'</a>';
		}
	}
	print '</td></tr>';

	print '<tr><td>'.$langs->trans('SerialNumber').'</td>';
	print '<td>';
	if ($action == 'edit' && $permwrite) {
		// AJAX-enabled serial selector from customer shipment history
		print '<input type="text" name="serial_number" id="serial_number" class="minwidth200"'
			.' value="'.dol_escape_htmltag($object->serial_number).'" list="serial_suggestions">';
		$serials = $object->getCustomerSerials($object->fk_soc, $object->fk_product);
		if ($serials) {
			print '<datalist id="serial_suggestions">';
			foreach ($serials as $s) {
				print '<option value="'.dol_escape_htmltag($s['serial']).'">'
					.dol_escape_htmltag($s['serial']).' ('.$s['product_ref'].')</option>';
			}
			print '</datalist>';
		}
	} else {
		print dol_escape_htmltag($object->serial_number);
	}
	print '</td></tr>';

	// Issue date + reported via
	print '<tr><td>'.$langs->trans('IssueDate').'</td>';
	print '<td>';
	if ($action == 'edit' && $permwrite) {
		print $form->selectDate($object->issue_date, 'issue_date', 0, 0, 0, '', 1, 1);
	} else {
		print dol_print_date($object->issue_date, 'day');
	}
	print '</td></tr>';

	print '<tr><td>'.$langs->trans('ReportedVia').'</td>';
	print '<td>';
	if ($action == 'edit' && $permwrite) {
		$via_options = array(
			'phone'  => $langs->trans('ReportedViaPhone'),
			'email'  => $langs->trans('ReportedViaEmail'),
			'onsite' => $langs->trans('ReportedViaOnSite'),
			'other'  => $langs->trans('ReportedViaOther'),
		);
		print Form::selectarray('reported_via', $via_options, $object->reported_via, 0, 0, 0, '', 0, 0, 0, '', 'flat');
	} else {
		$via_labels = array('phone'=>'ReportedViaPhone','email'=>'ReportedViaEmail','onsite'=>'ReportedViaOnSite','other'=>'ReportedViaOther');
		$via_key = isset($via_labels[$object->reported_via]) ? $via_labels[$object->reported_via] : 'ReportedViaOther';
		print $langs->trans($via_key);
	}
	print '</td></tr>';

	// Assigned
	print '<tr><td>'.$langs->trans('AssignedTo').'</td>';
	print '<td>';
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
	print '<tr><td>'.$langs->trans('CustomerSite').'</td>';
	print '<td>';
	if ($action == 'edit' && $permwrite) {
		print '<input type="text" name="customer_site" class="minwidth200" value="'.dol_escape_htmltag($object->customer_site).'">';
	} else {
		print dol_escape_htmltag($object->customer_site);
	}
	print '</td></tr>';

	// Project
	if (isModEnabled('project') && $object->fk_project) {
		require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
		$proj = new Project($db);
		if ($proj->fetch($object->fk_project) > 0) {
			print '<tr><td>'.$langs->trans('Project').'</td>';
			print '<td><a href="'.DOL_URL_ROOT.'/projet/card.php?id='.$proj->id.'">'
				.dol_escape_htmltag($proj->ref).' &mdash; '.dol_escape_htmltag($proj->title).'</a></td></tr>';
		}
	}

	print '</table>';
	print '</div>'; // fichehalfleft

	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">';

	// Issue description
	print '<tr><td class="titlefield tdtop">'.$langs->trans('IssueDescription').'</td>';
	print '<td>';
	if ($action == 'edit' && $permwrite) {
		print '<textarea name="issue_description" class="centpercent" rows="5">'.dol_escape_htmltag($object->issue_description, 1, 1).'</textarea>';
	} else {
		print dol_nl2br(dol_escape_htmltag($object->issue_description, 0, 1));
	}
	print '</td></tr>';

	// Resolution type
	print '<tr><td>'.$langs->trans('ResolutionType').'</td>';
	print '<td>';
	if ($action == 'edit' && $permwrite) {
		print Form::selectarray('resolution_type', svcrequest_resolution_types(), $object->resolution_type, 1, 0, 0, '', 0, 0, 0, '', 'flat minwidth200');
	} else {
		print svcrequest_resolution_label($object->resolution_type);
	}
	print '</td></tr>';

	// Warranty coverage banner
	print '<tr><td>'.$langs->trans('WarrantyStatus').'</td>';
	print '<td>'.svcwarranty_status_badge($object->warranty_status);
	if ($object->fk_warranty) {
		print ' &nbsp;<a href="'.DOL_URL_ROOT.'/custom/warrantysvc/warranty_card.php?id='.$object->fk_warranty.'">'
			.$langs->trans('ViewWarranty').'</a>';
	}
	print '</td></tr>';

	// Billable
	print '<tr><td>'.$langs->trans('Billable').'</td>';
	print '<td>';
	if ($action == 'edit' && $permwrite) {
		print '<input type="checkbox" name="billable" value="1"'.($object->billable ? ' checked' : '').'>';
	} else {
		print $object->billable ? yn($object->billable) : '<span class="opacitymedium">'.$langs->trans('No').'</span>';
	}
	print '</td></tr>';

	// Swap serials (shown for swap resolution types)
	if (in_array($object->resolution_type, array('swap_cross', 'swap_wait'))) {
		print '<tr><td>'.$langs->trans('SerialOut').'</td>';
		print '<td>';
		if ($action == 'edit' && $permwrite) {
			print '<input type="text" name="serial_out" class="minwidth150" value="'.dol_escape_htmltag($object->serial_out).'">';
		} else {
			print dol_escape_htmltag($object->serial_out);
		}
		print '</td></tr>';

		print '<tr><td>'.$langs->trans('SerialIn').'</td>';
		print '<td>';
		if ($action == 'edit' && $permwrite) {
			print '<input type="text" name="serial_in" class="minwidth150" value="'.dol_escape_htmltag($object->serial_in).'">';
		} else {
			print dol_escape_htmltag($object->serial_in);
		}
		print '</td></tr>';
	}

	// Outbound tracking
	if ($object->fk_shipment || in_array($object->resolution_type, array('swap_cross','swap_wait','component','component_return'))) {
		print '<tr><td>'.$langs->trans('OutboundTracking').'</td>';
		print '<td>';
		if ($action == 'edit' && $permwrite) {
			print '<input type="text" name="outbound_carrier" class="minwidth100" placeholder="'.$langs->trans('Carrier').'" value="'.dol_escape_htmltag($object->outbound_carrier).'">';
			print ' <input type="text" name="outbound_tracking" class="minwidth150" placeholder="'.$langs->trans('TrackingNumber').'" value="'.dol_escape_htmltag($object->outbound_tracking).'">';
		} else {
			if ($object->outbound_carrier || $object->outbound_tracking) {
				print dol_escape_htmltag($object->outbound_carrier);
				if ($object->outbound_carrier && $object->outbound_tracking) print ' &mdash; ';
				print dol_escape_htmltag($object->outbound_tracking);
			}
			if ($object->fk_shipment) {
				print ' <a href="'.DOL_URL_ROOT.'/expedition/card.php?id='.$object->fk_shipment.'">'
					.'['.$langs->trans('ViewShipment').']</a>';
			}
		}
		print '</td></tr>';
	}

	// Return tracking
	if (in_array($object->resolution_type, array('swap_cross','swap_wait','component_return'))) {
		print '<tr><td>'.$langs->trans('DateReturnExpected').'</td>';
		print '<td>';
		if ($action == 'edit' && $permwrite) {
			print $form->selectDate($object->date_return_expected ? $object->date_return_expected : -1, 'date_return_expected', 0, 0, 1, '', 1, 1);
		} else {
			print dol_print_date($object->date_return_expected, 'day');
		}
		print '</td></tr>';

		if ($object->date_return_received) {
			print '<tr><td>'.$langs->trans('DateReturnReceived').'</td>';
			print '<td>'.dol_print_date($object->date_return_received, 'day').'</td></tr>';
		}

		if ($object->return_carrier || $object->return_tracking) {
			print '<tr><td>'.$langs->trans('ReturnTracking').'</td>';
			print '<td>'.dol_escape_htmltag($object->return_carrier);
			if ($object->return_carrier && $object->return_tracking) print ' &mdash; ';
			print dol_escape_htmltag($object->return_tracking).'</td></tr>';
		}
	}

	print '</table>';
	print '</div>'; // fichehalfright
	print '</div>'; // fichecenter

	// ---- Action buttons ----
	print '<div class="tabsAction">';

	if ($action == 'edit') {
		print '<input type="submit" class="butAction" value="'.$langs->trans('Save').'">';
		print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" class="butActionDelete">'.$langs->trans('Cancel').'</a>';
	} else {
		$s = $object->status;

		// Edit button available on draft/validated/in-progress
		if ($permwrite && in_array($s, array(SvcRequest::STATUS_DRAFT, SvcRequest::STATUS_VALIDATED, SvcRequest::STATUS_IN_PROGRESS, SvcRequest::STATUS_AWAIT_RETURN))) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit&token='.newToken().'" class="butAction">'.$langs->trans('Modify').'</a>';
		}

		if ($s == SvcRequest::STATUS_DRAFT && $permvalidate) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_validate&token='.newToken().'" class="butAction">'.$langs->trans('ValidateSvcRequest').'</a>';
		}
		if ($s == SvcRequest::STATUS_VALIDATED && $permwrite) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_setinprogress&token='.newToken().'" class="butAction">'.$langs->trans('SetInProgress').'</a>';
		}
		if ($s == SvcRequest::STATUS_IN_PROGRESS && $permwrite) {
			if (isModEnabled('expedition')) {
				print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=send_replacement&token='.newToken().'" class="butAction">'.$langs->trans('SendReplacement').'</a>';
			}
			if (isModEnabled('ficheinter') && !$object->fk_intervention) {
				print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=create_intervention&token='.newToken().'" class="butAction">'.$langs->trans('CreateIntervention').'</a>';
			}
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_resolve&token='.newToken().'" class="butAction">'.$langs->trans('MarkResolved').'</a>';
		}
		if ($s == SvcRequest::STATUS_AWAIT_RETURN && $permwrite) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=send_reminder&token='.newToken().'" class="butAction">'.$langs->trans('SendReminder').'</a>';
			if (isModEnabled('facture')) {
				print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=invoice_nonreturn&token='.newToken().'" class="butAction">'.$langs->trans('InvoiceNonReturn').'</a>';
			}
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_resolve&token='.newToken().'" class="butAction">'.$langs->trans('MarkResolved').'</a>';
		}
		if (in_array($s, array(SvcRequest::STATUS_RESOLVED, SvcRequest::STATUS_IN_PROGRESS, SvcRequest::STATUS_AWAIT_RETURN)) && $permclose) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_close&token='.newToken().'" class="butAction">'.$langs->trans('CloseSvcRequest').'</a>';
		}
		if (in_array($s, array(SvcRequest::STATUS_RESOLVED, SvcRequest::STATUS_CLOSED)) && $permclose) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_reopen&token='.newToken().'" class="butActionDelete">'.$langs->trans('ReopenSvcRequest').'</a>';
		}
		if (in_array($s, array(SvcRequest::STATUS_DRAFT, SvcRequest::STATUS_VALIDATED)) && $permwrite) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_cancel&token='.newToken().'" class="butActionDelete">'.$langs->trans('CancelSvcRequest').'</a>';
		}
		if ($s == SvcRequest::STATUS_DRAFT && $permdelete) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_delete&token='.newToken().'" class="butActionDelete">'.$langs->trans('Delete').'</a>';
		}
	}

	print '</div>'; // tabsAction

	// Wrap form for edit mode
	if ($action == 'edit') {
		print '<form action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" method="POST">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update">';
	}

	// ---- Component lines section ----
	print '<a name="components"></a>';
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Product').'</td>';
	print '<td>'.$langs->trans('Description').'</td>';
	print '<td class="center">'.$langs->trans('Qty').'</td>';
	print '<td>'.$langs->trans('Type').'</td>';
	print '<td class="center">'.$langs->trans('Status').'</td>';
	if ($permwrite && in_array($object->status, array(SvcRequest::STATUS_VALIDATED, SvcRequest::STATUS_IN_PROGRESS, SvcRequest::STATUS_AWAIT_RETURN))) {
		print '<td></td>';
	}
	print '</tr>';

	foreach ($object->lines as $line) {
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$prod = new Product($db);
		$prod->fetch($line->fk_product);

		$shipped_label = $line->shipped ? '<span class="badge status6">'.$langs->trans('Shipped').'</span>' : '<span class="badge status0">'.$langs->trans('Pending').'</span>';

		print '<tr class="oddeven">';
		print '<td><a href="'.DOL_URL_ROOT.'/product/card.php?id='.$prod->id.'">'
			.dol_escape_htmltag($prod->ref).'</a></td>';
		print '<td>'.dol_escape_htmltag($line->description).'</td>';
		print '<td class="center">'.dol_escape_htmltag($line->qty).'</td>';
		print '<td>'.dol_escape_htmltag($line->line_type).'</td>';
		print '<td class="center">'.$shipped_label.'</td>';
		if ($permwrite && in_array($object->status, array(SvcRequest::STATUS_VALIDATED, SvcRequest::STATUS_IN_PROGRESS, SvcRequest::STATUS_AWAIT_RETURN))) {
			print '<td class="center"><a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=deleteline&lineid='.$line->id.'&token='.newToken().'" class="reposition">'
				.img_picto($langs->trans('Delete'), 'delete').'</a></td>';
		}
		print '</tr>';
	}

	// Add line form
	if ($permwrite && in_array($object->status, array(SvcRequest::STATUS_VALIDATED, SvcRequest::STATUS_IN_PROGRESS, SvcRequest::STATUS_AWAIT_RETURN))) {
		print '<tr>';
		print '<td>'.$form->select_produits(0, 'product_id', '', 0, 0, 1, 2, '', 0, array(), 0, 0, 0, 'minwidth150').'</td>';
		print '<td><input type="text" name="line_desc" class="minwidth150" placeholder="'.$langs->trans('Description').'"></td>';
		print '<td class="center"><input type="number" name="qty" value="1" min="1" style="width:50px"></td>';
		print '<td>';
		$line_types = array(
			'component_out' => $langs->trans('ComponentOut'),
			'component_in'  => $langs->trans('ComponentIn'),
		);
		print Form::selectarray('line_type', $line_types, 'component_out', 0, 0, 0, '', 0, 0, 0, '', 'flat');
		print '</td>';
		print '<td colspan="2"><input type="submit" name="addline" class="button small" value="'.$langs->trans('Add').'"></td>';
		print '</tr>';
	}

	print '</table>';
	print '</div>';

	// ---- Linked objects ----
	$object->fetchObjectLinked();
	print '<br>';
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="4">'.$langs->trans('SectionLinkedObjects').'</td></tr>';

	if ($object->fk_shipment) {
		print '<tr class="oddeven"><td>'.$langs->trans('Shipment').'</td>';
		print '<td><a href="'.DOL_URL_ROOT.'/expedition/card.php?id='.$object->fk_shipment.'">'.img_picto('', 'shipping').' #'.$object->fk_shipment.'</a></td></tr>';
	}
	if ($object->fk_reception) {
		print '<tr class="oddeven"><td>'.$langs->trans('Reception').'</td>';
		print '<td><a href="'.DOL_URL_ROOT.'/reception/card.php?id='.$object->fk_reception.'">'.img_picto('', 'reception').' #'.$object->fk_reception.'</a></td></tr>';
	}
	if ($object->fk_intervention) {
		print '<tr class="oddeven"><td>'.$langs->trans('Intervention').'</td>';
		print '<td><a href="'.DOL_URL_ROOT.'/fichinter/card.php?id='.$object->fk_intervention.'">'.img_picto('', 'intervention').' #'.$object->fk_intervention.'</a></td></tr>';
	}
	if ($object->fk_facture) {
		print '<tr class="oddeven"><td>'.$langs->trans('Invoice').'</td>';
		print '<td><a href="'.DOL_URL_ROOT.'/compta/facture/card.php?id='.$object->fk_facture.'">'.img_picto('', 'bill').' #'.$object->fk_facture.'</a></td></tr>';
	}
	if ($object->fk_pbxcall) {
		print '<tr class="oddeven"><td>'.$langs->trans('SourceCall').'</td>';
		print '<td>#'.$object->fk_pbxcall.'</td></tr>';
	}

	print '</table>';
	print '</div>';

	if ($action == 'edit') {
		print '</form>';
	}

	print dol_get_fiche_end();
}

// Add lang strings used inline
$langs->load('other');

llxFooter();
$db->close();
