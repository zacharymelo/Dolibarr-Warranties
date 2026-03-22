<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    lib/warrantysvc.lib.php
 * \ingroup warrantysvc
 * \brief   Tab helpers and shared utility functions
 */


/**
 * Return array of tabs for a SvcRequest card
 *
 * @param  SvcRequest $object SvcRequest object
 * @return array              Tab array for dol_get_fiche_head()
 */
function warrantysvc_prepare_head($object)
{
	global $langs, $conf, $user;

	$langs->loadLangs(array('warrantysvc@warrantysvc'));

	$h   = 0;
	$head = array();

	$head[$h][0] = DOL_URL_ROOT.'/custom/warrantysvc/card.php?id='.$object->id;
	$head[$h][1] = $langs->trans('Details');
	$head[$h][2] = 'card';
	$h++;

	// Notes tab
	$head[$h][0] = DOL_URL_ROOT.'/custom/warrantysvc/note.php?id='.$object->id;
	$head[$h][1] = $langs->trans('Notes');
	// Append count badge if notes exist
	if (!empty($object->note_private) || !empty($object->note_public)) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">!</span>';
	}
	$head[$h][2] = 'note';
	$h++;

	// Troubleshoot tab
	$head[$h][0] = DOL_URL_ROOT.'/custom/warrantysvc/troubleshoot.php?id='.$object->id;
	$head[$h][1] = $langs->trans('Troubleshoot');
	$head[$h][2] = 'troubleshoot';
	$h++;

	// Documents tab
	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

	$upload_dir = !empty($conf->warrantysvc->multidir_output[$object->entity])
		? $conf->warrantysvc->multidir_output[$object->entity]
		: (isset($conf->warrantysvc->dir_output) ? $conf->warrantysvc->dir_output : '');

	$nbFiles = 0;
	if ($upload_dir) {
		$filearray = dol_dir_list($upload_dir.'/'.$object->ref, 'files', 0, '', '(\.meta|_preview.*\.png)$');
		$nbFiles   = count($filearray);
	}

	$head[$h][0] = DOL_URL_ROOT.'/custom/warrantysvc/document.php?id='.$object->id;
	$head[$h][1] = $langs->trans('Documents');
	if ($nbFiles > 0) {
		$head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbFiles.'</span>';
	}
	$head[$h][2] = 'document';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'svcrequest@warrantysvc');

	return $head;
}


/**
 * Return array of tabs for a SvcWarranty card
 *
 * @param  SvcWarranty $object SvcWarranty object
 * @return array               Tab array for dol_get_fiche_head()
 */
function svcwarranty_prepare_head($object)
{
	global $langs, $conf;

	$langs->loadLangs(array('warrantysvc@warrantysvc'));

	$h    = 0;
	$head = array();

	$head[$h][0] = DOL_URL_ROOT.'/custom/warrantysvc/warranty_card.php?id='.$object->id;
	$head[$h][1] = $langs->trans('Details');
	$head[$h][2] = 'card';
	$h++;

	$head[$h][0] = DOL_URL_ROOT.'/custom/warrantysvc/warranty_note.php?id='.$object->id;
	$head[$h][1] = $langs->trans('Notes');
	$head[$h][2] = 'note';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'svcwarranty@warrantysvc');

	return $head;
}


/**
 * Return HTML badge for a SvcRequest status
 *
 * @param  int    $status  Status code
 * @param  int    $mode    0=badge with label, 1=label only
 * @return string          HTML
 */
function svcrequest_status_badge($status, $mode = 0)
{
	global $langs;
	$langs->loadLangs(array('warrantysvc@warrantysvc'));

	$map = array(
		0 => array('label' => 'Draft',         'color' => 'status0'),
		1 => array('label' => 'Validated',      'color' => 'status1'),
		2 => array('label' => 'InProgress',     'color' => 'status3'),
		3 => array('label' => 'AwaitingReturn', 'color' => 'status4'),
		4 => array('label' => 'Resolved',       'color' => 'status6'),
		5 => array('label' => 'Closed',         'color' => 'status6'),
		9 => array('label' => 'Cancelled',      'color' => 'status9'),
	);

	$s     = isset($map[$status]) ? $map[$status] : array('label' => 'Unknown', 'color' => 'status0');
	$label = $langs->trans($s['label']);

	if ($mode == 1) {
		return $label;
	}

	return '<span class="badge '.$s['color'].'">'.$label.'</span>';
}


/**
 * Return HTML badge for a warranty status
 *
 * @param  string $status  Status string: active|expired|voided|none
 * @param  int    $mode    0=badge, 1=label only
 * @return string          HTML
 */
function svcwarranty_status_badge($status, $mode = 0)
{
	global $langs;
	$langs->loadLangs(array('warrantysvc@warrantysvc'));

	$map = array(
		'active'  => array('label' => 'Active',     'color' => 'status1'),
		'expired' => array('label' => 'Expired',    'color' => 'status8'),
		'voided'  => array('label' => 'Voided',     'color' => 'status9'),
		'none'    => array('label' => 'NoCoverage', 'color' => 'status0'),
	);

	$s     = isset($map[$status]) ? $map[$status] : array('label' => 'NoCoverage', 'color' => 'status0');
	$label = $langs->trans($s['label']);

	if ($mode == 1) {
		return $label;
	}

	return '<span class="badge '.$s['color'].'">'.$label.'</span>';
}


/**
 * Return translated label for a resolution type
 *
 * @param  string $type Resolution type constant string
 * @return string       Translated label
 */
function svcrequest_resolution_label($type)
{
	global $langs;
	$langs->loadLangs(array('warrantysvc@warrantysvc'));

	$map = array(
		'component'        => 'ResolutionComponent',
		'component_return' => 'ResolutionComponentReturn',
		'swap_cross'       => 'ResolutionSwapCross',
		'swap_wait'        => 'ResolutionSwapWait',
		'intervention'     => 'ResolutionIntervention',
		'guidance'         => 'ResolutionGuidance',
		'informational'    => 'ResolutionInformational',
	);

	return isset($map[$type]) ? $langs->trans($map[$type]) : $type;
}


/**
 * Return list of resolution types as array for select boxes
 *
 * @return array key=>label
 */
function svcrequest_resolution_types()
{
	global $langs;
	$langs->loadLangs(array('warrantysvc@warrantysvc'));

	return array(
		''               => $langs->trans('ResolutionTypeSelect'),
		'component'        => $langs->trans('ResolutionComponent'),
		'component_return' => $langs->trans('ResolutionComponentReturn'),
		'swap_cross'       => $langs->trans('ResolutionSwapCross'),
		'swap_wait'        => $langs->trans('ResolutionSwapWait'),
		'intervention'     => $langs->trans('ResolutionIntervention'),
		'guidance'         => $langs->trans('ResolutionGuidance'),
		'informational'    => $langs->trans('ResolutionInformational'),
	);
}
