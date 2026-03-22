<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    warranty_list.php
 * \ingroup warrantysvc
 * \brief   Warranty list view
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))        { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php"))     { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))  { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarranty.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarrantytype.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/lib/warrantysvc.lib.php';

$langs->loadLangs(array('warrantysvc@warrantysvc', 'companies', 'products'));

if (!$user->hasRight('warrantysvc', 'svcwarranty', 'read')) {
	accessforbidden();
}

$action      = GETPOST('action', 'aZ09');
$optioncss   = GETPOST('optioncss', 'alpha');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'svcwarranty';

// Search filters
$search_ref         = GETPOST('search_ref', 'alpha');
$search_company     = GETPOST('search_company', 'alpha');
$search_product     = GETPOST('search_product', 'alpha');
$search_serial      = GETPOST('search_serial', 'alpha');
$search_wtype       = GETPOST('search_wtype', 'alpha');
$search_status      = GETPOST('search_status', 'alpha');
$search_expiry_from = dol_mktime(0, 0, 0, GETPOST('search_expiry_frommonth', 'int'), GETPOST('search_expiry_fromday', 'int'), GETPOST('search_expiry_fromyear', 'int'));
$search_expiry_to   = dol_mktime(23, 59, 59, GETPOST('search_expiry_tomonth', 'int'), GETPOST('search_expiry_today', 'int'), GETPOST('search_expiry_toyear', 'int'));

// Quick filter presets
$preset = GETPOST('preset', 'alpha');
if ($preset == 'active') {
	$search_status = 'active';
}
if ($preset == 'expiring') {
	$search_status      = 'active';
	$search_expiry_from = dol_now();
	$search_expiry_to   = dol_time_plus_duree(dol_now(), 30, 'd');
}
if ($preset == 'expired') {
	$search_status = 'expired';
}

// Reset filters
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$search_ref = $search_company = $search_product = $search_serial = '';
	$search_wtype = $search_status = '';
	$search_expiry_from = $search_expiry_to = '';
	$preset = '';
}

$sortfield = GETPOST('sortfield', 'aZ09comma') ? GETPOST('sortfield', 'aZ09comma') : 't.expiry_date';
$sortorder = GETPOST('sortorder', 'aZ09comma') ? GETPOST('sortorder', 'aZ09comma') : 'ASC';
$limit     = $conf->liste_limit;
$page      = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : max(0, GETPOST('page', 'int'));
$offset    = $limit * $page;

// Build query
$sql  = "SELECT t.rowid, t.ref, t.fk_soc, t.fk_product, t.serial_number,";
$sql .= " t.warranty_type, t.start_date, t.expiry_date, t.status,";
$sql .= " t.claim_count, t.total_claimed_value,";
$sql .= " s.nom as company_name,";
$sql .= " p.ref as product_ref, p.label as product_label";
$sql .= " FROM ".MAIN_DB_PREFIX."svc_warranty as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = t.fk_soc";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = t.fk_product";
$sql .= " WHERE t.entity IN (".getEntity('svcwarranty').")";

if ($search_ref) {
	$sql .= natural_search('t.ref', $search_ref);
}
if ($search_company) {
	$sql .= natural_search('s.nom', $search_company);
}
if ($search_product) {
	$sql .= natural_search('p.ref', $search_product);
}
if ($search_serial) {
	$sql .= natural_search('t.serial_number', $search_serial);
}
if ($search_wtype) {
	$sql .= " AND t.warranty_type = '".$db->escape($search_wtype)."'";
}
if ($search_status && $search_status != '-1') {
	// Expiry-based status filter
	if ($search_status == 'active') {
		$sql .= " AND t.status != 'voided' AND (t.expiry_date IS NULL OR t.expiry_date >= '".$db->idate(dol_now())."')";
	} elseif ($search_status == 'expired') {
		$sql .= " AND t.status != 'voided' AND t.expiry_date < '".$db->idate(dol_now())."'";
	} elseif ($search_status == 'voided') {
		$sql .= " AND t.status = 'voided'";
	}
}
if ($search_expiry_from) {
	$sql .= " AND t.expiry_date >= '".$db->idate($search_expiry_from)."'";
}
if ($search_expiry_to) {
	$sql .= " AND t.expiry_date <= '".$db->idate($search_expiry_to)."'";
}

$sql .= $db->order($sortfield, $sortorder);

