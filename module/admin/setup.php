<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    admin/setup.php
 * \ingroup warrantysvc
 * \brief   Module configuration page
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
dol_include_once('/warrantysvc/lib/warrantysvc.lib.php');

$langs->loadLangs(array('admin', 'warrantysvc@warrantysvc'));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

// Save settings
if ($action == 'update') {
	$settings = array(
		'WARRANTYSVC_WAREHOUSE_REFURB',
		'WARRANTYSVC_WAREHOUSE_RETURN',
		'WARRANTYSVC_RETURN_GRACE_DAYS',
		'WARRANTYSVC_RETURN_INVOICE_DAYS',
		'WARRANTYSVC_REPLACEMENT_STRATEGY',
		'WARRANTYSVC_AUTO_WARRANTY_CHECK',
		'WARRANTYSVC_AUTO_WARRANTY_ON_SHIPMENT',
		'WARRANTYSVC_WARRANTY_TRIGGER_EVENT',
		'WARRANTYSVC_AUTO_WARRANTY_ON_ORDER_CLOSE',
		'WARRANTYSVC_DEFAULT_COVERAGE_DAYS',
		'WARRANTYSVC_NOTIFY_WARRANTY_CREATED',
		'WARRANTYSVC_WARRANTY_REQUIRES_LOTS',
		'WARRANTYSVC_USE_CUSTOMERRETURN',
		'WARRANTYSVC_DEBUG_MODE',
	);

	foreach ($settings as $key) {
		$val = GETPOST($key, 'alpha');
		dolibarr_set_const($db, $key, $val, 'chaine', 0, '', $conf->entity);
	}

	setEventMessages($langs->trans('SvcSetupSaved'), null, 'mesgs');
}

// Load warehouses for selectors
$entrepot = new Entrepot($db);
$warehouses = $entrepot->list_array();

/*
 * View
 */

$wikihelp = '';
llxHeader('', $langs->trans('WarrantySvc').' - '.$langs->trans('SvcSetup'), $wikihelp);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'
	.$langs->trans('BackToModuleList').'</a>';

print load_fiche_titre($langs->trans('WarrantySvcSetup'), $linkback, 'title_setup');

$head = warrantysvc_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $langs->trans('WarrantySvc'), -1, 'technic');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameter').'</td>';
print '<td>'.$langs->trans('Value').'</td>';
print '</tr>';

// Refurbished stock warehouse
print '<tr class="oddeven">';
print '<td>'.$langs->trans('SvcWarehouseSource').'<br><span class="opacitymedium">'
	.$langs->trans('SvcWarehouseRefurbDesc').'</span></td>';
print '<td>';
print '<select name="WARRANTYSVC_WAREHOUSE_REFURB" class="flat minwidth300">';
print '<option value="">--- '.$langs->trans('SvcSelectWarehouse').' ---</option>';
foreach ($warehouses as $id => $label) {
	$sel = (getDolGlobalString('WARRANTYSVC_WAREHOUSE_REFURB') == $id) ? ' selected' : '';
	print '<option value="'.$id.'"'.$sel.'>'.dol_escape_htmltag($label).'</option>';
}
print '</select>';
print '</td></tr>';

// RMA return / repair warehouse
print '<tr class="oddeven">';
print '<td>'.$langs->trans('SvcWarehouseReturn').'<br><span class="opacitymedium">'
	.$langs->trans('SvcWarehouseReturnDesc').'</span></td>';
print '<td>';
print '<select name="WARRANTYSVC_WAREHOUSE_RETURN" class="flat minwidth300">';
print '<option value="">--- '.$langs->trans('SvcSelectWarehouse').' ---</option>';
foreach ($warehouses as $id => $label) {
	$sel = (getDolGlobalString('WARRANTYSVC_WAREHOUSE_RETURN') == $id) ? ' selected' : '';
	print '<option value="'.$id.'"'.$sel.'>'.dol_escape_htmltag($label).'</option>';
}
print '</select>';
print '</td></tr>';

// Return grace period (days before first reminder)
print '<tr class="oddeven">';
print '<td>'.$langs->trans('ReturnGraceDays').'<br><span class="opacitymedium">'
	.$langs->trans('ReturnGraceDaysDesc').'</span></td>';
print '<td>';
print '<input type="number" name="WARRANTYSVC_RETURN_GRACE_DAYS" class="flat" min="1" max="90" value="'.dol_escape_htmltag(getDolGlobalString('WARRANTYSVC_RETURN_GRACE_DAYS', '7')).'">';
print ' '.$langs->trans('days');
print '</td></tr>';

// Days before auto-invoice for non-return
print '<tr class="oddeven">';
print '<td>'.$langs->trans('ReturnInvoiceDays').'<br><span class="opacitymedium">'
	.$langs->trans('ReturnInvoiceDaysDesc').'</span></td>';
