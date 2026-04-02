<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    troubleshoot.php
 * \ingroup warrantysvc
 * \brief   STRETCH: Guided troubleshooting workflow for a Service Request
 *
 * Presents a checklist of diagnostic steps for the product associated with
 * the service request. Agents can mark steps done and record findings.
 * Findings are saved as a structured JSON note appended to note_private.
 *
 * No additional database table is required — data is stored in
 * llx_svc_request.note_private as a structured block.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) { $res = @include "../main.inc.php"; }
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) { die("Include of main fails"); }

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcrequest.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/lib/warrantysvc.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/lib/troubleshoot.lib.php';

$langs->loadLangs(array('warrantysvc@warrantysvc'));

$id     = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');

$object = new SvcRequest($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	dol_print_error($db, $object->error);
	exit;
}

if (!$user->hasRight('warrantysvc', 'svcrequest', 'read')) {
	accessforbidden();
}

$permwrite = $user->hasRight('warrantysvc', 'svcrequest', 'write');

/*
 * Actions
 */
if ($cancel) {
	$action = '';
}

if ($action == 'save_findings' && $permwrite) {
	// Read submitted checklist results
	$steps  = warrantysvc_checklist_steps($object->fk_product);
	$checks = array();
	foreach ($steps as $key => $step) {
		$checks[$key] = array(
			'done'    => GETPOST('check_'.$key, 'int') ? 1 : 0,
			'finding' => GETPOST('finding_'.$key, 'alphanohtml'),
		);
	}
	$summary  = GETPOST('troubleshoot_summary', 'restricthtml');
	$outcome  = GETPOST('troubleshoot_outcome', 'alpha');

	// Build structured block to inject into note_private
	$block  = "\n\n--- TROUBLESHOOT ".dol_print_date(dol_now(), 'dayhour')." ---\n";
	foreach ($steps as $key => $step) {
		$done    = !empty($checks[$key]['done']) ? '[x]' : '[ ]';
		$finding = !empty($checks[$key]['finding']) ? ' — '.$checks[$key]['finding'] : '';
		$block  .= $done.' '.$step['label'].$finding."\n";
	}
	if ($summary) {
		$block .= "Summary: ".$summary."\n";
	}
	if ($outcome) {
		$block .= "Outcome: ".$langs->trans('TroubleshootOutcome_'.$outcome)."\n";
	}
	$block .= "---\n";

	$object->note_private = ($object->note_private ? $object->note_private : '').$block;

	// If outcome is informational, update resolution type
	if ($outcome == 'no_fault') {
		$object->resolution_type = SvcRequest::RESOLUTION_INFORMATIONAL;
	}

	$object->update($user);
	setEventMessages($langs->trans('TroubleshootSaved'), null, 'mesgs');
	$action = '';
}

/*
 * View
 */
$form = new Form($db);
$head = warrantysvc_prepare_head($object);

llxHeader('', $langs->trans('Troubleshoot').' — '.$object->ref, '');

print dol_get_fiche_head($head, 'troubleshoot', $langs->trans('ServiceRequest'), -1, 'technic');

$linkback = '<a href="'.DOL_URL_ROOT.'/custom/warrantysvc/list.php">'.$langs->trans('BackToList').'</a>';
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', svcrequest_status_badge($object->status));

print dol_get_fiche_end();

// ---- CHECKLIST FORM ----
print '<form action="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save_findings">';

print '<div class="fichecenter">';

// Product info banner
print '<div class="underbanner clearboth">';
if ($object->fk_product) {
	$product = new Product($db);
	$product->fetch($object->fk_product);
	print '<strong>'.$langs->trans('Product').':</strong> '.$product->getNomUrl(1);
	print ' &nbsp; <strong>'.$langs->trans('SvcSerialNumber').':</strong> '.dol_escape_htmltag($object->serial_number);
}
print '</div>';

$steps = warrantysvc_checklist_steps($object->fk_product);

print '<table class="border centpercent tableforfieldcreate" style="margin-top:12px">';
print '<tr class="liste_titre">';
print '<th style="width:36px">'.$langs->trans('SvcDone').'</th>';
print '<th>'.$langs->trans('DiagnosticStep').'</th>';
print '<th>'.$langs->trans('Finding').'</th>';
print '</tr>';

foreach ($steps as $key => $step) {
	print '<tr class="oddeven">';
	print '<td class="center"><input type="checkbox" name="check_'.$key.'" value="1"></td>';
	print '<td>';
	print '<strong>'.dol_escape_htmltag($step['label']).'</strong>';
	if (!empty($step['description'])) {
		print '<br><span class="opacitymedium small">'.dol_escape_htmltag($step['description']).'</span>';
	}
	print '</td>';
	print '<td><input type="text" name="finding_'.$key.'" class="flat" style="width:100%" placeholder="'.$langs->trans('FindingOptional').'"></td>';
	print '</tr>';
}

print '</table>';

print '<br>';

// Summary text
print '<div class="tagtable">';
print '<table class="border centpercent">';
print '<tr><td class="tdtop" style="width:180px">'.$form->textwithpicto($langs->trans('TroubleshootSummary'), $langs->trans('TooltipTroubleshootSummary')).'</td>';
print '<td><textarea name="troubleshoot_summary" class="flat" rows="4" style="width:90%" placeholder="'.$langs->trans('TroubleshootSummaryPlaceholder').'"></textarea></td></tr>';

// Outcome
$outcomes = array(
	''              => $langs->trans('SelectOutcome'),
	'resolved'      => $langs->trans('TroubleshootOutcome_resolved'),
	'no_fault'      => $langs->trans('TroubleshootOutcome_no_fault'),
	'escalate'      => $langs->trans('TroubleshootOutcome_escalate'),
	'parts_needed'  => $langs->trans('TroubleshootOutcome_parts_needed'),
	'intervention'  => $langs->trans('TroubleshootOutcome_intervention'),
);
print '<tr><td>'.$form->textwithpicto($langs->trans('TroubleshootOutcome'), $langs->trans('TooltipTroubleshootOutcome')).'</td>';
print '<td>';
print Form::selectarray('troubleshoot_outcome', $outcomes, '', 0, 0, 0, '', 0, 0, 0, '', 'flat minwidth200');
print '</td></tr>';

print '</table>';
print '</div>';

print '</div>'; // fichecenter

if ($permwrite) {
	print '<div class="center" style="margin-top:12px">';
	print '<input type="submit" class="button button-save" name="save_findings" value="'.$langs->trans('SaveFindings').'">';
	print ' &nbsp; ';
	print '<a class="button button-cancel" href="'.DOL_URL_ROOT.'/custom/warrantysvc/card.php?id='.$object->id.'">'.$langs->trans('Cancel').'</a>';
	print '</div>';
}

print '</form>';

// ---- PREVIOUS TROUBLESHOOT NOTES ----
if (!empty($object->note_private) && strpos($object->note_private, '--- TROUBLESHOOT') !== false) {
	print '<br>';
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans('PreviousTroubleshootSessions').'</td></tr>';
	print '<tr class="oddeven"><td>';
	print '<pre style="white-space:pre-wrap;font-size:0.9em">'.dol_escape_htmltag($object->note_private).'</pre>';
	print '</td></tr>';
	print '</table>';
	print '</div>';
}

llxFooter();
$db->close();
