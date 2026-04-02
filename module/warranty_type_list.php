<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    warranty_type_list.php
 * \ingroup warrantysvc
 * \brief   Manage user-defined warranty types
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarrantytype.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/lib/warrantysvc.lib.php';

$langs->loadLangs(array('warrantysvc@warrantysvc'));

$action = GETPOST('action', 'aZ09');
$id     = GETPOST('id', 'int');
$cancel = GETPOST('cancel', 'alpha');

$permwrite = $user->hasRight('warrantysvc', 'svcwarranty', 'write');
if (!$user->hasRight('warrantysvc', 'svcwarranty', 'read')) {
	accessforbidden();
}

if ($cancel) {
	$action = '';
}

/*
 * Actions
 */

// Add new type
if ($action == 'add' && $permwrite) {
	$wtype = new SvcWarrantyType($db);
	$wtype->code                  = GETPOST('code', 'alpha');
	$wtype->label                 = GETPOST('label', 'alphanohtml');
	$wtype->description           = GETPOST('description', 'restricthtml');
	$wtype->coverage_terms        = GETPOST('coverage_terms', 'restricthtml');
	$wtype->exclusions            = GETPOST('exclusions', 'restricthtml');
	$wtype->default_coverage_days = GETPOST('default_coverage_days', 'int');
	$wtype->active                = 1;
	$wtype->position              = GETPOST('position', 'int');

	if (empty($wtype->code) || empty($wtype->label)) {
		setEventMessages($langs->trans('ErrorWarrantyTypeCodeLabelRequired'), null, 'errors');
	} else {
		$result = $wtype->create($user);
		if ($result > 0) {
			setEventMessages($langs->trans('WarrantyTypeAdded'), null, 'mesgs');
		} else {
			setEventMessages($wtype->error, $wtype->errors, 'errors');
		}
	}
	$action = '';
}

// Update existing type
if ($action == 'update' && $permwrite) {
	$wtype = new SvcWarrantyType($db);
	$wtype->fetch($id);
	$wtype->code                  = GETPOST('code', 'alpha');
	$wtype->label                 = GETPOST('label', 'alphanohtml');
	$wtype->description           = GETPOST('description', 'restricthtml');
	$wtype->coverage_terms        = GETPOST('coverage_terms', 'restricthtml');
	$wtype->exclusions            = GETPOST('exclusions', 'restricthtml');
	$wtype->default_coverage_days = GETPOST('default_coverage_days', 'int');
	$wtype->position              = GETPOST('position', 'int');

	if (empty($wtype->code) || empty($wtype->label)) {
		setEventMessages($langs->trans('ErrorWarrantyTypeCodeLabelRequired'), null, 'errors');
	} else {
		$result = $wtype->update($user);
		if ($result > 0) {
			setEventMessages($langs->trans('WarrantyTypeUpdated'), null, 'mesgs');
		} else {
			setEventMessages($wtype->error, $wtype->errors, 'errors');
		}
	}
	$action = '';
}