print '<td>';
print '<input type="number" name="WARRANTYSVC_RETURN_INVOICE_DAYS" class="flat" min="1" max="180" value="'.dol_escape_htmltag(getDolGlobalString('WARRANTYSVC_RETURN_INVOICE_DAYS', '30')).'">';
print ' '.$langs->trans('days');
print '</td></tr>';

// Replacement unit selection strategy
print '<tr class="oddeven">';
print '<td>'.$langs->trans('ReplacementStrategy').'<br><span class="opacitymedium">'
	.$langs->trans('ReplacementStrategyDesc').'</span></td>';
print '<td>';
$current_strategy = getDolGlobalString('WARRANTYSVC_REPLACEMENT_STRATEGY', 'fifo');
$strategies = array(
	'fifo'           => $langs->trans('StrategyFIFO'),
	'least_serviced' => $langs->trans('StrategyLeastServiced'),
	'best_condition' => $langs->trans('StrategyBestCondition'),
	'manual'         => $langs->trans('StrategyManual'),
);
print '<select name="WARRANTYSVC_REPLACEMENT_STRATEGY" class="flat minwidth300">';
foreach ($strategies as $key => $label) {
	$sel = ($current_strategy == $key) ? ' selected' : '';
	print '<option value="'.$key.'"'.$sel.'>'.dol_escape_htmltag($label).'</option>';
}
print '</select>';
print '</td></tr>';

// Auto warranty check on SvcRequest creation
print '<tr class="oddeven">';
print '<td>'.$langs->trans('AutoWarrantyCheck').'<br><span class="opacitymedium">'
	.$langs->trans('AutoWarrantyCheckDesc').'</span></td>';
print '<td>';
$chk = getDolGlobalString('WARRANTYSVC_AUTO_WARRANTY_CHECK', '1') ? ' checked' : '';
print '<input type="checkbox" name="WARRANTYSVC_AUTO_WARRANTY_CHECK" value="1"'.$chk.'>';
print '</td></tr>';

// Auto-create warranty on shipment
print '<tr class="oddeven">';
print '<td>'.$langs->trans('AutoWarrantyOnShipment').'<br><span class="opacitymedium">'
	.$langs->trans('AutoWarrantyOnShipmentDesc').'</span></td>';
print '<td>';
$chk2 = getDolGlobalString('WARRANTYSVC_AUTO_WARRANTY_ON_SHIPMENT') ? ' checked' : '';
print '<input type="checkbox" name="WARRANTYSVC_AUTO_WARRANTY_ON_SHIPMENT" value="1"'.$chk2.'>';
print '</td></tr>';

// Warranty trigger event (only shown when auto-create is enabled)
print '<tr class="oddeven">';
print '<td>'.$langs->trans('WarrantyTriggerEvent').'<br><span class="opacitymedium">'
	.$langs->trans('WarrantyTriggerEventDesc').'</span></td>';
print '<td>';
$trigger_event = getDolGlobalString('WARRANTYSVC_WARRANTY_TRIGGER_EVENT', 'close');
print '<select name="WARRANTYSVC_WARRANTY_TRIGGER_EVENT" class="flat minwidth200">';
print '<option value="validate"'.($trigger_event == 'validate' ? ' selected' : '').'>'.$langs->trans('OnShipmentValidate').'</option>';
print '<option value="close"'.($trigger_event == 'close' ? ' selected' : '').'>'.$langs->trans('OnShipmentClose').'</option>';
print '<option value="both"'.($trigger_event == 'both' ? ' selected' : '').'>'.$langs->trans('OnShipmentBoth').'</option>';
print '</select>';
print '</td></tr>';

// Auto-create warranty on order close (delivered)
print '<tr class="oddeven">';
print '<td>'.$langs->trans('AutoWarrantyOnOrderClose').'<br><span class="opacitymedium">'
	.$langs->trans('AutoWarrantyOnOrderCloseDesc').'</span></td>';
print '<td>';
$chk_oc = getDolGlobalString('WARRANTYSVC_AUTO_WARRANTY_ON_ORDER_CLOSE') ? ' checked' : '';
print '<input type="checkbox" name="WARRANTYSVC_AUTO_WARRANTY_ON_ORDER_CLOSE" value="1"'.$chk_oc.'>';
print '</td></tr>';

// Default warranty coverage months
print '<tr class="oddeven">';
print '<td>'.$langs->trans('DefaultCoverageDays').'<br><span class="opacitymedium">'
	.$langs->trans('DefaultCoverageDaysDesc').'</span></td>';
print '<td>';
print '<input type="number" name="WARRANTYSVC_DEFAULT_COVERAGE_DAYS" value="'.((int) getDolGlobalInt('WARRANTYSVC_DEFAULT_COVERAGE_DAYS', 365)).'" class="flat width75" min="1" max="3650">';
print ' '.$langs->trans('SvcDays');
print '</td></tr>';

