<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    ajax/debug.php
 * \ingroup warrantysvc
 * \brief   Comprehensive debug diagnostics for the warrantysvc module.
 *          Gated by admin permission + WARRANTYSVC_DEBUG_MODE setting.
 *
 * Modes (via ?mode=):
 *   overview    — Module config, hook contexts, trigger registration, DB table health (default)
 *   object      — Deep inspect a single object (?mode=object&type=svcrequest&id=11)
 *   links       — All element_element rows involving this module's types
 *   settings    — All WARRANTYSVC_* constants from llx_const
 *   classes     — Class loading + method availability for all module objects
 *   sql         — Run a read-only diagnostic query (?mode=sql&q=SELECT...)
 *   triggers    — List all registered triggers and check ours is loaded
 *   hooks       — Show registered hook contexts and verify our hooks fire
 *   all         — Run every diagnostic at once
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

if (!$user->admin) { http_response_code(403); print 'Admin only'; exit; }
if (!getDolGlobalInt('WARRANTYSVC_DEBUG_MODE')) {
	http_response_code(403);
	print 'Debug mode not enabled. Go to Warranty > Setup and enable Debug Mode.';
	exit;
}

header('Content-Type: text/plain; charset=utf-8');

$mode = GETPOST('mode', 'alpha') ?: 'overview';
$run_all = ($mode === 'all');

// Module metadata
$MODULE_NAME   = 'warrantysvc';
$MODULE_UPPER  = 'WARRANTYSVC';
$OBJECTS = array(
	'svcrequest' => array(
		'class'      => 'SvcRequest',
		'classfile'  => 'svcrequest',
		'table'      => 'svc_request',
		'prefixed'   => 'warrantysvc_svcrequest',
		'fk_fields'  => array('fk_warranty', 'fk_commande', 'fk_shipment', 'fk_facture', 'fk_reception', 'fk_intervention', 'fk_soc', 'fk_product', 'fk_project', 'fk_user_assigned'),
	),
	'svcwarranty' => array(
		'class'      => 'SvcWarranty',
		'classfile'  => 'svcwarranty',
		'table'      => 'svc_warranty',
		'prefixed'   => 'warrantysvc_svcwarranty',
		'fk_fields'  => array('fk_soc', 'fk_product', 'fk_contract', 'fk_commande', 'fk_expedition'),
	),
);

print "=== WARRANTYSVC DEBUG DIAGNOSTICS ===\n";
print "Timestamp: ".date('Y-m-d H:i:s T')."\n";
print "Dolibarr: ".(defined('DOL_VERSION') ? DOL_VERSION : 'unknown')."\n";
print "DB prefix: ".MAIN_DB_PREFIX."\n";
print "Module version: ".getDolGlobalString('MAIN_MODULE_WARRANTYSVC_VERSION', 'unknown')."\n";
print "Mode: $mode\n";
print "Usage: ?mode=overview|object|links|settings|classes|sql|triggers|hooks|all\n";
print "       ?mode=object&type=svcrequest&id=11\n";
print "       ?mode=sql&q=SELECT+rowid,ref+FROM+llx_svc_request+LIMIT+5\n";
print str_repeat('=', 60)."\n\n";


