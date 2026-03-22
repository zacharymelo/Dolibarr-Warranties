<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    list.php
 * \ingroup warrantysvc
 * \brief   Service Request list view
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))        { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php"))     { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))  { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcrequest.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/lib/warrantysvc.lib.php';

$langs->loadLangs(array('warrantysvc@warrantysvc', 'companies'));

if (!$user->hasRight('warrantysvc', 'svcrequest', 'read')) {
	accessforbidden();
}

$action       = GETPOST('action', 'aZ09');
$massaction   = GETPOST('massaction', 'alpha');
$contextpage  = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'svcrequest';
$optioncss    = GETPOST('optioncss', 'alpha');

// Project tab context — pre-filter by project when accessed from project card
$projectid = GETPOST('projectid', 'int');

// Search filters
$search_ref           = GETPOST('search_ref', 'alpha');
$search_company       = GETPOST('search_company', 'alpha');
$search_serial        = GETPOST('search_serial', 'alpha');
$search_resolution    = GETPOST('search_resolution', 'alpha');
$search_status        = GETPOST('search_status', 'intcomma');
$search_assigned      = GETPOST('search_assigned', 'int');
$search_warranty      = GETPOST('search_warranty', 'alpha');
$search_date_start    = dol_mktime(0, 0, 0, GETPOST('search_date_startmonth', 'int'), GETPOST('search_date_startday', 'int'), GETPOST('search_date_startyear', 'int'));
$search_date_end      = dol_mktime(23, 59, 59, GETPOST('search_date_endmonth', 'int'), GETPOST('search_date_endday', 'int'), GETPOST('search_date_endyear', 'int'));

// Quick filter presets
$preset = GETPOST('preset', 'alpha');
if ($preset == 'myopen') {
	$search_assigned = $user->id;
	$search_status   = '0,1,2,3';
}
if ($preset == 'awaitreturn') {
	$search_status = '3';
}
if ($preset == 'unassigned') {
	$search_assigned = -1;
	$search_status   = '0,1,2';
}

// Reset filters
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$search_ref = $search_company = $search_serial = $search_resolution = '';
	$search_status = $search_assigned = $search_warranty = '';
	$search_date_start = $search_date_end = '';
}

$sortfield = GETPOST('sortfield', 'aZ09comma') ? GETPOST('sortfield', 'aZ09comma') : 't.date_creation';
$sortorder = GETPOST('sortorder', 'aZ09comma') ? GETPOST('sortorder', 'aZ09comma') : 'DESC';
$limit     = $conf->liste_limit;
$page      = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : max(0, GETPOST('page', 'int'));
$offset    = $limit * $page;

// Build WHERE
$sql  = "SELECT t.rowid, t.ref, t.fk_soc, t.serial_number, t.resolution_type,";
$sql .= " t.warranty_status, t.status, t.fk_user_assigned, t.date_creation,";
$sql .= " s.nom as company_name";
$sql .= " FROM ".MAIN_DB_PREFIX."svc_request as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = t.fk_soc";
$sql .= " WHERE t.entity IN (".getEntity('svcrequest').")";

if ($projectid > 0) {
	$sql .= " AND t.fk_project = ".((int) $projectid);
}

if ($search_ref) {
	$sql .= natural_search('t.ref', $search_ref);
}
if ($search_company) {
	$sql .= natural_search('s.nom', $search_company);
}
if ($search_serial) {
	$sql .= natural_search('t.serial_number', $search_serial);
}
if ($search_resolution) {
	$sql .= " AND t.resolution_type = '".$db->escape($search_resolution)."'";
}
if ($search_status !== '' && $search_status !== '-1') {
	if (strpos($search_status, ',') !== false) {
		$sql .= " AND t.status IN (".$db->sanitize($search_status).")";
	} else {
		$sql .= " AND t.status = ".((int) $search_status);
	}
}
if ($search_assigned > 0) {
	$sql .= " AND t.fk_user_assigned = ".((int) $search_assigned);
} elseif ($search_assigned == -1) {
	$sql .= " AND t.fk_user_assigned IS NULL";
}
if ($search_warranty) {
	$sql .= " AND t.warranty_status = '".$db->escape($search_warranty)."'";
}
if ($search_date_start) {
	$sql .= " AND t.date_creation >= '".$db->idate($search_date_start)."'";
}
if ($search_date_end) {
	$sql .= " AND t.date_creation <= '".$db->idate($search_date_end)."'";
}

$sql .= $db->order($sortfield, $sortorder);

// Count total for pagination
$sqlcount = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(*) as nb FROM', $sql);
$sqlcount = preg_replace('/ORDER BY.*$/s', '', $sqlcount);
$nbtotalofrecords = 0;
$resqlcount = $db->query($sqlcount);
if ($resqlcount) {
	$obj = $db->fetch_object($resqlcount);
	$nbtotalofrecords = $obj->nb;
}

$sql .= $db->plimit($limit, $offset);

/*
 * View
 */
llxHeader('', $langs->trans('SvcRequests'), '');

$newcardbutton = '';
if ($user->hasRight('warrantysvc', 'svcrequest', 'write')) {
	$newcardbutton = dolGetButtonTitle(
		$langs->trans('NewSvcRequest'),
		'',
		'fa fa-plus-circle',
		DOL_URL_ROOT.'/custom/warrantysvc/card.php?action=create'
	);
}

print_barre_liste(
	$langs->trans('SvcRequests'),
	$page,
	$_SERVER['PHP_SELF'],
	'',
	$sortfield,
	$sortorder,
	'',
	$nbtotalofrecords,
	$nbtotalofrecords,
	'technic',
	0,
	$newcardbutton,
	'',
	$limit,
	0,
	0,
	1
);

