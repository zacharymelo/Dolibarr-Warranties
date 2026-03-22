<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    api/api_warrantysvc.class.php
 * \ingroup warrantysvc
 * \brief   REST API endpoints for Warranty & Service module
 *
 * Exposes SvcRequest and SvcWarranty resources via Dolibarr's Luracast
 * REST API infrastructure. Accessible at:
 *   GET  /api/index.php/warrantysvc/requests
 *   GET  /api/index.php/warrantysvc/requests/{id}
 *   POST /api/index.php/warrantysvc/requests
 *   PUT  /api/index.php/warrantysvc/requests/{id}
 *   POST /api/index.php/warrantysvc/requests/{id}/createfromcall
 *   GET  /api/index.php/warrantysvc/warranties
 *   GET  /api/index.php/warrantysvc/warranties/{id}
 *   GET  /api/index.php/warrantysvc/warranties/byserial/{serial}
 */

require_once DOL_DOCUMENT_ROOT.'/api/class/dolgenerics.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcrequest.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarranty.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/lib/warrantysvc.lib.php';


/**
 * API for Warranty & Service module
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class WarrantySvc extends DolibarrApi
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	// ====================================================================
	// SERVICE REQUESTS
	// ====================================================================

	/**
	 * List service requests
	 *
	 * @param string $sortfield  Sort field (default: t.rowid)
	 * @param string $sortorder  Sort order (default: ASC)
	 * @param int    $limit      Page size (default: 100)
	 * @param int    $page       Page number (default: 0)
	 * @param string $sqlfilters Extra SQL WHERE clauses e.g. "(t.status:=:1)"
	 *
	 * @url GET /requests
	 * @return array Array of SvcRequest objects
	 * @throws RestException
	 */
	public function indexRequests($sortfield = 't.rowid', $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '')
	{
		if (!DolibarrApiAccess::$user->hasRight('warrantysvc', 'svcrequest', 'read')) {
			throw new RestException(403);
		}

		$obj_ret = array();
		$offset  = $limit * $page;

		$sql  = "SELECT t.rowid FROM ".MAIN_DB_PREFIX."svc_request as t";
		$sql .= " WHERE t.entity IN (".getEntity('svcrequest').")";

		if ($sqlfilters) {
			$regexstring = '\(([^:\'\(\)]+:[^:\'\(\)]+:[^:\'\(\)]+)\)';
			$sql .= " AND (".DolibarrApi::_checkFilters($sqlfilters, $regexstring).")";
		}

		$sql .= $this->db->order($sortfield, $sortorder);
		$sql .= $this->db->plimit($limit, $offset);

		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, $this->db->lasterror());
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$request = new SvcRequest($this->db);
			if ($request->fetch($obj->rowid) > 0) {
				$obj_ret[] = $this->_cleanObjectDatas($request);
			}
		}
		$this->db->free($resql);

		return $obj_ret;
	}

	/**
	 * Get a service request by ID
	 *
	 * @param int $id Service request ID
	 *
	 * @url GET /requests/{id}
	 * @return array SvcRequest object
	 * @throws RestException
	 */
	public function getRequest($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('warrantysvc', 'svcrequest', 'read')) {
			throw new RestException(403);
		}

		$request = new SvcRequest($this->db);
		$result  = $request->fetch((int) $id);

		if ($result == 0) {
			throw new RestException(404, 'Service request not found');
		}
		if ($result < 0) {
			throw new RestException(500, $request->error);
		}

		return $this->_cleanObjectDatas($request);
	}

	/**
	 * Create a service request
	 *
	 * @param array $request Request body (SvcRequest fields)
	 *
	 * @url POST /requests
	 * @return int New service request ID
	 * @throws RestException
	 */
	public function postRequest($request)
	{
		if (!DolibarrApiAccess::$user->hasRight('warrantysvc', 'svcrequest', 'write')) {
			throw new RestException(403);
		}

		$obj = new SvcRequest($this->db);
		foreach ($request as $field => $value) {
			if ($field == 'rowid') {
				continue;
			}
			$obj->$field = $value;
		}

		$result = $obj->create(DolibarrApiAccess::$user);
		if ($result < 0) {
			throw new RestException(500, $obj->error);
		}

		return $result;
	}

	/**
	 * Update a service request
	 *
	 * @param int   $id      Service request ID
	 * @param array $request Request body (SvcRequest fields to update)
	 *
	 * @url PUT /requests/{id}
	 * @return int 1 if OK
	 * @throws RestException
	 */
	public function putRequest($id, $request)
	{
		if (!DolibarrApiAccess::$user->hasRight('warrantysvc', 'svcrequest', 'write')) {
			throw new RestException(403);
		}

		$obj    = new SvcRequest($this->db);
		$result = $obj->fetch((int) $id);
		if ($result == 0) {
			throw new RestException(404, 'Service request not found');
		}
		if ($result < 0) {
			throw new RestException(500, $obj->error);
		}

		foreach ($request as $field => $value) {
			if (in_array($field, array('rowid', 'entity', 'ref', 'date_creation'))) {
				continue;
			}
			$obj->$field = $value;
		}

		$result = $obj->update(DolibarrApiAccess::$user);
		if ($result < 0) {
			throw new RestException(500, $obj->error);
		}

		return 1;
	}

	/**
	 * Create a service request from a CRM phone call (actioncomm)
	 *
	 * Pre-fills the new service request from the actioncomm record fields
	 * (thirdparty, contact, description, date) and links them together.
	 *
	 * @param int   $id      Actioncomm ID of the call
	 * @param array $request Optional extra fields to override (serial_number, fk_product, etc.)
	 *
	 * @url POST /requests/createfromcall/{id}
	 * @return int New service request ID
	 * @throws RestException
	 */
	public function postRequestFromCall($id, $request = array())
	{
		if (!DolibarrApiAccess::$user->hasRight('warrantysvc', 'svcrequest', 'write')) {
			throw new RestException(403);
		}

		$obj = new SvcRequest($this->db);

		// Apply any extra fields passed in the body before createFromCall
		if (!empty($request)) {
			foreach ($request as $field => $value) {
				if (in_array($field, array('rowid', 'entity', 'ref', 'date_creation'))) {
					continue;
				}
				$obj->$field = $value;
			}
		}

		$result = $obj->createFromCall((int) $id, DolibarrApiAccess::$user);
		if ($result < 0) {
			throw new RestException(500, $obj->error);
		}

		return $result;
	}

	// ====================================================================
	// WARRANTIES
	// ====================================================================

	/**
	 * List warranties
	 *
	 * @param string $sortfield  Sort field (default: t.rowid)
	 * @param string $sortorder  Sort order (default: ASC)
	 * @param int    $limit      Page size (default: 100)
	 * @param int    $page       Page number (default: 0)
	 * @param string $sqlfilters Extra SQL WHERE clauses
	 *
	 * @url GET /warranties
	 * @return array Array of SvcWarranty objects
	 * @throws RestException
	 */
	public function indexWarranties($sortfield = 't.rowid', $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '')
	{
		if (!DolibarrApiAccess::$user->hasRight('warrantysvc', 'svcwarranty', 'read')) {
			throw new RestException(403);
		}

		$obj_ret = array();
		$offset  = $limit * $page;

		$sql  = "SELECT t.rowid FROM ".MAIN_DB_PREFIX."svc_warranty as t";
		$sql .= " WHERE t.entity IN (".getEntity('svcwarranty').")";

		if ($sqlfilters) {
			$regexstring = '\(([^:\'\(\)]+:[^:\'\(\)]+:[^:\'\(\)]+)\)';
			$sql .= " AND (".DolibarrApi::_checkFilters($sqlfilters, $regexstring).")";
		}

		$sql .= $this->db->order($sortfield, $sortorder);
		$sql .= $this->db->plimit($limit, $offset);

		$resql = $this->db->query($sql);
		if (!$resql) {
			throw new RestException(500, $this->db->lasterror());
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$warranty = new SvcWarranty($this->db);
			if ($warranty->fetch($obj->rowid) > 0) {
				$obj_ret[] = $this->_cleanObjectDatas($warranty);
			}
		}
		$this->db->free($resql);

		return $obj_ret;
	}

	/**
	 * Get a warranty by ID
	 *
	 * @param int $id Warranty ID
	 *
	 * @url GET /warranties/{id}
	 * @return array SvcWarranty object
	 * @throws RestException
	 */
	public function getWarranty($id)
	{
		if (!DolibarrApiAccess::$user->hasRight('warrantysvc', 'svcwarranty', 'read')) {
			throw new RestException(403);
		}

		$warranty = new SvcWarranty($this->db);
		$result   = $warranty->fetch((int) $id);

		if ($result == 0) {
			throw new RestException(404, 'Warranty not found');
		}
		if ($result < 0) {
			throw new RestException(500, $warranty->error);
		}

		return $this->_cleanObjectDatas($warranty);
	}

	/**
	 * Get a warranty by serial number
	 *
	 * @param string $serial Serial number
	 *
	 * @url GET /warranties/byserial/{serial}
	 * @return array SvcWarranty object
	 * @throws RestException
	 */
	public function getWarrantyBySerial($serial)
	{
		if (!DolibarrApiAccess::$user->hasRight('warrantysvc', 'svcwarranty', 'read')) {
			throw new RestException(403);
		}

		$warranty = new SvcWarranty($this->db);
		$result   = $warranty->fetchBySerial($serial);

		if ($result == 0) {
			throw new RestException(404, 'No warranty found for serial: '.$serial);
		}
		if ($result < 0) {
			throw new RestException(500, $warranty->error);
		}

		return $this->_cleanObjectDatas($warranty);
	}

	// ====================================================================
	// Internal helpers
	// ====================================================================

	/**
	 * Strip properties not suitable for API output
	 *
	 * @param  CommonObject $object Object to clean
	 * @return array                Cleaned associative array
	 */
	protected function _cleanObjectDatas($object)
	{
		$object = parent::_cleanObjectDatas($object);
		// Remove large/internal fields not relevant to API consumers
		unset($object->linkedObjectsIds, $object->context, $object->canvas, $object->fk_project);
		return $object;
	}
}
