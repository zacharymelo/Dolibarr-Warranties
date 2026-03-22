<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    ajax/projects.php
 * \ingroup warrantysvc
 * \brief   Returns JSON list of open projects for a given third party
 *
 * GET params:
 *   socid  (int, required) — third party to filter by
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php"))     { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))   { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

if (!$user->id || !$user->hasRight('warrantysvc', 'svcrequest', 'read')) {
	http_response_code(403);
	exit;
}

$socid = (int) GETPOST('socid', 'int');
if ($socid <= 0) {
	header('Content-Type: application/json');
	print '[]';
	exit;
}

$sql  = "SELECT p.rowid, p.ref, p.title FROM ".MAIN_DB_PREFIX."projet p";
$sql .= " WHERE p.fk_soc = ".((int) $socid);
$sql .= " AND p.entity IN (".getEntity('project').")";
$sql .= " AND p.fk_statut != 2";
$sql .= " ORDER BY p.ref ASC";

$resql = $db->query($sql);
$projects = array();
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$projects[] = array(
			'rowid' => (int) $obj->rowid,
			'label' => $obj->ref.($obj->title ? ' \u2014 '.$obj->title : ''),
		);
	}
}

header('Content-Type: application/json');
print json_encode($projects);
