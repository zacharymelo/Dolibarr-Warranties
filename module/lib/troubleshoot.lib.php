<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    lib/troubleshoot.lib.php
 * \ingroup warrantysvc
 * \brief   Troubleshooting checklist steps library
 *
 * Returns product-specific or generic diagnostic checklists for use in
 * the guided troubleshooting workflow (troubleshoot.php).
 *
 * Steps are keyed arrays: label (required), description (optional),
 * category: 'safety'|'power'|'mechanical'|'consumable'|'software'|'general'
 *
 * To add product-specific steps: add a case in the switch statement matching
 * the product's reference or a product attribute/tag. Fall through to default
 * for generic steps appended after.
 */


/**
 * Return ordered checklist steps for a product
 *
 * @param  int|null $fk_product  Product ID (0 or null = generic checklist)
 * @return array                 Keyed array of step definitions
 */
function warrantysvc_checklist_steps($fk_product = 0)
{
	global $db, $langs;

	$langs->loadLangs(array('warrantysvc@warrantysvc'));

	// Attempt to get product ref for product-specific branching
	$product_ref = '';
	if ($fk_product > 0) {
		$sql = "SELECT ref FROM ".MAIN_DB_PREFIX."product WHERE rowid = ".((int) $fk_product);
		$res = $db->query($sql);
		if ($res) {
			$obj = $db->fetch_object($res);
			if ($obj) {
				$product_ref = strtolower($obj->ref);
			}
		}
	}

	$steps = array();

	// ---- SAFETY STEPS (always first) ----
	$steps['safety_power_off'] = array(
		'label'       => $langs->trans('ChecklistSafetyPowerOff'),
		'description' => $langs->trans('ChecklistSafetyPowerOffDesc'),
		'category'    => 'safety',
	);
	$steps['safety_inspect_exterior'] = array(
		'label'       => $langs->trans('ChecklistSafetyExterior'),
		'description' => $langs->trans('ChecklistSafetyExteriorDesc'),
		'category'    => 'safety',
	);

	// ---- POWER / STARTUP ----
	$steps['power_supply'] = array(
		'label'       => $langs->trans('ChecklistPowerSupply'),
		'description' => $langs->trans('ChecklistPowerSupplyDesc'),
		'category'    => 'power',
	);
	$steps['power_startup'] = array(
		'label'       => $langs->trans('ChecklistPowerStartup'),
		'description' => $langs->trans('ChecklistPowerStartupDesc'),
		'category'    => 'power',
	);

	// ---- PRODUCT-SPECIFIC STEPS ----
	// Oxygen compressor specific (matches refs containing 'compressor' or 'oc-')
	if (strpos($product_ref, 'compressor') !== false || strpos($product_ref, 'oc-') !== false || empty($product_ref)) {
		$steps['comp_intake_filter'] = array(
			'label'       => $langs->trans('ChecklistCompIntakeFilter'),
			'description' => $langs->trans('ChecklistCompIntakeFilterDesc'),
			'category'    => 'consumable',
		);
		$steps['comp_output_pressure'] = array(
			'label'       => $langs->trans('ChecklistCompOutputPressure'),
			'description' => $langs->trans('ChecklistCompOutputPressureDesc'),
			'category'    => 'mechanical',
		);
		$steps['comp_flow_rate'] = array(
			'label'       => $langs->trans('ChecklistCompFlowRate'),
			'description' => $langs->trans('ChecklistCompFlowRateDesc'),
			'category'    => 'mechanical',
		);
		$steps['comp_moisture_trap'] = array(
			'label'       => $langs->trans('ChecklistCompMoistureTrap'),
			'description' => $langs->trans('ChecklistCompMoistureTrapDesc'),
			'category'    => 'consumable',
		);
		$steps['comp_outlet_purity'] = array(
			'label'       => $langs->trans('ChecklistCompOutletPurity'),
			'description' => $langs->trans('ChecklistCompOutletPurityDesc'),
			'category'    => 'mechanical',
		);
		$steps['comp_vibration_noise'] = array(
			'label'       => $langs->trans('ChecklistCompVibrationNoise'),
			'description' => $langs->trans('ChecklistCompVibrationNoiseDesc'),
			'category'    => 'mechanical',
		);
		$steps['comp_heat_temp'] = array(
			'label'       => $langs->trans('ChecklistCompHeatTemp'),
			'description' => $langs->trans('ChecklistCompHeatTempDesc'),
			'category'    => 'mechanical',
		);
	}

	// ---- GENERIC WRAP-UP ----
	$steps['general_customer_steps'] = array(
		'label'       => $langs->trans('ChecklistCustomerSteps'),
		'description' => $langs->trans('ChecklistCustomerStepsDesc'),
		'category'    => 'general',
	);
	$steps['general_reproduce'] = array(
		'label'       => $langs->trans('ChecklistReproduce'),
		'description' => $langs->trans('ChecklistReproduceDesc'),
		'category'    => 'general',
	);
	$steps['general_firmware'] = array(
		'label'       => $langs->trans('ChecklistFirmware'),
		'description' => $langs->trans('ChecklistFirmwareDesc'),
		'category'    => 'software',
	);

	return $steps;
}