// Notify customer when warranty created
print '<tr class="oddeven">';
print '<td>'.$langs->trans('NotifyWarrantyCreated').'<br><span class="opacitymedium">'
	.$langs->trans('NotifyWarrantyCreatedDesc').'</span></td>';
print '<td>';
$chk3 = getDolGlobalString('WARRANTYSVC_NOTIFY_WARRANTY_CREATED') ? ' checked' : '';
print '<input type="checkbox" name="WARRANTYSVC_NOTIFY_WARRANTY_CREATED" value="1"'.$chk3.'>';
print '</td></tr>';

// Restrict service requests to serialized/lot-tracked products only
print '<tr class="oddeven">';
print '<td>'.$langs->trans('WarrantyRequiresLots').'<br><span class="opacitymedium">'
	.$langs->trans('WarrantyRequiresLotsDesc').'</span></td>';
print '<td>';
$chk4 = getDolGlobalString('WARRANTYSVC_WARRANTY_REQUIRES_LOTS') ? ' checked' : '';
print '<input type="checkbox" name="WARRANTYSVC_WARRANTY_REQUIRES_LOTS" value="1"'.$chk4.'>';
print '</td></tr>';

// Use Customer Returns module for inbound returns (optional integration)
$customerreturn_available = isModEnabled('customerreturn');
print '<tr class="oddeven">';
print '<td>'.$langs->trans('UseCustomerReturns').'<br><span class="opacitymedium">'
	.$langs->trans('UseCustomerReturnsDesc').'</span>';
if (!$customerreturn_available) {
	print '<br><span class="warning">'.$langs->trans('CustomerReturnModuleNotInstalled').'</span>';
}
print '</td>';
print '<td>';
$chk5 = getDolGlobalString('WARRANTYSVC_USE_CUSTOMERRETURN') ? ' checked' : '';
$disabled = $customerreturn_available ? '' : ' disabled';
print '<input type="checkbox" name="WARRANTYSVC_USE_CUSTOMERRETURN" value="1"'.$chk5.$disabled.'>';
print '</td></tr>';

// Debug mode
print '<tr class="oddeven">';
print '<td>'.$langs->trans('DebugMode').'<br><span class="opacitymedium">'
	.$langs->trans('DebugModeDesc').'</span></td>';
print '<td>';
$chk_debug = getDolGlobalString('WARRANTYSVC_DEBUG_MODE') ? ' checked' : '';
print '<input type="checkbox" name="WARRANTYSVC_DEBUG_MODE" value="1"'.$chk_debug.'>';
print '</td></tr>';

print '</table>';

print dol_get_fiche_end();

print '<div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</div>';

print '</form>';

// Numbering model selector (native Dolibarr UI)
print '<br>';
print load_fiche_titre($langs->trans('NumberingModule'), '', '');

$setupsql = "SELECT count(*) as nb FROM ".MAIN_DB_PREFIX."svc_request";
$resql    = $db->query($setupsql);
if (!$resql) {
	// table may not exist yet — skip numbering model display until module activated
} else {
	require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/core/modules/warrantysvc/modules_warrantysvc.php';

	$dir     = DOL_DOCUMENT_ROOT.'/custom/warrantysvc/core/modules/warrantysvc/';
	$type    = 'warrantysvc';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Name').'</td>';
	print '<td>'.$langs->trans('Description').'</td>';
	print '<td class="center">'.$langs->trans('Status').'</td>';
	print '<td class="center">'.$langs->trans('Example').'</td>';
	print '</tr>';

	$handle = opendir($dir);
	if ($handle) {
		while (($file = readdir($handle)) !== false) {
			if (substr($file, 0, 4) != 'mod_' || substr($file, -4) != '.php') {
				continue;
			}
			require_once $dir.$file;
			$classname = substr($file, 0, -4);
			if (!class_exists($classname)) {
				continue;
			}
			$mod = new $classname();
			$current = getDolGlobalString('WARRANTYSVC_ADDON', 'mod_warrantysvc_standard');
			$active  = ($current == $classname);

			print '<tr class="oddeven"><td>'.$mod->name.'</td>';
			print '<td>'.$mod->info($langs).'</td>';
			print '<td class="center">';
			if ($active) {
				print img_picto($langs->trans('Activated'), 'switch_on');
			} else {
				print '<a href="'.$_SERVER['PHP_SELF'].'?action=setmod&token='.newToken().'&value='.$classname.'">';
				print img_picto($langs->trans('Disabled'), 'switch_off');
				print '</a>';
			}
			print '</td>';
			print '<td class="center"><code>'.$mod->getExample().'</code></td>';
			print '</tr>';
		}
		closedir($handle);
	}
	print '</table>';
}

// Handle setmod action
if ($action == 'setmod') {
	$value = GETPOST('value', 'alpha');
	dolibarr_set_const($db, 'WARRANTYSVC_ADDON', $value, 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('SvcSetupSaved'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

llxFooter();
$db->close();