// Count for pagination
$sqlcount = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(*) as nb FROM', $sql);
$sqlcount = preg_replace('/ORDER BY.*$/s', '', $sqlcount);
$nbtotalofrecords = 0;
$resqlcount = $db->query($sqlcount);
if ($resqlcount) {
	$objcount = $db->fetch_object($resqlcount);
	$nbtotalofrecords = $objcount->nb;
}

$sql .= $db->plimit($limit, $offset);

/*
 * View
 */
llxHeader('', $langs->trans('Warranties'), '');

$newcardbutton = '';
if ($user->hasRight('warrantysvc', 'svcwarranty', 'write')) {
	$newcardbutton = dolGetButtonTitle(
		$langs->trans('NewWarranty'),
		'',
		'fa fa-plus-circle',
		DOL_URL_ROOT.'/custom/warrantysvc/warranty_card.php?action=create'
	);
}

print_barre_liste(
	$langs->trans('Warranties'),
	$page,
	$_SERVER['PHP_SELF'],
	'',
	$sortfield,
	$sortorder,
	'',
	$nbtotalofrecords,
	$nbtotalofrecords,
	'bill',
	0,
	$newcardbutton,
	'',
	$limit,
	0,
	0,
	1
);

// Build type label cache for display (avoids per-row DB queries)
$wtype_labels = array();
foreach (SvcWarrantyType::fetchAllForForm($db) as $wt) {
	$wtype_labels[$wt->code] = $wt->label;
}

// Quick filter presets
print '<div class="divsearchfield">';
print '<a class="btnTitle'.($preset == 'active' ? ' btnTitleSelected' : '').'" href="'.$_SERVER['PHP_SELF'].'?preset=active">'.$langs->trans('Active').'</a> &nbsp;';
print '<a class="btnTitle'.($preset == 'expiring' ? ' btnTitleSelected' : '').'" href="'.$_SERVER['PHP_SELF'].'?preset=expiring">'.$langs->trans('ExpiringSoon').'</a> &nbsp;';
print '<a class="btnTitle'.($preset == 'expired' ? ' btnTitleSelected' : '').'" href="'.$_SERVER['PHP_SELF'].'?preset=expired">'.$langs->trans('Expired').'</a>';
print '</div>';

print '<form method="GET" id="searchFormList" action="'.$_SERVER['PHP_SELF'].'">';
if ($preset) {
	print '<input type="hidden" name="preset" value="'.dol_escape_htmltag($preset).'">';
}

print '<div class="div-table-responsive">';
print '<table class="tabl noborder liste '.($optioncss == 'print' ? 'listwithout' : 'centpercent').'">';

// Filter row
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input type="text" class="flat maxwidth75imp" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100imp" name="search_company" value="'.dol_escape_htmltag($search_company).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100imp" name="search_product" value="'.dol_escape_htmltag($search_product).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth75imp" name="search_serial" value="'.dol_escape_htmltag($search_serial).'"></td>';

// Warranty type filter — DB-driven
$wtype_filter = array(-1 => '');
foreach (SvcWarrantyType::fetchAllForForm($db) as $wt) {
	$wtype_filter[$wt->code] = $wt->label;
}
print '<td class="liste_titre">';
print Form::selectarray('search_wtype', $wtype_filter, $search_wtype, 0, 0, 0, '', 0, 0, 0, '', 'flat maxwidth100');
print '</td>';

// Status filter
$statuses = array(
	-1        => '',
	'active'  => $langs->trans('Active'),
	'expiring'=> $langs->trans('ExpiringSoon'),
	'expired' => $langs->trans('Expired'),
	'voided'  => $langs->trans('Voided'),
);
print '<td class="liste_titre">';
print Form::selectarray('search_status', $statuses, $search_status, 0, 0, 0, '', 0, 0, 0, '', 'flat maxwidth100');
print '</td>';