// Toggle active/inactive
if ($action == 'toggleactive' && $permwrite) {
	$wtype = new SvcWarrantyType($db);
	if ($wtype->fetch($id) > 0) {
		$wtype->active = $wtype->active ? 0 : 1;
		$wtype->update($user);
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

// Delete type
if ($action == 'confirm_delete' && $permwrite) {
	$wtype = new SvcWarrantyType($db);
	$wtype->fetch($id);
	$result = $wtype->delete($user);
	if ($result > 0) {
		setEventMessages($langs->trans('WarrantyTypeDeleted'), null, 'mesgs');
	} else {
		setEventMessages($wtype->error, $wtype->errors, 'errors');
	}
	$action = '';
}

/*
 * View
 */

llxHeader('', $langs->trans('WarrantyTypes'), '');

print load_fiche_titre($langs->trans('WarrantyTypes'), '', 'bill');

// Confirmation dialog for delete
if ($action == 'delete') {
	print $form->formconfirm(
		$_SERVER['PHP_SELF'].'?id='.$id,
		$langs->trans('DeleteWarrantyType'),
		$langs->trans('ConfirmDeleteWarrantyType'),
		'confirm_delete',
		'',
		0,
		1
	);
}

$form = new Form($db);

$wtype_obj = new SvcWarrantyType($db);
$types     = $wtype_obj->fetchAll();

/*
 * List of existing types
 */
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Code').'</td>';
print '<td>'.$langs->trans('Label').'</td>';
print '<td>'.$langs->trans('Description').'</td>';
print '<td>'.$langs->trans('CoverageTerms').'</td>';
print '<td>'.$langs->trans('Exclusions').'</td>';
print '<td class="center">'.$langs->trans('DefaultCoverageDays').'</td>';
print '<td class="center">'.$langs->trans('Position').'</td>';
print '<td class="center">'.$langs->trans('SvcActive').'</td>';
print '<td class="center"></td>';
print '</tr>';

foreach ($types as $t) {
	if ($action == 'edit' && $id == $t->rowid) {
		// Inline edit row
		print '<tr class="oddeven">';
		print '<input type="hidden" name="action" value="update">';
		print '<input type="hidden" name="id" value="'.$t->rowid.'">';
		print '<td><input type="text" name="code" class="flat maxwidth100" value="'.dol_escape_htmltag($t->code).'" required></td>';
		print '<td><input type="text" name="label" class="flat minwidth200" value="'.dol_escape_htmltag($t->label).'" required></td>';
		print '<td><input type="text" name="description" class="flat minwidth200" value="'.dol_escape_htmltag($t->description).'"></td>';
		print '<td><textarea name="coverage_terms" class="flat" rows="3" style="width:100%;min-width:180px">'.dol_escape_htmltag($t->coverage_terms, 1).'</textarea></td>';
		print '<td><textarea name="exclusions" class="flat" rows="3" style="width:100%;min-width:180px">'.dol_escape_htmltag($t->exclusions, 1).'</textarea></td>';
		print '<td class="center"><input type="number" name="default_coverage_days" class="flat width50" min="1" max="3650" value="'.((int) $t->default_coverage_days).'"></td>';
		print '<td class="center"><input type="number" name="position" class="flat width50" value="'.((int) $t->position).'"></td>';
		print '<td class="center">—</td>';
		print '<td class="center">';
		print '<input type="submit" class="button button-save small" value="'.$langs->trans('Save').'">';
		print ' <a class="button button-cancel small" href="'.$_SERVER['PHP_SELF'].'">'.$langs->trans('Cancel').'</a>';
		print '</td>';
		print '</tr>';
	} else {
		// View row
		print '<tr class="oddeven">';
		print '<td><code>'.dol_escape_htmltag($t->code).'</code></td>';
		print '<td>'.dol_escape_htmltag($t->label).'</td>';
		print '<td class="opacitymedium">'.dol_escape_htmltag($t->description).'</td>';
		print '<td class="opacitymedium">'.($t->coverage_terms ? '<span title="'.dol_escape_htmltag(dol_string_nohtmltag($t->coverage_terms)).'">'.dol_escape_htmltag(dol_trunc(dol_string_nohtmltag($t->coverage_terms), 60)).'</span>' : '').'</td>';
		print '<td class="opacitymedium">'.($t->exclusions     ? '<span title="'.dol_escape_htmltag(dol_string_nohtmltag($t->exclusions)).'">'.dol_escape_htmltag(dol_trunc(dol_string_nohtmltag($t->exclusions), 60)).'</span>' : '').'</td>';
		print '<td class="center">'.((int) $t->default_coverage_days).' '.$langs->trans('SvcDays').'</td>';
		print '<td class="center">'.((int) $t->position).'</td>';
		print '<td class="center">';
		if ($permwrite) {
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=toggleactive&token='.newToken().'&id='.$t->rowid.'">';
			print img_picto($t->active ? $langs->trans('SvcActive') : $langs->trans('Inactive'), $t->active ? 'switch_on' : 'switch_off');
			print '</a>';
		} else {
			print img_picto($t->active ? $langs->trans('SvcActive') : $langs->trans('Inactive'), $t->active ? 'switch_on' : 'switch_off');
		}
		print '</td>';
		print '<td class="center">';
		if ($permwrite) {
			print '<a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?action=edit&token='.newToken().'&id='.$t->rowid.'">'.img_picto($langs->trans('Edit'), 'edit').'</a>';
			print ' &nbsp; ';
			print '<a class="deletefielda" href="'.$_SERVER['PHP_SELF'].'?action=delete&token='.newToken().'&id='.$t->rowid.'">'.img_picto($langs->trans('Delete'), 'delete').'</a>';
		}
		print '</td>';
		print '</tr>';
	}
}

print '</table>';
print '</form>';

/*
 * Add new type form
 */
if ($permwrite && $action != 'edit') {
	print '<br>';
	print load_fiche_titre($langs->trans('AddWarrantyType'), '', '');

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print '<table class="border centpercent tableforfieldcreate">';

	print '<tr><td class="fieldrequired">'.$form->textwithpicto($langs->trans('Code'), $langs->trans('TooltipWTypeCode')).'</td>';
	print '<td><input type="text" name="code" class="flat maxwidth100" placeholder="e.g. standard" required></td></tr>';

	print '<tr><td class="fieldrequired">'.$form->textwithpicto($langs->trans('Label'), $langs->trans('TooltipWTypeLabel')).'</td>';
	print '<td><input type="text" name="label" class="flat minwidth300" placeholder="'.$langs->trans('WarrantyTypeLabel').'" required></td></tr>';

	print '<tr><td>'.$form->textwithpicto($langs->trans('Description'), $langs->trans('TooltipWTypeDescription')).'</td>';
	print '<td><input type="text" name="description" class="flat minwidth300" placeholder="e.g. Covers parts and labour for standard equipment"></td></tr>';

	print '<tr><td class="tdtop">'.$form->textwithpicto($langs->trans('CoverageTerms'), $langs->trans('TooltipWTypeCoverageTerms')).'</td>';
	print '<td><textarea name="coverage_terms" class="flat" rows="4" style="width:90%" placeholder="'.$langs->trans('CoverageTermsPlaceholder').'"></textarea></td></tr>';

	print '<tr><td class="tdtop">'.$form->textwithpicto($langs->trans('Exclusions'), $langs->trans('TooltipWTypeExclusions')).'</td>';
	print '<td><textarea name="exclusions" class="flat" rows="3" style="width:90%" placeholder="'.$langs->trans('ExclusionsPlaceholder').'"></textarea></td></tr>';

	print '<tr><td>'.$form->textwithpicto($langs->trans('DefaultCoverageDays'), $langs->trans('TooltipWTypeDefaultDays')).'</td>';
	print '<td><input type="number" name="default_coverage_days" class="flat width75" value="365" min="1" max="3650"> '.$langs->trans('SvcDays').'</td></tr>';

	print '<tr><td>'.$form->textwithpicto($langs->trans('Position'), $langs->trans('TooltipWTypePosition')).'</td>';
	print '<td><input type="number" name="position" class="flat width75" value="0"></td></tr>';

	print '</table>';

	print '<div class="center" style="padding-top:10px">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans('Add').'">';
	print '</div>';
	print '</form>';
}

llxFooter();
$db->close();