// =====================================================================
// OVERVIEW — module status, config, table health
// =====================================================================
if ($mode === 'overview' || $run_all) {
	print "--- MODULE STATUS ---\n";
	print "isModEnabled('warrantysvc'): ".(isModEnabled('warrantysvc') ? 'YES' : 'NO')."\n";
	print "conf->warrantysvc->enabled: ".(isset($conf->warrantysvc->enabled) ? $conf->warrantysvc->enabled : '(not set)')."\n";

	// Check module_parts registration
	print "\nRegistered module_parts:\n";
	if (isset($conf->modules_parts)) {
		foreach (array('hooks', 'triggers', 'tpl') as $part) {
			if (isset($conf->modules_parts[$part])) {
				$found = false;
				foreach ($conf->modules_parts[$part] as $k => $v) {
					if (stripos($k, 'warrantysvc') !== false || stripos(print_r($v, true), 'warrantysvc') !== false) {
						print "  $part: ".print_r($v, true);
						$found = true;
					}
				}
				if (!$found) print "  $part: (warrantysvc not found in registered parts)\n";
			} else {
				print "  $part: (not in modules_parts)\n";
			}
		}
	}

	// DB table health
	print "\n--- DATABASE TABLES ---\n";
	$tables = array('svc_request', 'svc_warranty', 'svc_warranty_type', 'svc_request_line', 'svc_service_log',
		'svc_request_extrafields', 'svc_warranty_extrafields');
	foreach ($tables as $tbl) {
		$sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX.$tbl;
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			print "  llx_$tbl: ".$obj->cnt." rows\n";
		} else {
			print "  llx_$tbl: TABLE MISSING OR ERROR\n";
		}
	}

	// Element properties resolution
	print "\n--- ELEMENT PROPERTIES ---\n";
	foreach ($OBJECTS as $bare => $odef) {
		foreach (array($bare, $odef['prefixed']) as $etype) {
			$props = getElementProperties($etype);
			$ok = (!empty($props['classname']) && $props['classname'] === $odef['class']);
			print "  $etype → classname=".$props['classname']." ".($ok ? 'OK' : 'MISMATCH (expected '.$odef['class'].')')."\n";
		}
	}

	// Template files
	print "\n--- LINKED OBJECT TEMPLATES ---\n";
	foreach ($OBJECTS as $bare => $odef) {
		$tplpath = $MODULE_NAME.'/'.$bare.'/tpl/linkedobjectblock.tpl.php';
		$fullpath = dol_buildpath('/'.$tplpath);
		print "  $tplpath: ".(file_exists($fullpath) ? 'EXISTS' : 'MISSING ('.$fullpath.')')."\n";
	}

	print "\n";
}


// =====================================================================
// OBJECT — deep inspection of a single record
// =====================================================================
if ($mode === 'object' || $run_all) {
	$otype = GETPOST('type', 'alpha') ?: 'svcrequest';
	$oid   = GETPOSTINT('id');

	if ($oid <= 0 && !$run_all) {
		print "--- OBJECT DIAGNOSIS ---\nUsage: ?mode=object&type=svcrequest&id=11\n\n";
	} elseif ($oid > 0) {
		$odef = isset($OBJECTS[$otype]) ? $OBJECTS[$otype] : null;
		if (!$odef) {
			print "--- OBJECT DIAGNOSIS ---\nUnknown type '$otype'. Available: ".implode(', ', array_keys($OBJECTS))."\n\n";
		} else {
			print "--- OBJECT DIAGNOSIS: $otype id=$oid ---\n";
			dol_include_once('/'.$MODULE_NAME.'/class/'.$odef['classfile'].'.class.php');
			$classname = $odef['class'];

			if (!class_exists($classname)) {
				print "  Class $classname NOT FOUND after include!\n\n";
			} else {
				$obj = new $classname($db);
				$fetch_result = $obj->fetch($oid);
				print "  fetch() returned: $fetch_result\n";

				if ($fetch_result > 0) {
					print "  ref: $obj->ref\n";
					print "  element: $obj->element\n";
					print "  module: ".(property_exists($obj, 'module') ? ($obj->module ?: '(empty)') : '(NOT DEFINED)')."\n";
					print "  getElementType(): ".$obj->getElementType()."\n";
					print "  getNomUrl(): ".(method_exists($obj, 'getNomUrl') ? 'defined' : 'MISSING')."\n";
					print "  getLibStatut(): ".(method_exists($obj, 'getLibStatut') ? 'defined' : 'MISSING')."\n";

					// FK fields
					print "\n  FK fields (non-empty):\n";
					$has_fk = false;
					foreach ($odef['fk_fields'] as $fk) {
						$val = isset($obj->$fk) ? $obj->$fk : null;
						if (!empty($val)) {
							print "    $fk = $val\n";
							$has_fk = true;
						}
					}
					if (!$has_fk) print "    (none populated)\n";

					// Status
					print "\n  Status: ".$obj->status."\n";

					// element_element — search with both prefixed and bare types
					$etype = $obj->getElementType();
					print "\n  element_element rows:\n";

					$search_types = array($etype);
					if ($etype !== $obj->element) {
						$search_types[] = $obj->element;
					}
					$where_parts = array();
					foreach ($search_types as $st) {
						$where_parts[] = "(fk_source = $oid AND sourcetype = '".$db->escape($st)."')";
						$where_parts[] = "(fk_target = $oid AND targettype = '".$db->escape($st)."')";
					}
					// Also wildcard search for any mention of this ID with module-related types
					$where_parts[] = "(fk_source = $oid AND sourcetype LIKE '%".$db->escape($MODULE_NAME)."%')";
					$where_parts[] = "(fk_target = $oid AND targettype LIKE '%".$db->escape($MODULE_NAME)."%')";
					$where_parts[] = "(fk_source = $oid AND sourcetype LIKE '%".$db->escape($obj->element)."%')";
					$where_parts[] = "(fk_target = $oid AND targettype LIKE '%".$db->escape($obj->element)."%')";

					$sql = "SELECT DISTINCT rowid, fk_source, sourcetype, fk_target, targettype FROM ".MAIN_DB_PREFIX."element_element WHERE ".implode(" OR ", $where_parts)." ORDER BY rowid";
					$resql = $db->query($sql);
					if ($resql) {
						$cnt = 0;
						while ($row = $db->fetch_object($resql)) {
							$cnt++;
							print "    [$row->rowid] source=$row->fk_source ($row->sourcetype) → target=$row->fk_target ($row->targettype)\n";
						}
						if ($cnt == 0) print "    (none found)\n";
					}

					// fetchObjectLinked
					print "\n  fetchObjectLinked():\n";
					$obj->fetchObjectLinked();
					if (!empty($obj->linkedObjectsIds)) {
						foreach ($obj->linkedObjectsIds as $ltype => $lids) {
							print "    linkedObjectsIds[$ltype]: ".implode(', ', $lids)."\n";
						}
					} else {
						print "    linkedObjectsIds: (empty)\n";
					}
					if (!empty($obj->linkedObjects)) {
						foreach ($obj->linkedObjects as $ltype => $lobjs) {
							foreach ($lobjs as $lkey => $lobj) {
								print "    linkedObjects[$ltype][$lkey]: ".get_class($lobj)." ref=".$lobj->ref."\n";
							}
						}
					} else {
						print "    linkedObjects: (empty)\n";
					}

					// Template check for each linked type
					if (!empty($obj->linkedObjectsIds)) {
						print "\n  Template availability for linked types:\n";
						foreach (array_keys($obj->linkedObjectsIds) as $ltype) {
							$tregs = array();
							if (preg_match('/^([^_]+)_([^_]+)/i', $ltype, $tregs)) {
								$tplpath = $tregs[1].'/'.$tregs[2];
							} else {
								$tplpath = $ltype;
							}
							$tplfile = dol_buildpath('/'.$tplpath.'/tpl/linkedobjectblock.tpl.php');
							print "    $ltype → $tplpath/tpl/linkedobjectblock.tpl.php: ".(file_exists($tplfile) ? 'EXISTS' : 'MISSING')."\n";
						}
					}
				}
			}
			print "\n";
		}
	}
}