// Quick filter presets
print '<div class="divsearchfield">';
print '<a class="btnTitle'.($preset == 'myopen' ? ' btnTitleSelected' : '').'" href="'.$_SERVER['PHP_SELF'].'?preset=myopen">'.$langs->trans('MyOpenRequests').'</a> &nbsp;';
print '<a class="btnTitle'.($preset == 'awaitreturn' ? ' btnTitleSelected' : '').'" href="'.$_SERVER['PHP_SELF'].'?preset=awaitreturn">'.$langs->trans('AwaitingReturn').'</a> &nbsp;';
print '<a class="btnTitle'.($preset == 'unassigned' ? ' btnTitleSelected' : '').'" href="'.$_SERVER['PHP_SELF'].'?preset=unassigned">'.$langs->trans('Unassigned').'</a>';
print '</div>';

print '<form method="GET" id="searchFormList" action="'.$_SERVER['PHP_SELF'].'">';

print '<div class="div-table-responsive">';
print '<table class="tabl noborder liste '.($optioncss == 'print' ? 'listwithout' : 'centpercent').'">';

// Header row with search fields
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input type="text" class="flat maxwidth75imp" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth100imp" name="search_company" value="'.dol_escape_htmltag($search_company).'"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth75imp" name="search_serial" value="'.dol_escape_htmltag($search_serial).'"></td>';

// Resolution type filter
print '<td class="liste_titre">';
$resolution_types = array('' => '') + svcrequest_resolution_types();
print Form::selectarray('search_resolution', $resolution_types, $search_resolution, 0, 0, 0, '', 0, 0, 0, '', 'flat maxwidth125');
print '</td>';

// Status filter
print '<td class="liste_titre">';
$statuses = array(
	-1 => '',
	0  => $langs->trans('Draft'),
	1  => $langs->trans('Validated'),
	2  => $langs->trans('InProgress'),
	3  => $langs->trans('AwaitingReturn'),
	4  => $langs->trans('Resolved'),
	5  => $langs->trans('Closed'),
	9  => $langs->trans('Cancelled'),
);
print Form::selectarray('search_status', $statuses, $search_status, 0, 0, 0, '', 0, 0, 0, '', 'flat maxwidth100');
print '</td>';

print '<td class="liste_titre"></td>'; // assigned (omit for now)
print '<td class="liste_titre"></td>'; // date
print '<td class="liste_titre liste_titre_right">';
print '<input type="submit" class="button small liste_titre" name="button_search_x" value="'.dol_escape_htmltag($langs->trans('Search')).'">';
print ' <input type="submit" class="button small liste_titre" name="button_removefilter_x" value="'.dol_escape_htmltag($langs->trans('Reset')).'">';
print '</td>';
print '</tr>';

// Column headers
print '<tr class="liste_titre">';
print getTitleFieldOfList('Ref',            0, $_SERVER['PHP_SELF'], 't.ref',            '', '', '',     '', $sortfield, $sortorder);
print getTitleFieldOfList('Company',        0, $_SERVER['PHP_SELF'], 's.nom',            '', '', '',     '', $sortfield, $sortorder);
print getTitleFieldOfList('SerialNumber',   0, $_SERVER['PHP_SELF'], 't.serial_number',  '', '', '',     '', $sortfield, $sortorder);
print getTitleFieldOfList('ResolutionType', 0, $_SERVER['PHP_SELF'], 't.resolution_type','', '', '',     '', $sortfield, $sortorder);
print getTitleFieldOfList('Status',         0, $_SERVER['PHP_SELF'], 't.status',         '', '', 'center','', $sortfield, $sortorder);
print getTitleFieldOfList('AssignedTo',     0, $_SERVER['PHP_SELF'], 't.fk_user_assigned','','','',     '', $sortfield, $sortorder);
print getTitleFieldOfList('DateCreation',   0, $_SERVER['PHP_SELF'], 't.date_creation',  '', '', '',     '', $sortfield, $sortorder);
print getTitleFieldOfList('',               0, $_SERVER['PHP_SELF'], '',                 '', '', '',     '', $sortfield, $sortorder); // warranty badge
print '</tr>';

// Data rows
$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	$i   = 0;

	if ($num == 0) {
		print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}

	while ($i < $num) {
		$obj = $db->fetch_object($resql);

		$cardurl = DOL_URL_ROOT.'/custom/warrantysvc/card.php?id='.$obj->rowid;

		print '<tr class="oddeven">';
		print '<td><a href="'.$cardurl.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
		print '<td>'.dol_escape_htmltag($obj->company_name).'</td>';
		print '<td>'.dol_escape_htmltag($obj->serial_number).'</td>';
		print '<td>'.svcrequest_resolution_label($obj->resolution_type).'</td>';
		print '<td class="center">'.svcrequest_status_badge($obj->status).'</td>';

		// Assigned user
		if ($obj->fk_user_assigned) {
			$u = new User($db);
			$u->fetch($obj->fk_user_assigned);
			print '<td>'.dol_escape_htmltag($u->getFullName($langs)).'</td>';
		} else {
			print '<td><span class="opacitymedium">&mdash;</span></td>';
		}

		print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'day').'</td>';
		print '<td>'.svcwarranty_status_badge($obj->warranty_status ? $obj->warranty_status : 'none').'</td>';
		print '</tr>';

		$i++;
	}
	$db->free($resql);
} else {
	print '<tr><td colspan="8">'.$db->lasterror().'</td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
