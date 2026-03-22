<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    class/svcwarrantytype.class.php
 * \ingroup warrantysvc
 * \brief   Class for user-defined warranty types
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class to manage warranty type definitions
 */
class SvcWarrantyType extends CommonObject
{
	/** @var string Trigger prefix */
	public $TRIGGER_PREFIX = 'SVCWARRANTYTYPE';

	/** @var string Element name */
	public $element = 'svcwarrantytype';

	/** @var string Table name without prefix */
	public $table_element = 'svc_warranty_type';

	/** @var string Icon */
	public $picto = 'bill';

	/** @var string Ref field */
	protected $table_ref_field = 'code';

	public $code;
	public $label;
	public $description;
	public $default_coverage_days = 365;
	public $active                  = 1;
	public $position                = 0;
	public $entity;
	public $import_key;

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
	 * Create warranty type in DB
	 *
	 * @param  User $user      User object
	 * @param  int  $notrigger 0=fire triggers, 1=disable
	 * @return int             rowid if OK, <0 if KO
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$this->db->begin();

		$sql  = "INSERT INTO ".MAIN_DB_PREFIX."svc_warranty_type";
		$sql .= " (entity, code, label, description, default_coverage_days, active, position, import_key)";
		$sql .= " VALUES (";
		$sql .= ((int) $conf->entity).",";
		$sql .= "'".$this->db->escape($this->code)."',";
		$sql .= "'".$this->db->escape($this->label)."',";
		$sql .= ($this->description ? "'".$this->db->escape($this->description)."'" : "NULL").",";
		$sql .= ((int) ($this->default_coverage_days ? $this->default_coverage_days : 365)).",";
		$sql .= ((int) $this->active).",";
		$sql .= ((int) $this->position).",";
		$sql .= "NULL";
		$sql .= ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'svc_warranty_type');
		$this->db->commit();
		return $this->id;
	}

	/**
	 * Load warranty type from DB
	 *
	 * @param  int    $id   rowid
	 * @param  string $code code (alternative to id)
	 * @return int          >0 if OK, <0 if KO, 0 if not found
	 */
	public function fetch($id, $code = '')
	{
		$sql  = "SELECT rowid, entity, code, label, description, default_coverage_days, active, position";
		$sql .= " FROM ".MAIN_DB_PREFIX."svc_warranty_type";
		if ($id > 0) {
			$sql .= " WHERE rowid = ".((int) $id);
		} elseif ($code) {
			$sql .= " WHERE code = '".$this->db->escape($code)."'";
			$sql .= " AND entity IN (".getEntity('svcwarrantytype').")";
		} else {
			return -1;
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		if (!$obj) {
			return 0;
		}

		$this->id                      = $obj->rowid;
		$this->entity                  = $obj->entity;
		$this->code                    = $obj->code;
		$this->label                   = $obj->label;
		$this->description             = $obj->description;
		$this->default_coverage_days = $obj->default_coverage_days;
		$this->active                  = $obj->active;
		$this->position                = $obj->position;

		return 1;
	}

	/**
	 * Update warranty type in DB
	 *
	 * @param  User $user      User object
	 * @param  int  $notrigger 0=fire triggers, 1=disable
	 * @return int             >0 if OK, <0 if KO
	 */
	public function update($user, $notrigger = 0)
	{
		$this->db->begin();

		$sql  = "UPDATE ".MAIN_DB_PREFIX."svc_warranty_type SET";
		$sql .= " code = '".$this->db->escape($this->code)."',";
		$sql .= " label = '".$this->db->escape($this->label)."',";
		$sql .= " description = ".($this->description ? "'".$this->db->escape($this->description)."'" : "NULL").",";
		$sql .= " default_coverage_days = ".((int) ($this->default_coverage_days ? $this->default_coverage_days : 365)).",";
		$sql .= " active = ".((int) $this->active).",";
		$sql .= " position = ".((int) $this->position);
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Delete warranty type from DB
	 *
	 * @param  User $user      User object
	 * @param  int  $notrigger 0=fire triggers, 1=disable
	 * @return int             >0 if OK, <0 if KO
	 */
	public function delete($user, $notrigger = 0)
	{
		$this->db->begin();

		$sql  = "DELETE FROM ".MAIN_DB_PREFIX."svc_warranty_type";
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Return all active warranty types as code => label array for use in dropdowns.
	 * Includes an empty first entry.
	 *
	 * @param  DoliDB $db Database handler
	 * @return array      array('code' => 'Label', ...)
	 */
	public static function fetchAllActive($db)
	{
		$out = array('' => '');
		foreach (self::fetchAllForForm($db) as $t) {
			$out[$t->code] = $t->label;
		}
		return $out;
	}

	/**
	 * Return all active warranty types as an array of objects with all form-relevant fields.
	 * Used to build dropdowns and JS defaults simultaneously.
	 *
	 * @param  DoliDB $db Database handler
	 * @return array      Array of stdClass with code, label, default_coverage_days
	 */
	public static function fetchAllForForm($db)
	{
		$out = array();

		$sql  = "SELECT code, label, default_coverage_days FROM ".MAIN_DB_PREFIX."svc_warranty_type";
		$sql .= " WHERE active = 1";
		$sql .= " AND entity IN (".getEntity('svcwarrantytype').")";
		$sql .= " ORDER BY position ASC, label ASC";

		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$out[] = $obj;
			}
		}

		return $out;
	}

	/**
	 * Return all warranty types (active and inactive) as an array of objects.
	 * Used by the management page.
	 *
	 * @return array  Array of stdClass with rowid, code, label, description, default_coverage_days, active, position
	 */
	public function fetchAll()
	{
		$out = array();

		$sql  = "SELECT rowid, code, label, description, default_coverage_days, active, position";
		$sql .= " FROM ".MAIN_DB_PREFIX."svc_warranty_type";
		$sql .= " WHERE entity IN (".getEntity('svcwarrantytype').")";
		$sql .= " ORDER BY position ASC, label ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return array();
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$out[] = $obj;
		}

		return $out;
	}

	/**
	 * Return label of warranty type by code
	 *
	 * @param  DoliDB $db   Database handler
	 * @param  string $code Type code
	 * @return string       Label, or empty string if not found
	 */
	public static function getLabelByCode($db, $code)
	{
		if (empty($code)) {
			return '';
		}

		$sql  = "SELECT label FROM ".MAIN_DB_PREFIX."svc_warranty_type";
		$sql .= " WHERE code = '".$db->escape($code)."'";
		$sql .= " AND entity IN (".getEntity('svcwarrantytype').")";

		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				return $obj->label;
			}
		}
		return dol_escape_htmltag($code);
	}
}