// =====================================================================
// LINKS — all element_element rows for this module
// =====================================================================
if ($mode === 'links' || $run_all) {
	print "--- ALL ELEMENT_ELEMENT ROWS FOR WARRANTYSVC ---\n";

	$type_patterns = array();
	foreach ($OBJECTS as $bare => $odef) {
		$type_patterns[] = "sourcetype LIKE '%".$db->escape($bare)."%'";
		$type_patterns[] = "targettype LIKE '%".$db->escape($bare)."%'";
	}

	$sql = "SELECT rowid, fk_source, sourcetype, fk_target, targettype FROM ".MAIN_DB_PREFIX."element_element WHERE ".implode(" OR ", $type_patterns)." ORDER BY rowid DESC LIMIT 50";
	$resql = $db->query($sql);
	if ($resql) {
		$cnt = 0;
		while ($row = $db->fetch_object($resql)) {
			$cnt++;
			print "  [$row->rowid] source=$row->fk_source ($row->sourcetype) → target=$row->fk_target ($row->targettype)\n";
		}
		print "  Total: $cnt rows (max 50 shown)\n";
	}
	print "\n";
}


// =====================================================================
// SETTINGS — all module constants
// =====================================================================
if ($mode === 'settings' || $run_all) {
	print "--- WARRANTYSVC SETTINGS ---\n";

	$sql = "SELECT name, value, note FROM ".MAIN_DB_PREFIX."const WHERE name LIKE '".$MODULE_UPPER."%' AND entity IN (0, ".((int) $conf->entity).") ORDER BY name";
	$resql = $db->query($sql);
	if ($resql) {
		while ($row = $db->fetch_object($resql)) {
			$display_val = strlen($row->value) > 80 ? substr($row->value, 0, 80).'...' : $row->value;
			print "  $row->name = $display_val\n";
		}
	}
	print "\n";
}