// Expiry date range
$form = new Form($db);
print '<td class="liste_titre">';
print $form->selectDate($search_expiry_from, 'search_expiry_from', 0, 0, 1, 'searchFormList', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
print '</td>';
print '<td class="liste_titre">';
print $form->selectDate($search_expiry_to, 'search_expiry_to', 0, 0, 1, 'searchFormList', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('To'));
print '</td>';

print '<td class="liste_titre center"></td>'; // claims
print '<td class="liste_titre liste_titre_right">';
print '<input type="submit" class="button small liste_titre" name="button_search_x" value="'.dol_escape_htmltag($langs->trans('Search')).'">';
print ' <input type="submit" class="button small liste_titre" name="button_removefilter_x" value="'.dol_escape_htmltag($langs->trans('Reset')).'">';
print '</td>';
print '</tr>';

// Column headers
print '<tr class="liste_titre">';
print getTitleFieldOfList('Ref',           0, $_SERVER['PHP_SELF'], 't.ref',          '', '', '',       '', $sortfield, $sortorder);
print getTitleFieldOfList('Company',       0, $_SERVER['PHP_SELF'], 's.nom',          '', '', '',       '', $sortfield, $sortorder);
print getTitleFieldOfList('Product',       0, $_SERVER['PHP_SELF'], 'p.ref',          '', '', '',       '', $sortfield, $sortorder);
print getTitleFieldOfList('SerialNumber',  0, $_SERVER['PHP_SELF'], 't.serial_number','', '', '',       '', $sortfield, $sortorder);
print getTitleFieldOfList('WarrantyType',  0, $_SERVER['PHP_SELF'], 't.warranty_type','', '', '',       '', $sortfield, $sortorder);
print getTitleFieldOfList('Status',        0, $_SERVER['PHP_SELF'], 't.status',       '', '', 'center', '', $sortfield, $sortorder);
print getTitleFieldOfList('StartDate',     0, $_SERVER['PHP_SELF'], 't.start_date',   '', '', '',       '', $sortfield, $sortorder);
print getTitleFieldOfList('ExpiryDate',    0, $_SERVER['PHP_SELF'], 't.expiry_date',  '', '', '',       '', $sortfield, $sortorder);
print getTitleFieldOfList('Claims',        0, $_SERVER['PHP_SELF'], 't.claim_count',  '', '', 'center', '', $sortfield, $sortorder);
print '</tr>';

// Data rows
$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	$i   = 0;

	if ($num == 0) {
		print '<tr class="oddeven"><td colspan="9"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}

	$now = dol_now();

	while ($i < $num) {
		$obj = $db->fetch_object($resql);

		$cardurl = DOL_URL_ROOT.'/custom/warrantysvc/warranty_card.php?id='.$obj->rowid;

		// Compute live status for display
		if ($obj->status == 'voided') {
			$display_status = 'voided';
		} elseif (!empty($obj->expiry_date) && $db->jdate($obj->expiry_date) < $now) {
			$display_status = 'expired';
		} else {
			$display_status = 'active';
		}

		// Highlight expiring soon in yellow
		$row_class = 'oddeven';
		$expiry_ts = $db->jdate($obj->expiry_date);
		if ($display_status == 'active' && $expiry_ts && $expiry_ts < dol_time_plus_duree($now, 30, 'd')) {
			$row_class = 'oddeven highlight';
		}

		print '<tr class="'.$row_class.'">';
		print '<td><a href="'.$cardurl.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
		print '<td>'.dol_escape_htmltag($obj->company_name).'</td>';
		print '<td>'.dol_escape_htmltag($obj->product_ref ? $obj->product_ref : '').'</td>';
		print '<td>'.dol_escape_htmltag($obj->serial_number).'</td>';
		$wtype_label = $obj->warranty_type ? ($wtype_labels[$obj->warranty_type] ?? dol_escape_htmltag($obj->warranty_type)) : '';
		print '<td>'.($wtype_label ? dol_escape_htmltag($wtype_label) : '<span class="opacitymedium">&mdash;</span>').'</td>';
		print '<td class="center">'.svcwarranty_status_badge($display_status).'</td>';
		print '<td>'.dol_print_date($db->jdate($obj->start_date), 'day').'</td>';
		print '<td>';
		if ($obj->expiry_date) {
			$expiry_label = dol_print_date($expiry_ts, 'day');
			if ($display_status == 'expired') {
				print '<span class="warning">'.$expiry_label.'</span>';
			} elseif ($display_status == 'active' && $expiry_ts < dol_time_plus_duree($now, 30, 'd')) {
				print '<span class="opacitymediumhigh">'.$expiry_label.'</span>';
			} else {
				print $expiry_label;
			}
		} else {
			print '<span class="opacitymedium">&mdash;</span>';
		}
		print '</td>';
		print '<td class="center">'.((int) $obj->claim_count).'</td>';
		print '</tr>';

		$i++;
	}
	$db->free($resql);
} else {
	print '<tr><td colspan="9">'.$db->lasterror().'</td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
