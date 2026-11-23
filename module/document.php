<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    document.php
 * \ingroup warrantysvc
 * \brief   Attached files tab for a Service Request card
 */

$res = 0;
if (file_exists("../main.inc.php")) {
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcrequest.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/lib/warrantysvc.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
'@phan-var-force Conf $conf';
'@phan-var-force DoliDB $db';
'@phan-var-force HookManager $hookmanager';
'@phan-var-force Translate $langs';
'@phan-var-force User $user';

$langs->loadLangs(array('warrantysvc@warrantysvc', 'companies', 'other'));

$id      = GETPOSTINT('id');
$ref     = GETPOST('ref', 'alpha');
$action  = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (!$sortorder) {
	$sortorder = 'ASC';
}
if (!$sortfield) {
	$sortfield = 'name';
}

$object = new SvcRequest($db);
$hookmanager->initHooks(array('svcrequestdocument', 'globalcard'));

if ($id > 0 || $ref) {
	if ($object->fetch($id, $ref) <= 0) {
		accessforbidden();
	}
}

$permissiontoread = $user->hasRight('warrantysvc', 'svcrequest', 'read');
$permissiontoadd  = $user->hasRight('warrantysvc', 'svcrequest', 'write'); // Used by actions_linkedfiles.inc.php

if (!isModEnabled('warrantysvc') || !$permissiontoread || empty($object->id)) {
	accessforbidden();
}

// Files sit under the right's object part (svcrequest/) so core download access checks svcrequest->read
$upload_dir = (!empty($conf->warrantysvc->multidir_output[$object->entity]) ? $conf->warrantysvc->multidir_output[$object->entity] : $conf->warrantysvc->dir_output);
$upload_dir .= '/svcrequest/'.dol_sanitizeFileName($object->ref);


/*
 * Actions
 */

$parameters = array('id' => $object->id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	include DOL_DOCUMENT_ROOT.'/core/actions_linkedfiles.inc.php';
}


/*
 * View
 */

$form = new Form($db);

llxHeader('', $object->ref.' - '.$langs->trans('SvcDocuments'), '', '', 0, 0, '', '', '', 'mod-warrantysvc page-card_document');

$head = warrantysvc_prepare_head($object);
print dol_get_fiche_head($head, 'document', $langs->trans('SvcRequest'), -1, 'technic');

$filearray = dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$', $sortfield, (dol_strtolower($sortorder) == 'desc' ? SORT_DESC : SORT_ASC), 1);
$totalsize = 0;
foreach ($filearray as $file) {
	$totalsize += $file['size'];
}

$linkback = '<a href="'.DOL_URL_ROOT.'/custom/warrantysvc/list.php">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('NbOfAttachedFiles').'</td><td colspan="3">'.count($filearray).'</td></tr>';
print '<tr><td>'.$langs->trans('TotalSizeOfAttachedFiles').'</td><td colspan="3">'.dol_print_size($totalsize, 1, 1).'</td></tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();

$modulepart = 'warrantysvc';
$param = '&id='.$object->id;
$relativepathwithnofile = 'svcrequest/'.dol_sanitizeFileName($object->ref).'/';

include DOL_DOCUMENT_ROOT.'/core/tpl/document_actions_post_headers.tpl.php';

llxFooter();
$db->close();
