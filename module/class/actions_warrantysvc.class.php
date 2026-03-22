<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    class/actions_warrantysvc.class.php
 * \ingroup warrantysvc
 * \brief   Hook actions for the Warranty & Service module
 */

/**
 * Class ActionsWarrantySvc
 *
 * Implements Dolibarr hooks so that svcwarranty and svcrequest element types
 * are recognised system-wide — appearing in the "Link to" dropdown on any
 * Dolibarr object card (shipments, orders, projects, etc.).
 */
class ActionsWarrantySvc
{
	/** @var DoliDB Database handler */
	public $db;

	/** @var string Error message */
	public $error = '';

	/** @var array Result set returned to hook manager */
	public $results = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Provide element properties for svcwarranty and svcrequest so Dolibarr
	 * can resolve them in linked-object lookups and the "Link to" dropdown.
	 *
	 * Called by HookManager on the 'elementproperties' context.
	 *
	 * @param  array      $parameters  Hook parameters, including $parameters['elementType']
	 * @param  object     &$object     Current page object (not used)
	 * @param  string     &$action     Current action (not used)
	 * @param  HookManager $hookmanager Hook manager instance
	 * @return int                     0 = merge results via array_replace
	 */
	public function getElementProperties($parameters, &$object, &$action, $hookmanager)
	{
		$elementType = isset($parameters['elementType']) ? $parameters['elementType'] : '';

		if ($elementType === 'svcwarranty') {
			$this->results = array(
				'module'        => 'warrantysvc',
				'element'       => 'svcwarranty',
				'table_element' => 'svc_warranty',
				'subelement'    => 'svcwarranty',
				'classpath'     => 'warrantysvc/class',
				'classfile'     => 'svcwarranty',
				'classname'     => 'SvcWarranty',
			);
		} elseif ($elementType === 'svcrequest') {
			$this->results = array(
				'module'        => 'warrantysvc',
				'element'       => 'svcrequest',
				'table_element' => 'svc_request',
				'subelement'    => 'svcrequest',
				'classpath'     => 'warrantysvc/class',
				'classfile'     => 'svcrequest',
				'classname'     => 'SvcRequest',
			);
		}

		return 0;
	}
}
