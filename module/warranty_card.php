<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    warranty_card.php
 * \ingroup warrantysvc
 * \brief   Warranty create / view / edit card
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))       { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarranty.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcrequest.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarrantytype.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/lib/warrantysvc.lib.php';

$langs->loadLangs(array('warrantysvc@warrantysvc', 'companies', 'products'));

$id     = GETPOST('id', 'int');
$ref    = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');

$object = new SvcWarranty($db);

if ($id > 0 || $ref) {
	$result = $object->fetch($id, $ref);
	if ($result <= 0) {
		dol_print_error($db, $object->error);
		exit;
	}
}

$permread   = $user->hasRight('warrantysvc', 'svcwarranty', 'read');
$permwrite  = $user->hasRight('warrantysvc', 'svcwarranty', 'write');
$permdelete = $user->hasRight('warrantysvc', 'svcwarranty', 'delete');

if (!$permread) { accessforbidden(); }

/*
 * Actions
 */
if ($cancel) { $action = ''; }

// ---- CREATE ----
if ($action == 'add' && $permwrite) {
	$object->fk_soc         = GETPOST('fk_soc', 'int');
	$object->fk_product     = GETPOST('fk_product', 'int');
	$object->serial_number  = GETPOST('serial_number', 'alpha');
	$object->warranty_type  = GETPOST('warranty_type', 'alpha');
	$object->start_date     = dol_mktime(12, 0, 0, GETPOST('start_datemonth', 'int'), GETPOST('start_dateday', 'int'), GETPOST('start_dateyear', 'int'));
	$object->coverage_days= GETPOST('coverage_days', 'int');
	$object->coverage_terms = GETPOST('coverage_terms', 'restricthtml');
	$object->exclusions     = GETPOST('exclusions', 'restricthtml');
	$object->fk_commande    = GETPOST('fk_commande', 'int');
	$object->fk_expedition  = GETPOST('fk_expedition', 'int');
	$object->note_public    = GETPOST('note_public', 'restricthtml');
	$object->note_private   = GETPOST('note_private', 'restricthtml');

	// Manual expiry overrides computed one
	$manual_expiry = dol_mktime(12, 0, 0, GETPOST('expiry_datemonth', 'int'), GETPOST('expiry_dateday', 'int'), GETPOST('expiry_dateyear', 'int'));
	if ($manual_expiry) {
		$object->expiry_date = $manual_expiry;
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

// ---- UPDATE ----
if ($action == 'update' && $permwrite) {
	$object->fk_soc         = GETPOST('fk_soc', 'int');
	$object->fk_product     = GETPOST('fk_product', 'int');
	$object->serial_number  = GETPOST('serial_number', 'alpha');
	$object->warranty_type  = GETPOST('warranty_type', 'alpha');
	$object->start_date     = dol_mktime(12, 0, 0, GETPOST('start_datemonth', 'int'), GETPOST('start_dateday', 'int'), GETPOST('start_dateyear', 'int'));
	$object->coverage_days= GETPOST('coverage_days', 'int');
	$object->coverage_terms = GETPOST('coverage_terms', 'restricthtml');
	$object->exclusions     = GETPOST('exclusions', 'restricthtml');
	$object->fk_commande    = GETPOST('fk_commande', 'int');
	$object->fk_expedition  = GETPOST('fk_expedition', 'int');
	$object->note_public    = GETPOST('note_public', 'restricthtml');
	$object->note_private   = GETPOST('note_private', 'restricthtml');

	$manual_expiry = dol_mktime(12, 0, 0, GETPOST('expiry_datemonth', 'int'), GETPOST('expiry_dateday', 'int'), GETPOST('expiry_dateyear', 'int'));
	if ($manual_expiry) {
		$object->expiry_date = $manual_expiry;
	} elseif ($object->coverage_days && $object->start_date) {
		$object->expiry_date = dol_time_plus_duree($object->start_date, $object->coverage_days, 'd');
	}

	$result = $object->update($user);
	if ($result > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'edit';
	}
}

// ---- VOID ----
if ($action == 'confirm_void' && GETPOST('confirm', 'alpha') == 'yes' && $permwrite) {
	$object->status = SvcWarranty::STATUS_VOIDED;
	$object->update($user);
	setEventMessages($langs->trans('WarrantyVoided'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

// ---- DELETE ----
if ($action == 'confirm_delete' && GETPOST('confirm', 'alpha') == 'yes' && $permdelete) {
	$result = $object->delete($user);
	if ($result > 0) {
		header('Location: '.DOL_URL_ROOT.'/custom/warrantysvc/warranty_list.php');
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

$form        = new Form($db);
$formcompany = new FormCompany($db);

/*
 * View
 */
llxHeader('', $langs->trans('Warranty'), '');

// ============================================================
// CREATE FROM SHIPMENT FORM
// ============================================================
if ($action == 'create_from_shipment') {
	require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
	require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

	$fk_expedition_src = GETPOST('fk_expedition_src', 'int');

	print load_fiche_titre($langs->trans('NewWarrantyFromShipment'), '', 'bill');
	print '<p><a href="'.$_SERVER['PHP_SELF'].'?action=create">'.img_picto('', 'back', 'class="paddingright"').$langs->trans('SwitchToManualWarranty').'</a></p>';

	// Build validated shipment list
	$sql_exp  = "SELECT e.rowid, e.ref, s.nom as customer_name";
	$sql_exp .= " FROM ".MAIN_DB_PREFIX."expedition e";
	$sql_exp .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = e.fk_soc";
	$sql_exp .= " WHERE e.fk_statut >= 1";
	$sql_exp .= " AND e.entity IN (".getEntity('expedition').")";
	$sql_exp .= " ORDER BY e.rowid DESC LIMIT 500";
	$res_exp  = $db->query($sql_exp);
	$exp_options = array('' => '— '.$langs->trans('SelectShipment').' —');
	if ($res_exp) {
		while ($obj_exp = $db->fetch_object($res_exp)) {
			$exp_options[$obj_exp->rowid] = $obj_exp->ref.($obj_exp->customer_name ? ' — '.$obj_exp->customer_name : '');
		}
	}

	// Step 1 — shipment selector (GET reload)
	print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="action" value="create_from_shipment">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td colspan="2">'.$langs->trans('SelectShipmentStep').'</td></tr>';
	print '<tr class="oddeven"><td class="fieldrequired titlefieldcreate">'.$langs->trans('Shipment').'</td>';
	print '<td>';
	print Form::selectarray('fk_expedition_src', $exp_options, $fk_expedition_src, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300');
	print ' <input type="submit" class="button small" value="'.$langs->trans('Load').'">';
	print '</td></tr>';
	print '</table>';
	print '</form>';

	// Step 2 — warranty form (only once a shipment is selected)
	if ($fk_expedition_src > 0) {
		$expedition = new Expedition($db);
		$presoc     = ($expedition->fetch($fk_expedition_src) > 0) ? (int) $expedition->socid : 0;

		// Serials in this shipment that don't yet have a warranty
		$sql_ser  = "SELECT edl.batch as serial_number, ed.fk_product,";
		$sql_ser .= " p.ref as product_ref, p.label as product_label";
		$sql_ser .= " FROM ".MAIN_DB_PREFIX."expeditiondet_batch edl";
		$sql_ser .= " JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.rowid = edl.fk_expeditiondet";
		$sql_ser .= " JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ed.fk_product";
		$sql_ser .= " WHERE ed.fk_expedition = ".((int) $fk_expedition_src);
		$sql_ser .= " AND edl.batch IS NOT NULL AND edl.batch != ''";
		$sql_ser .= " AND edl.batch NOT IN (";
		$sql_ser .= "   SELECT serial_number FROM ".MAIN_DB_PREFIX."svc_warranty";
		$sql_ser .= "   WHERE serial_number IS NOT NULL AND serial_number != ''";
		$sql_ser .= "   AND entity IN (".getEntity('svcwarranty').")";
		$sql_ser .= " )";
		$res_ser = $db->query($sql_ser);

		$serial_options     = array('' => '— '.$langs->trans('SelectSerial').' —');
		$serial_product_map = '{';
		if ($res_ser) {
			while ($obj_ser = $db->fetch_object($res_ser)) {
				$opt_label = $obj_ser->serial_number.' — '.$obj_ser->product_ref.($obj_ser->product_label ? ' '.$obj_ser->product_label : '');
				$serial_options[$obj_ser->serial_number] = $opt_label;
				$serial_product_map .= '"'.dol_escape_js($obj_ser->serial_number).'":{'
					.'"fk_product":'.((int) $obj_ser->fk_product).','
					.'"label":"'.dol_escape_js($obj_ser->product_ref.($obj_ser->product_label ? ' — '.$obj_ser->product_label : '')).'"'
					.'},';
			}
		}
		$serial_product_map = rtrim($serial_product_map, ',').'}';

		if (count($serial_options) <= 1) {
			print '<div class="warning" style="margin-top:10px">'.$langs->trans('NoUncoveredSerialsInShipment').'</div>';
		} else {
			$wtype_items       = SvcWarrantyType::fetchAllForForm($db);
			$wtype_options     = array('' => '— '.$langs->trans('NoPredefinedType').' —');
			$wtype_defaults_js = '{';
			foreach ($wtype_items as $wt) {
				$wtype_options[$wt->code] = dol_escape_htmltag($wt->label);
				$wtype_defaults_js .= '"'.dol_escape_js($wt->code).'":'.((int) $wt->default_coverage_days).',';
			}
			$wtype_defaults_js = rtrim($wtype_defaults_js, ',').'}';
			$selected_wtype    = GETPOST('warranty_type', 'alpha');
			$initial_days      = 365;
			foreach ($wtype_items as $wt) {
				if ($wt->code === $selected_wtype) { $initial_days = (int) $wt->default_coverage_days; break; }
			}
			$days_disabled = ($selected_wtype ? ' disabled style="opacity:0.5"' : '');

			print '<br>';
			print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="add">';
			print '<input type="hidden" name="fk_soc" value="'.((int) $presoc).'">';
			print '<input type="hidden" name="fk_expedition" value="'.((int) $fk_expedition_src).'">';
			print '<input type="hidden" id="fk_product" name="fk_product" value="">';

			print dol_get_fiche_head(array(), '', '', -1);
			print '<table class="border centpercent tableforfieldcreate">';

			// Customer — display only
			$soc = new Societe($db);
			print '<tr><td class="titlefieldcreate">'.$langs->trans('Customer').'</td>';
			print '<td>';
			if ($presoc && $soc->fetch($presoc) > 0) {
				print $soc->getNomUrl(1);
			}
			print '</td></tr>';

			// Serial number dropdown (only uncovered serials from this shipment)
			print '<tr><td class="fieldrequired">'.$form->textwithpicto($langs->trans('SerialNumber'), $langs->trans('TooltipWarrantySerial')).'</td>';
			print '<td>';
			print Form::selectarray('serial_number', $serial_options, dol_escape_htmltag(GETPOST('serial_number', 'alpha')), 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth300', 0, '', '', true);
			print '</td></tr>';

			// Product — auto-filled by JS when serial is chosen
			print '<tr><td>'.$langs->trans('Product').'</td>';
			print '<td><span id="product_label" class="opacitymedium">'.$langs->trans('AutoFilledFromSerial').'</span></td></tr>';

			// Warranty type
			print '<tr><td>'.$form->textwithpicto($langs->trans('WarrantyType'), $langs->trans('TooltipWarrantyType')).'</td>';
			print '<td>';
			print Form::selectarray('warranty_type', $wtype_options, $selected_wtype, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth200', 0, '', '', true);
			print '</td></tr>';

			// Start date
			print '<tr><td class="fieldrequired">'.$langs->trans('StartDate').'</td>';
			print '<td>';
			print $form->selectDate(dol_now(), 'start_date', 0, 0, 0, 'formship', 1, 1);
			print '</td></tr>';

			// Coverage days
			print '<tr><td>'.$form->textwithpicto($langs->trans('CoverageDays'), $langs->trans('TooltipCoverageDays')).'</td>';
			print '<td>';
			print '<input type="number" id="coverage_days" name="coverage_days" value="'.$initial_days.'" class="flat width75" min="1" max="3650"'.$days_disabled.'>';
			print ' '.$langs->trans('Days');
			print ' &nbsp;<span id="coverage_auto_hint" class="opacitymedium"'.($selected_wtype ? '' : ' style="display:none"').'>'.$langs->trans('CoverageFromType').'</span>';
			print '</td></tr>';

			// Notes
			print '<tr><td>'.$form->textwithpicto($langs->trans('NotePublic'), $langs->trans('TooltipNotePublic')).'</td>';
			print '<td><textarea name="note_public" class="flat" rows="3" style="width:90%" placeholder="'.$langs->trans('NotePublicPlaceholder').'"></textarea></td></tr>';

			print '</table>';
			print dol_get_fiche_end();

			print '<div class="center">';
			print '<input type="submit" id="btn_save_ship" class="button button-save" name="add" value="'.$langs->trans('Save').'" disabled>';
			print ' &nbsp; ';
			print '<a class="button button-cancel" href="'.$_SERVER['PHP_SELF'].'?action=create_from_shipment">'.$langs->trans('Cancel').'</a>';
			print '</div>';

			print '</form>';

			print '<script>(function(){
	var smap  = '.$serial_product_map.';
	var wtdef = '.$wtype_defaults_js.';
	var selSer  = document.querySelector("[name=serial_number]");
	var selType = document.querySelector("[name=warranty_type]");
	var inpProd = document.getElementById("fk_product");
	var lblProd = document.getElementById("product_label");
	var inpCov  = document.getElementById("coverage_days");
	var hint    = document.getElementById("coverage_auto_hint");
	var btn     = document.getElementById("btn_save_ship");
	function syncSerial(){
		var s = selSer ? selSer.value : "";
		if(s && smap[s]){
			if(inpProd) inpProd.value = smap[s].fk_product;
			if(lblProd) lblProd.textContent = smap[s].label;
			if(btn)     btn.disabled = false;
		} else {
			if(inpProd) inpProd.value = "";
			if(lblProd) lblProd.textContent = "'.dol_escape_js($langs->trans('AutoFilledFromSerial')).'";
			if(btn)     btn.disabled = true;
		}
	}
	function syncType(){
		var code = selType ? selType.value : "";
		if(code && wtdef[code] !== undefined){
			inpCov.value = wtdef[code]; inpCov.disabled = true; inpCov.style.opacity = "0.5";
			if(hint) hint.style.display = "";
		} else {
			inpCov.disabled = false; inpCov.style.opacity = "";
			if(hint) hint.style.display = "none";
		}
	}
	if(selSer)  selSer.addEventListener("change", syncSerial);
	if(selType) selType.addEventListener("change", syncType);
	syncSerial(); syncType();
})();</script>';
		}
	}

	llxFooter();
	$db->close();
	exit;
}

// ============================================================
// CREATE FORM
// ============================================================
if ($action == 'create') {
	// Build map of unassigned serials grouped by product_id for the JS picker
	$sql_lot  = "SELECT pl.batch AS serial_number, pl.fk_product";
	$sql_lot .= " FROM ".MAIN_DB_PREFIX."product_lot pl";
	$sql_lot .= " WHERE pl.batch IS NOT NULL AND pl.batch != ''";
	$sql_lot .= " AND pl.entity IN (".getEntity('product').")";
	$sql_lot .= " AND pl.batch NOT IN (";
	$sql_lot .= "   SELECT w.serial_number FROM ".MAIN_DB_PREFIX."svc_warranty w";
	$sql_lot .= "   WHERE w.serial_number IS NOT NULL AND w.serial_number != ''";
	$sql_lot .= "   AND w.entity IN (".getEntity('svcwarranty').")";
	$sql_lot .= " )";
	$sql_lot .= " ORDER BY pl.fk_product, pl.batch";
	$res_lot = $db->query($sql_lot);
	$serials_by_product = array();
	if ($res_lot) {
		while ($row_lot = $db->fetch_object($res_lot)) {
			$pid = (int) $row_lot->fk_product;
			if (!isset($serials_by_product[$pid])) {
				$serials_by_product[$pid] = array();
			}
			$serials_by_product[$pid][] = $row_lot->serial_number;
		}
	}
	$serial_map_js = json_encode($serials_by_product);
	$prev_serial   = dol_escape_js(GETPOST('serial_number', 'alpha'));

	print load_fiche_titre($langs->trans('NewWarranty'), '', 'bill');
	print '<p><a href="'.$_SERVER['PHP_SELF'].'?action=create_from_shipment">'.img_picto('', 'shipment', 'class="paddingright"').$langs->trans('CreateWarrantyFromShipment').'</a></p>';

	print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print dol_get_fiche_head(array(), '', '', -1);

	print '<table class="border centpercent tableforfieldcreate">';

	// Customer
	print '<tr><td class="fieldrequired">'.$langs->trans('Customer').'</td>';
	print '<td>';
	print $formcompany->select_company(GETPOST('fk_soc', 'int'), 'fk_soc', '', $langs->trans('SelectThird'), 0, 0, null, 0, 'minwidth300 maxwidth500 widthcentpercentminusxx');
	print '</td></tr>';

	// Product
	print '<tr><td class="fieldrequired">'.$langs->trans('Product').'</td>';
	print '<td>';
	$form->select_produits(GETPOST('fk_product', 'int'), 'fk_product', '', 0, 0, 1, 2, '', 0, array(), 0, 1, 0, 'minwidth300');
	print '</td></tr>';

	// Serial number — populated dynamically from product_lot based on selected product
	print '<tr><td class="fieldrequired">'.$form->textwithpicto($langs->trans('SerialNumber'), $langs->trans('TooltipWarrantySerial')).'</td>';
	print '<td>';
	print '<select name="serial_number" id="manual_serial_select" class="flat minwidth200" disabled>';
	print '<option value="">'.$langs->trans('SelectProductFirst').'</option>';
	print '</select>';
	print '</td></tr>';

	print '<script>(function(){
	var smap = '.$serial_map_js.';
	var noSelTxt  = '.json_encode($langs->trans('SelectProductFirst')).';
	var noneTxt   = '.json_encode($langs->trans('NoSerialsAvailable')).';
	var prevVal   = "'.dol_escape_js($prev_serial).'";

	var prodSel = document.getElementById("fk_product");
	var serSel  = document.getElementById("manual_serial_select");

	function populateSerials() {
		var pid = prodSel ? parseInt(prodSel.value, 10) : 0;
		serSel.innerHTML = "";
		if (!pid) {
			var opt = document.createElement("option");
			opt.value = "";
			opt.textContent = noSelTxt;
			serSel.appendChild(opt);
			serSel.disabled = true;
			return;
		}
		var serials = (smap[pid] && smap[pid].length) ? smap[pid] : null;
		if (!serials) {
			var opt = document.createElement("option");
			opt.value = "";
			opt.textContent = noneTxt;
			serSel.appendChild(opt);
			serSel.disabled = true;
			return;
		}
		var blank = document.createElement("option");
		blank.value = "";
		blank.textContent = "— '.dol_escape_js($langs->trans('SelectSerial')).' —";
		serSel.appendChild(blank);
		serials.forEach(function(s) {
			var opt = document.createElement("option");
			opt.value = s;
			opt.textContent = s;
			serSel.appendChild(opt);
		});
		serSel.disabled = false;
		if (prevVal) { serSel.value = prevVal; prevVal = ""; }
	}

	if (prodSel) {
		// Support both native select and Select2/jQuery-enhanced dropdowns
		prodSel.addEventListener("change", populateSerials);
		if (typeof jQuery !== "undefined") {
			jQuery(prodSel).on("change", populateSerials);
		}
		// If product already selected on load (e.g. after failed submit), populate immediately
		if (prodSel.value) { populateSerials(); }
	}
})();</script>';

	// Warranty type — load from DB
	$wtype_items   = SvcWarrantyType::fetchAllForForm($db);
	$wtype_options = array('' => '— '.$langs->trans('NoPredefinedType').' —');
	$wtype_defaults_js = '{';
	foreach ($wtype_items as $wt) {
		$wtype_options[$wt->code] = dol_escape_htmltag($wt->label);
		$wtype_defaults_js .= '"'.dol_escape_js($wt->code).'":'.((int) $wt->default_coverage_days).',';
	}
	$wtype_defaults_js = rtrim($wtype_defaults_js, ',').'}';

	$selected_wtype    = GETPOST('warranty_type', 'alpha');
	$initial_days      = GETPOST('coverage_days', 'int');
	if (!$initial_days) {
		$initial_days = 365;
		foreach ($wtype_items as $wt) {
			if ($wt->code === $selected_wtype) { $initial_days = (int) $wt->default_coverage_days; break; }
		}
	}

	print '<tr><td>'.$form->textwithpicto($langs->trans('WarrantyType'), $langs->trans('TooltipWarrantyType')).'</td>';
	print '<td>';
	print Form::selectarray('warranty_type', $wtype_options, $selected_wtype, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth200', 0, '', '', true);
	print '</td></tr>';

	// Start date
	print '<tr><td class="fieldrequired">'.$langs->trans('StartDate').'</td>';
	print '<td>';
	print $form->selectDate(GETPOST('start_date', 'int') ? GETPOST('start_date', 'int') : dol_now(), 'start_date', 0, 0, 0, 'formcreate', 1, 1);
	print '</td></tr>';

	// Coverage months — disabled when a type is selected (auto-filled by JS)
	$days_disabled = ($selected_wtype ? ' disabled style="opacity:0.5"' : '');
	print '<tr><td>'.$form->textwithpicto($langs->trans('CoverageDays'), $langs->trans('TooltipCoverageDays')).'</td>';
	print '<td>';
	print '<input type="number" id="coverage_days" name="coverage_days" value="'.$initial_days.'" class="flat width75" min="1" max="3650"'.$days_disabled.'>';
	print ' '.$langs->trans('Days');
	print ' &nbsp;<span id="coverage_auto_hint" class="opacitymedium"'.($selected_wtype ? '' : ' style="display:none"').'>'.$langs->trans('CoverageFromType').'</span>';
	print ' <span id="coverage_manual_hint" class="opacitymedium"'.($selected_wtype ? ' style="display:none"' : '').'>'.$langs->trans('ExpiryAutoComputed').'</span>';
	print '</td></tr>';

	// Inline JS for coverage auto-fill
	print '<script>
(function(){
	var defaults = '.$wtype_defaults_js.';
	var sel = document.getElementById("warranty_type");
	var cm  = document.getElementById("coverage_days");
	var autoHint   = document.getElementById("coverage_auto_hint");
	var manualHint = document.getElementById("coverage_manual_hint");
	function sync(){
		var code = sel ? sel.value : "";
		if(code && defaults[code] !== undefined){
			cm.value    = defaults[code];
			cm.disabled = true;
			cm.style.opacity = "0.5";
			if(autoHint)   autoHint.style.display   = "";
			if(manualHint) manualHint.style.display = "none";
		} else {
			cm.disabled = false;
			cm.style.opacity = "";
			if(autoHint)   autoHint.style.display   = "none";
			if(manualHint) manualHint.style.display = "";
		}
	}
	if(sel){ sel.addEventListener("change", sync); }
})();
</script>';

	// Manual expiry override
	print '<tr><td>'.$form->textwithpicto($langs->trans('ExpiryDateOverride'), $langs->trans('TooltipExpiryDateOverride')).'</td>';
	print '<td>';
	print $form->selectDate('', 'expiry_date', 0, 0, 1, 'formcreate', 1, 0);
	print '</td></tr>';

	// Coverage terms
	print '<tr><td class="tdtop">'.$form->textwithpicto($langs->trans('CoverageTerms'), $langs->trans('TooltipCoverageTerms')).'</td>';
	print '<td>';
	require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
	$doleditor = new DolEditor('coverage_terms', GETPOST('coverage_terms', 'restricthtml'), '', 100, 'dolibarr_notes', '', false, true, getDolGlobalInt('FCKEDITOR_ENABLE_DETAILS'), ROWS_4, '90%');
	$doleditor->Create();
	print '</td></tr>';

	// Exclusions
	print '<tr><td class="tdtop">'.$form->textwithpicto($langs->trans('Exclusions'), $langs->trans('TooltipExclusions')).'</td>';
	print '<td>';
	$doleditor2 = new DolEditor('exclusions', GETPOST('exclusions', 'restricthtml'), '', 100, 'dolibarr_notes', '', false, true, getDolGlobalInt('FCKEDITOR_ENABLE_DETAILS'), ROWS_3, '90%');
	$doleditor2->Create();
	print '</td></tr>';

	// Origin order
	print '<tr><td>'.$form->textwithpicto($langs->trans('OriginOrder'), $langs->trans('TooltipOriginOrder')).'</td>';
	print '<td>';
	print '<input type="number" name="fk_commande" value="'.((int) GETPOST('fk_commande', 'int')).'" class="flat width100">';
	print '</td></tr>';

	// Notes
	print '<tr><td>'.$form->textwithpicto($langs->trans('NotePublic'), $langs->trans('TooltipNotePublic')).'</td>';
	print '<td><textarea name="note_public" class="flat" rows="3" style="width:90%" placeholder="'.$langs->trans('NotePublicPlaceholder').'">'.dol_escape_htmltag(GETPOST('note_public', 'restricthtml'), 1).'</textarea></td></tr>';

	print '</table>';

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button button-save" name="add" value="'.$langs->trans('Save').'">';
	print ' &nbsp; ';
	print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
	print '</div>';

	print '</form>';

	llxFooter();
	$db->close();
	exit;
}

// ============================================================
// VIEW / EDIT
// ============================================================
if (empty($object->id)) {
	dol_print_error($db, 'Object not found');
	exit;
}

$head = svcwarranty_prepare_head($object);

// Determine live status
$now           = dol_now();
$display_status = $object->status;
if ($object->status != SvcWarranty::STATUS_VOIDED) {
	if (!empty($object->expiry_date) && $object->expiry_date < $now) {
		$display_status = SvcWarranty::STATUS_EXPIRED;
	} else {
		$display_status = SvcWarranty::STATUS_ACTIVE;
	}
}

// --- Confirm dialogs ---
if ($action == 'void') {
	print $form->formconfirm(
		$_SERVER['PHP_SELF'].'?id='.$object->id,
		$langs->trans('VoidWarranty'),
		$langs->trans('ConfirmVoidWarranty'),
		'confirm_void',
		'',
		0,
		1
	);
}

if ($action == 'delete') {
	print $form->formconfirm(
		$_SERVER['PHP_SELF'].'?id='.$object->id,
		$langs->trans('DeleteWarranty'),
		$langs->trans('ConfirmDeleteWarranty'),
		'confirm_delete',
		'',
		0,
		1
	);
}

print dol_get_fiche_head($head, 'card', $langs->trans('Warranty'), -1, 'bill');

$linkback = '<a href="'.DOL_URL_ROOT.'/custom/warrantysvc/warranty_list.php">'.$langs->trans('BackToList').'</a>';

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', svcwarranty_status_badge($display_status));

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<table class="border centpercent tableforfield">';

// Customer
print '<tr><td class="titlefield">'.$langs->trans('Customer').'</td>';
print '<td>';
if ($action == 'edit') {
	print $formcompany->select_company($object->fk_soc, 'fk_soc', '', '', 0, 0, null, 0, 'minwidth200 maxwidth400');
} else {
	$soc = new Societe($db);
	if ($soc->fetch($object->fk_soc) > 0) {
		print $soc->getNomUrl(1);
	}
}
print '</td></tr>';

// Product
print '<tr><td>'.$langs->trans('Product').'</td>';
print '<td>';
if ($action == 'edit') {
	$form->select_produits($object->fk_product, 'fk_product', '', 0, 0, 1, 2, '', 0, array(), 0, 1, 0, 'minwidth200');
} else {
	$product = new Product($db);
	if ($product->fetch($object->fk_product) > 0) {
		print $product->getNomUrl(1);
	}
}
print '</td></tr>';

// Serial number
print '<tr><td>'.$form->textwithpicto($langs->trans('SerialNumber'), $langs->trans('TooltipWarrantySerial')).'</td>';
print '<td>';
if ($action == 'edit') {
	print '<input type="text" name="serial_number" value="'.dol_escape_htmltag($object->serial_number).'" class="flat minwidth200">';
} else {
	print dol_escape_htmltag($object->serial_number);
}
print '</td></tr>';

// Warranty type
print '<tr><td>'.$form->textwithpicto($langs->trans('WarrantyType'), $langs->trans('TooltipWarrantyType')).'</td>';
print '<td>';
if ($action == 'edit') {
	$wtype_items_edit   = SvcWarrantyType::fetchAllForForm($db);
	$wtype_options_edit = array('' => '— '.$langs->trans('NoPredefinedType').' —');
	$wtype_defaults_edit_js = '{';
	foreach ($wtype_items_edit as $wt) {
		$wtype_options_edit[$wt->code] = dol_escape_htmltag($wt->label);
		$wtype_defaults_edit_js .= '"'.dol_escape_js($wt->code).'":'.((int) $wt->default_coverage_days).',';
	}
	$wtype_defaults_edit_js = rtrim($wtype_defaults_edit_js, ',').'}';
	print Form::selectarray('warranty_type', $wtype_options_edit, $object->warranty_type, 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth200', 0, '', '', true);
	print '<script>
(function(){
	var defaults = '.$wtype_defaults_edit_js.';
	var sel = document.getElementById("warranty_type");
	var cm  = document.getElementById("coverage_days");
	function sync(){
		var code = sel ? sel.value : "";
		if(code && defaults[code] !== undefined){
			cm.value = defaults[code]; cm.disabled = true; cm.style.opacity = "0.5";
		} else { cm.disabled = false; cm.style.opacity = ""; }
	}
	if(sel){ sel.addEventListener("change", sync); sync(); }
})();
</script>';
} else {
	print $object->warranty_type
		? dol_escape_htmltag(SvcWarrantyType::getLabelByCode($db, $object->warranty_type))
		: '<span class="opacitymedium">&mdash;</span>';
}
print '</td></tr>';

// Status
print '<tr><td>'.$langs->trans('Status').'</td>';
print '<td>'.svcwarranty_status_badge($display_status).'</td></tr>';

// Claim summary
print '<tr><td>'.$langs->trans('Claims').'</td>';
print '<td>'.((int) $object->claim_count);
if ($object->total_claimed_value > 0) {
	print ' &mdash; '.price($object->total_claimed_value, 0, $langs, 1, -1, -1, $conf->currency);
}
print '</td></tr>';

print '</table>';
print '</div>'; // fichehalfleft

print '<div class="fichehalfright">';
print '<table class="border centpercent tableforfield">';

// Start date
print '<tr><td class="titlefield">'.$langs->trans('StartDate').'</td>';
print '<td>';
if ($action == 'edit') {
	print $form->selectDate($object->start_date, 'start_date', 0, 0, 0, 'cardform', 1, 1);
} else {
	print dol_print_date($object->start_date, 'day');
}
print '</td></tr>';

// Coverage months
print '<tr><td>'.$form->textwithpicto($langs->trans('CoverageDays'), $langs->trans('TooltipCoverageDays')).'</td>';
print '<td>';
if ($action == 'edit') {
	$edit_days_disabled = ($object->warranty_type ? ' disabled style="opacity:0.5"' : '');
	print '<input type="number" id="coverage_days" name="coverage_days" value="'.((int) $object->coverage_days).'" class="flat width75" min="0" max="3650"'.$edit_days_disabled.'>';
	print ' '.$langs->trans('Days');
	print ' &nbsp;<span class="opacitymedium" id="coverage_type_hint"'.($object->warranty_type ? '' : ' style="display:none"').'>'.$langs->trans('CoverageFromType').'</span>';
} else {
	print $object->coverage_days ? ((int) $object->coverage_days).' '.$langs->trans('Days') : '<span class="opacitymedium">&mdash;</span>';
}
print '</td></tr>';

// Expiry date
print '<tr><td>'.$langs->trans('ExpiryDate').'</td>';
print '<td>';
if ($action == 'edit') {
	print $form->selectDate($object->expiry_date, 'expiry_date', 0, 0, 1, 'cardform', 1, 0);
	print ' <span class="opacitymedium">'.$langs->trans('ExpiryAutoComputed').'</span>';
} else {
	if ($object->expiry_date) {
		$expiry_ts = $object->expiry_date;
		if ($display_status == 'expired') {
			print '<span class="warning">'.dol_print_date($expiry_ts, 'day').'</span>';
		} elseif ($display_status == 'active' && $expiry_ts < dol_time_plus_duree($now, 30, 'd')) {
			print '<span class="opacitymediumhigh">'.dol_print_date($expiry_ts, 'day').'</span>';
			print ' &nbsp;<span class="badge badge-status4">'.$langs->trans('ExpiringSoon').'</span>';
		} else {
			print dol_print_date($expiry_ts, 'day');
		}
	} else {
		print '<span class="opacitymedium">&mdash;</span>';
	}
}
print '</td></tr>';

// Origin order
if ($object->fk_commande || $action == 'edit') {
	print '<tr><td>'.$form->textwithpicto($langs->trans('OriginOrder'), $langs->trans('TooltipOriginOrder')).'</td>';
	print '<td>';
	if ($action == 'edit') {
		print '<input type="number" name="fk_commande" value="'.((int) $object->fk_commande).'" class="flat width100">';
	} elseif ($object->fk_commande) {
		if (isModEnabled('commande')) {
			require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
			$commande = new Commande($db);
			if ($commande->fetch($object->fk_commande) > 0) {
				print $commande->getNomUrl(1);
			}
		} else {
			print $object->fk_commande;
		}
	}
	print '</td></tr>';
}

// Origin shipment
if ($object->fk_expedition || $action == 'edit') {
	print '<tr><td>'.$langs->trans('OriginShipment').'</td>';
	print '<td>';
	if ($action == 'edit') {
		print '<input type="number" name="fk_expedition" value="'.((int) $object->fk_expedition).'" class="flat width100">';
	} elseif ($object->fk_expedition) {
		if (isModEnabled('expedition')) {
			require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
			$expd = new Expedition($db);
			if ($expd->fetch($object->fk_expedition) > 0) {
				print $expd->getNomUrl(1);
			}
		} else {
			print $object->fk_expedition;
		}
	}
	print '</td></tr>';
}

print '</table>';
print '</div>'; // fichehalfright
print '</div>'; // fichecenter

// Coverage terms & exclusions
print '<div class="clearboth"></div>';
print '<div class="fichecenter">';

print '<div class="fichehalfleft">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield tdtop">'.$langs->trans('CoverageTerms').'</td>';
print '<td>';
if ($action == 'edit') {
	require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
	$doleditor = new DolEditor('coverage_terms', $object->coverage_terms, '', 120, 'dolibarr_notes', '', false, true, getDolGlobalInt('FCKEDITOR_ENABLE_DETAILS'), ROWS_4, '95%');
	$doleditor->Create();
} else {
	print dol_htmlentitiesbr($object->coverage_terms);
}
print '</td></tr>';
print '</table>';
print '</div>';

print '<div class="fichehalfright">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield tdtop">'.$langs->trans('Exclusions').'</td>';
print '<td>';
if ($action == 'edit') {
	require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
	$doleditor3 = new DolEditor('exclusions', $object->exclusions, '', 120, 'dolibarr_notes', '', false, true, getDolGlobalInt('FCKEDITOR_ENABLE_DETAILS'), ROWS_4, '95%');
	$doleditor3->Create();
} else {
	print dol_htmlentitiesbr($object->exclusions);
}
print '</td></tr>';
print '</table>';
print '</div>';

print '</div>'; // fichecenter
print '<div class="clearboth"></div>';

print dol_get_fiche_end();

// ---- Action buttons ----
if ($action == 'edit') {
	print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	print '<div class="center">';
	print '<input type="submit" class="button button-save" name="save" value="'.$langs->trans('Save').'">';
	print ' &nbsp; ';
	print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans('Cancel').'">';
	print '</div>';
	print '</form>';
} else {
	// View mode buttons
	print "\n".'<div class="tabsAction">'."\n";

	if ($permwrite && $display_status != SvcWarranty::STATUS_VOIDED) {
		print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit&token='.newToken(), '');
	}
	if ($permwrite && $display_status != SvcWarranty::STATUS_VOIDED) {
		print dolGetButtonAction('', $langs->trans('VoidWarranty'), 'danger', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=void&token='.newToken(), '');
	}
	if ($permdelete) {
		print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken(), '');
	}

	print '</div>';
}

// ============================================================
// CLAIM HISTORY — SvcRequests filed against this warranty
// ============================================================
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="5">'.$langs->trans('ClaimHistory').'</td>';
print '</tr>';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Ref').'</td>';
print '<td>'.$langs->trans('IssueDate').'</td>';
print '<td>'.$langs->trans('ResolutionType').'</td>';
print '<td class="center">'.$langs->trans('Status').'</td>';
print '<td>'.$langs->trans('AssignedTo').'</td>';
print '</tr>';

$sqlclaims  = "SELECT r.rowid, r.ref, r.issue_date, r.resolution_type, r.status, r.fk_user_assigned";
$sqlclaims .= " FROM ".MAIN_DB_PREFIX."svc_request as r";
$sqlclaims .= " WHERE r.serial_number = '".$db->escape($object->serial_number)."'";
$sqlclaims .= " AND r.entity IN (".getEntity('svcrequest').")";
$sqlclaims .= " ORDER BY r.issue_date DESC";

$resqlclaims = $db->query($sqlclaims);
if ($resqlclaims) {
	$numclaims = $db->num_rows($resqlclaims);
	if ($numclaims == 0) {
		print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('NoClaims').'</span></td></tr>';
	}
	$i = 0;
	while ($i < $numclaims) {
		$claim = $db->fetch_object($resqlclaims);
		print '<tr class="oddeven">';
		print '<td><a href="'.DOL_URL_ROOT.'/custom/warrantysvc/card.php?id='.$claim->rowid.'">'.dol_escape_htmltag($claim->ref).'</a></td>';
		print '<td>'.dol_print_date($db->jdate($claim->issue_date), 'day').'</td>';
		print '<td>'.svcrequest_resolution_label($claim->resolution_type).'</td>';
		print '<td class="center">'.svcrequest_status_badge($claim->status).'</td>';
		if ($claim->fk_user_assigned) {
			$u = new User($db);
			$u->fetch($claim->fk_user_assigned);
			print '<td>'.dol_escape_htmltag($u->getFullName($langs)).'</td>';
		} else {
			print '<td><span class="opacitymedium">&mdash;</span></td>';
		}
		print '</tr>';
		$i++;
	}
	$db->free($resqlclaims);
} else {
	print '<tr><td colspan="5">'.$db->lasterror().'</td></tr>';
}

print '</table>';
print '</div>';

llxFooter();
$db->close();
