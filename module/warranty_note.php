<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    warranty_note.php
 * \ingroup warrantysvc
 * \brief   Notes tab for a Warranty card
 */

$res = 0;
if (!$res && file_exists("../main.inc.php"))       { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php"))    { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarranty.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/lib/warrantysvc.lib.php';

$langs->loadLangs(array('warrantysvc@warrantysvc'));

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

$permread  = $user->hasRight('warrantysvc', 'svcwarranty', 'read');
$permwrite = $user->hasRight('warrantysvc', 'svcwarranty', 'write');

if (!$permread) { accessforbidden(); }

if ($cancel) {
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

/*
 * Actions
 */
if ($action == 'update' && $permwrite) {
	$object->note_public  = GETPOST('note_public', 'restricthtml');
	$object->note_private = GETPOST('note_private', 'restricthtml');
	if ($object->update($user) < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

/*
 * View
 */
$form = new Form($db);

llxHeader('', $object->ref.' - '.$langs->trans('Notes'), '');

$head = svcwarranty_prepare_head($object);
print dol_get_fiche_head($head, 'note', $langs->trans('SvcWarranty'), -1, 'technic');

$linkback = '<a href="'.DOL_URL_ROOT.'/custom/warrantysvc/warranty_list.php">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

if ($action == 'edit' && $permwrite) {
	print '<form action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" method="POST">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';

	print '<table class="border centpercent tableforfield">';

	print '<tr><td class="titlefieldcreate tdtop">'.$langs->trans('NotePublic').'</td><td>';
	print '<textarea name="note_public" class="quatrevingtpercent" rows="8">'.dol_escape_htmltag($object->note_public, 0, 1).'</textarea>';
	print '</td></tr>';

	print '<tr><td class="titlefieldcreate tdtop">'.$langs->trans('NotePrivate').'</td><td>';
	print '<textarea name="note_private" class="quatrevingtpercent" rows="8">'.dol_escape_htmltag($object->note_private, 0, 1).'</textarea>';
	print '</td></tr>';

	print '</table>';
	print '</div>';
	print dol_get_fiche_end();

	print '<div class="tabsAction">';
	print '<input type="submit" class="butAction" value="'.$langs->trans('Save').'">';
	print ' <a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&cancel=1" class="butActionDelete">'.$langs->trans('Cancel').'</a>';
	print '</div>';
	print '</form>';
} else {
	print '<table class="border centpercent tableforfield">';

	print '<tr><td class="titlefieldcreate">'.$langs->trans('NotePublic').'</td><td>';
	print dol_string_onlythesehtmltags(dol_htmlentitiesbr($object->note_public));
	print '</td></tr>';

	print '<tr><td class="titlefieldcreate">'.$langs->trans('NotePrivate').'</td><td>';
	print dol_string_onlythesehtmltags(dol_htmlentitiesbr($object->note_private));
	print '</td></tr>';

	print '</table>';
	print '</div>';
	print dol_get_fiche_end();

	if ($permwrite) {
		print '<div class="tabsAction">';
		print '<a href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit&token='.newToken().'" class="butAction">'.$langs->trans('Modify').'</a>';
		print '</div>';
	}
}

llxFooter();
$db->close();
