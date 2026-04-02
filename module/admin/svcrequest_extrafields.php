<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    admin/svcrequest_extrafields.php
 * \ingroup warrantysvc
 * \brief   Manage extra fields for Service Requests
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
dol_include_once('/warrantysvc/lib/warrantysvc.lib.php');

$langs->loadLangs(array('admin', 'warrantysvc@warrantysvc'));

$extrafields = new ExtraFields($db);
$form = new Form($db);

$type2label = ExtraFields::getListOfTypesLabels();

$action   = GETPOST('action', 'aZ09');
$attrname = GETPOST('attrname', 'alpha');
$elementtype = 'svc_request'; // Must match $table_element on the class

if (!$user->admin) {
	accessforbidden();
}

// Actions
require DOL_DOCUMENT_ROOT.'/core/actions_extrafields.inc.php';

// View
llxHeader('', $langs->trans('ServiceRequestExtraFields'));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('WarrantySvcSetup'), $linkback, 'title_setup');

$head = warrantysvc_admin_prepare_head();
print dol_get_fiche_head($head, 'svcrequest_extrafields', $langs->trans('WarrantySvc'), -1, 'technic');

require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_view.tpl.php';

print dol_get_fiche_end();

if ($action == 'create') {
	print '<br><div id="newattrib"></div>';
	print load_fiche_titre($langs->trans('NewAttribute'));
	require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_add.tpl.php';
}

if ($action == 'edit' && !empty($attrname)) {
	print '<br>';
	print load_fiche_titre($langs->trans('FieldEdition', $attrname));
	require DOL_DOCUMENT_ROOT.'/core/tpl/admin_extrafields_edit.tpl.php';
}

llxFooter();
$db->close();
