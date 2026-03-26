<?php
/**
 * Debug: show element_element rows and getElementProperties results for an SR
 * Usage: /custom/warrantysvc/ajax/debug_links.php?id=11
 */
$res = 0;
if (!$res && file_exists("../../main.inc.php"))    { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php"))  { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")){ $res = @include "../../../../main.inc.php"; }
if (!$res) { http_response_code(500); exit; }

if (!$user->admin) { http_response_code(403); print 'Admin only'; exit; }

$id = GETPOSTINT('id');
header('Content-Type: text/plain');

print "=== DEBUG LINKED OBJECTS FOR SR id=$id ===\n\n";

// 1. Show what's in element_element for this SR
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcrequest.class.php';
$sr = new SvcRequest($db);
$sr->fetch($id);

print "SR ref: $sr->ref\n";
print "SR id: $sr->id\n";
print "SR element: $sr->element\n";
print "SR module: $sr->module\n";
print "SR getElementType(): ".$sr->getElementType()."\n\n";

print "FK values:\n";
print "  fk_warranty: $sr->fk_warranty\n";
print "  fk_commande: $sr->fk_commande\n";
print "  fk_shipment: $sr->fk_shipment\n";
print "  fk_facture: $sr->fk_facture\n";
print "  fk_reception: $sr->fk_reception\n";
print "  fk_intervention: $sr->fk_intervention\n\n";

// 2. Query element_element directly
$sql = "SELECT rowid, fk_source, sourcetype, fk_target, targettype FROM ".MAIN_DB_PREFIX."element_element"
    ." WHERE (fk_source = $id AND sourcetype LIKE '%svcrequest%')"
    ." OR (fk_target = $id AND targettype LIKE '%svcrequest%')"
    ." ORDER BY rowid";
$resql = $db->query($sql);
print "element_element rows matching SR $id:\n";
if ($resql) {
    $count = 0;
    while ($obj = $db->fetch_object($resql)) {
        $count++;
        print "  [$obj->rowid] source=$obj->fk_source ($obj->sourcetype) → target=$obj->fk_target ($obj->targettype)\n";
    }
    if ($count == 0) print "  (none found)\n";
} else {
    print "  SQL ERROR: ".$db->lasterror()."\n";
}

// 3. Test fetchObjectLinked
print "\nfetchObjectLinked() result:\n";
$sr->fetchObjectLinked();
print "  linkedObjectsIds: ".print_r($sr->linkedObjectsIds, true)."\n";
print "  linkedObjects keys: ".implode(', ', array_keys($sr->linkedObjects))."\n";
foreach ($sr->linkedObjects as $type => $objs) {
    print "  type=$type: ".count($objs)." objects\n";
    foreach ($objs as $key => $obj) {
        print "    [$key] class=".get_class($obj)." ref=".$obj->ref." id=".$obj->id."\n";
    }
}

// 4. Test getElementProperties for our types
print "\ngetElementProperties tests:\n";
foreach (array('svcwarranty', 'warrantysvc_svcwarranty', 'svcrequest', 'warrantysvc_svcrequest') as $type) {
    $props = getElementProperties($type);
    print "  $type → module=".$props['module']." classname=".$props['classname']." classpath=".$props['classpath']." classfile=".$props['classfile']."\n";
    print "    isModEnabled(".$props['module']."): ".(isModEnabled($props['module']) ? 'YES' : 'NO')."\n";
}