// =====================================================================
// CLASSES — class loading and method checks
// =====================================================================
if ($mode === 'classes' || $run_all) {
	print "--- CLASS LOADING & METHODS ---\n";

	foreach ($OBJECTS as $bare => $odef) {
		print "  $bare ({$odef['class']}):\n";
		$inc = @dol_include_once('/'.$MODULE_NAME.'/class/'.$odef['classfile'].'.class.php');
		print "    dol_include_once: ".($inc ? 'OK' : 'FAILED')."\n";
		print "    class_exists: ".(class_exists($odef['class']) ? 'YES' : 'NO')."\n";

		if (class_exists($odef['class'])) {
			$required_methods = array('create', 'fetch', 'update', 'delete', 'validate', 'getNomUrl', 'getLibStatut', 'getNextNumRef');
			$obj = new $odef['class']($db);
			print "    \$module property: ".(property_exists($obj, 'module') ? ($obj->module ?: '(empty)') : 'NOT DEFINED')."\n";
			print "    \$element: ".$obj->element."\n";
			print "    getElementType(): ".$obj->getElementType()."\n";

			$methods_ok = true;
			$missing = array();
			foreach ($required_methods as $m) {
				if (!method_exists($obj, $m)) {
					$methods_ok = false;
					$missing[] = $m;
				}
			}
			print "    Required methods: ".($methods_ok ? 'ALL PRESENT' : 'MISSING: '.implode(', ', $missing))."\n";

			// Check syncLinkedObjects if it's SvcRequest
			if ($bare === 'svcrequest') {
				print "    syncLinkedObjects(): ".(method_exists($obj, 'syncLinkedObjects') ? 'defined' : 'MISSING')."\n";
			}
		}
		print "\n";
	}
}


// =====================================================================
// SQL — run a read-only diagnostic query
// =====================================================================
if ($mode === 'sql') {
	$q = GETPOST('q', 'restricthtml');
	print "--- SQL QUERY ---\n";

	if (empty($q)) {
		print "Usage: ?mode=sql&q=SELECT+rowid,ref+FROM+llx_svc_request+LIMIT+5\n";
		print "\nUseful queries:\n";
		print "  ?mode=sql&q=SELECT rowid,ref,fk_warranty,fk_commande,fk_shipment,status FROM llx_svc_request ORDER BY rowid DESC LIMIT 10\n";
		print "  ?mode=sql&q=SELECT rowid,ref,serial_number,status,claim_count FROM llx_svc_warranty ORDER BY rowid DESC LIMIT 10\n";
		print "  ?mode=sql&q=SELECT * FROM llx_element_element WHERE sourcetype LIKE '%svc%' OR targettype LIKE '%svc%' ORDER BY rowid DESC LIMIT 20\n";
		print "  ?mode=sql&q=SELECT * FROM llx_svc_service_log ORDER BY rowid DESC LIMIT 10\n";
	} else {
		// Safety: only allow SELECT statements
		$q_trimmed = trim($q);
		if (stripos($q_trimmed, 'SELECT') !== 0) {
			print "ERROR: Only SELECT queries allowed.\n";
		} else {
			// Block dangerous keywords
			$blocked = array('INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE', 'GRANT', 'REVOKE');
			$safe = true;
			foreach ($blocked as $kw) {
				if (stripos($q_trimmed, $kw) !== false && stripos($q_trimmed, $kw) !== stripos($q_trimmed, 'SELECT')) {
					$safe = false;
					break;
				}
			}
			if (!$safe) {
				print "ERROR: Query contains blocked keywords.\n";
			} else {
				// Add LIMIT if not present
				if (stripos($q_trimmed, 'LIMIT') === false) {
					$q_trimmed .= ' LIMIT 50';
				}

				// Auto-replace llx_ with actual DB prefix
				$q_trimmed = str_replace('llx_', MAIN_DB_PREFIX, $q_trimmed);

				print "Query: $q_trimmed\n\n";
				$resql = $db->query($q_trimmed);
				if ($resql) {
					$num = $db->num_rows($resql);
					$first = true;
					$row_num = 0;
					while ($obj = $db->fetch_array($resql)) {
						if ($first) {
							// Print column headers
							print implode("\t", array_keys($obj))."\n";
							print str_repeat('-', 80)."\n";
							$first = false;
						}
						$row_num++;
						$vals = array();
						foreach ($obj as $v) {
							$vals[] = ($v === null) ? 'NULL' : (strlen($v) > 40 ? substr($v, 0, 40).'...' : $v);
						}
						print implode("\t", $vals)."\n";
					}
					print "\n$row_num rows returned.\n";
				} else {
					print "SQL ERROR: ".$db->lasterror()."\n";
				}
			}
		}
	}
	print "\n";
}


// =====================================================================
// TRIGGERS — check trigger registration
// =====================================================================
if ($mode === 'triggers' || $run_all) {
	print "--- TRIGGER REGISTRATION ---\n";

	// Scan for trigger files in our module
	$trigger_dir = DOL_DOCUMENT_ROOT.'/custom/'.$MODULE_NAME.'/core/triggers';
	if (is_dir($trigger_dir)) {
		$files = scandir($trigger_dir);
		foreach ($files as $f) {
			if (preg_match('/^interface_.*\.class\.php$/', $f)) {
				print "  Found trigger file: $f\n";
				include_once $trigger_dir.'/'.$f;
				$classname = str_replace('.class.php', '', $f);
				print "    Class exists: ".(class_exists($classname) ? 'YES' : 'NO')."\n";
			}
		}
	} else {
		print "  Trigger directory not found: $trigger_dir\n";
	}

	// Check which events our trigger handles by looking at the switch cases
	$trigger_file = $trigger_dir.'/interface_99_modWarrantySvc_WarrantySvcTrigger.class.php';
	if (file_exists($trigger_file)) {
		$content = file_get_contents($trigger_file);
		preg_match_all("/case\s+'([^']+)'/", $content, $matches);
		if (!empty($matches[1])) {
			print "\n  Events handled:\n";
			foreach ($matches[1] as $event) {
				print "    - $event\n";
			}
		}
	}
	print "\n";
}


// =====================================================================
// HOOKS — check hook context registration
// =====================================================================
if ($mode === 'hooks' || $run_all) {
	print "--- HOOK REGISTRATION ---\n";

	// Check what contexts are registered for our module
	$sql_hooks = "SELECT name, value FROM ".MAIN_DB_PREFIX."const WHERE name = 'MAIN_MODULE_WARRANTYSVC_HOOKS' AND entity IN (0, ".((int) $conf->entity).")";
	$resql = $db->query($sql_hooks);
	if ($resql && ($row = $db->fetch_object($resql))) {
		print "  MAIN_MODULE_WARRANTYSVC_HOOKS = $row->value\n";
	}

	// Check from module descriptor
	print "\n  Hook contexts from conf->modules_parts['hooks']:\n";
	if (isset($conf->modules_parts['hooks'])) {
		foreach ($conf->modules_parts['hooks'] as $context => $modules) {
			if (is_array($modules)) {
				foreach ($modules as $mod) {
					if (stripos($mod, 'warrantysvc') !== false) {
						print "    context='$context' module='$mod'\n";
					}
				}
			} elseif (stripos($modules, 'warrantysvc') !== false) {
				print "    context='$context' module='$modules'\n";
			}
		}
	}

	// Test actions class loading
	print "\n  Actions class:\n";
	$actions_file = DOL_DOCUMENT_ROOT.'/custom/'.$MODULE_NAME.'/class/actions_'.$MODULE_NAME.'.class.php';
	print "    File exists: ".(file_exists($actions_file) ? 'YES' : 'NO')."\n";
	if (file_exists($actions_file)) {
		include_once $actions_file;
		$actions_class = 'ActionsWarrantysvc';
		print "    Class exists: ".(class_exists($actions_class) ? 'YES' : 'NO')."\n";
		if (class_exists($actions_class)) {
			$methods = array('getElementProperties', 'formObjectOptions', 'showLinkToObjectBlock');
			foreach ($methods as $m) {
				print "    method $m(): ".(method_exists($actions_class, $m) ? 'defined' : 'MISSING')."\n";
			}
		}
	}
	print "\n";
}






print "=== END DEBUG ===\n";
