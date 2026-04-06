<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    class/svcservicelog.class.php
 * \ingroup warrantysvc
 * \brief   Tracks service/condition history per serialized unit
 *          Used for replacement selection scoring.
 */

/**
 * Class to manage service log entries per serial number.
 * Aggregates data from Interventions for replacement selection.
 */
class SvcServiceLog
{
	/** @var DoliDB Database handler */
	public $db;

	/** @var string Error */
	public $error    = '';

	/** @var array Errors */
	public $errors   = array();

	/** @var int ID */
	public $id;

	// Condition status constants — stored as SMALLINT in DB
	const CONDITION_GOOD     = 0;
	const CONDITION_FAIR     = 1;
	const CONDITION_POOR     = 2;
	const CONDITION_SCRAP    = 3;

	public $entity;
	public $fk_product;
	public $serial_number;
	public $service_hours    = 0;
	public $service_count    = 0;
	public $last_service_date;
	public $condition_score;
	public $condition_notes;
	public $condition_status = self::CONDITION_GOOD;
	public $date_last_updated;

	/** @var bool When true, save() will not overwrite condition_score with computeConditionScore() */
	public $skip_auto_score = false;

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
	 * Fetch service log by serial number (most recent record)
	 *
	 * @param  string $serial_number Serial number
	 * @param  int    $entity        Entity ID
	 * @return int                   >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetchBySerial($serial_number, $entity = 1)
	{
		$sql = "SELECT rowid, entity, fk_product, serial_number,";
		$sql .= " service_hours, service_count, last_service_date,";
		$sql .= " condition_score, condition_notes, condition_status, date_last_updated";
		$sql .= " FROM ".MAIN_DB_PREFIX."svc_service_log";
		$sql .= " WHERE serial_number = '".$this->db->escape($serial_number)."'";
		$sql .= " AND entity = ".((int) $entity);
		$sql .= " ORDER BY rowid DESC LIMIT 1";

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$this->id                = $obj->rowid;
				$this->entity            = $obj->entity;
				$this->fk_product        = $obj->fk_product;
				$this->serial_number     = $obj->serial_number;
				$this->service_hours     = $obj->service_hours;
				$this->service_count     = $obj->service_count;
				$this->last_service_date = $this->db->jdate($obj->last_service_date);
				$this->condition_score   = $obj->condition_score;
				$this->condition_notes   = $obj->condition_notes;
				$this->condition_status  = $obj->condition_status;
				$this->date_last_updated = $this->db->jdate($obj->date_last_updated);
				return 1;
			}
			return 0;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Create or update service log for a serial number.
	 * Called after an Intervention is completed on a unit.
	 *
	 * @param  User $user User performing the action
	 * @return int        >0 if OK, <0 if KO
	 */
	public function save($user)
	{
		global $conf;

		$this->date_last_updated = dol_now();
		if (!$this->skip_auto_score) {
			$this->condition_score = $this->computeConditionScore();
		}

		// Upsert: update if exists, insert if not
		$existing = $this->fetchBySerial($this->serial_number, $conf->entity);

		if ($existing > 0) {
			// Update
			$sql = "UPDATE ".MAIN_DB_PREFIX."svc_service_log SET";
			$sql .= " service_hours = ".((float) $this->service_hours);
			$sql .= ", service_count = ".((int) $this->service_count);
			$sql .= ", last_service_date = ".($this->last_service_date ? "'".$this->db->idate($this->last_service_date)."'" : "NULL");
			$sql .= ", condition_score = ".((int) $this->condition_score);
			$sql .= ", condition_notes = ".($this->condition_notes ? "'".$this->db->escape($this->condition_notes)."'" : "NULL");
			$sql .= ", condition_status = ".((int) $this->condition_status);
			$sql .= ", date_last_updated = '".$this->db->idate($this->date_last_updated)."'";
			$sql .= " WHERE rowid = ".((int) $this->id);
		} else {
			// Insert
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."svc_service_log";
			$sql .= " (entity, fk_product, serial_number, service_hours, service_count,";
			$sql .= "  last_service_date, condition_score, condition_notes, condition_status, date_last_updated)";
			$sql .= " VALUES (";
			$sql .= ((int) $conf->entity);
			$sql .= ", ".((int) $this->fk_product);
			$sql .= ", '".$this->db->escape($this->serial_number)."'";
			$sql .= ", ".((float) $this->service_hours);
			$sql .= ", ".((int) $this->service_count);
			$sql .= ", ".($this->last_service_date ? "'".$this->db->idate($this->last_service_date)."'" : "NULL");
			$sql .= ", ".((int) $this->condition_score);
			$sql .= ", ".($this->condition_notes ? "'".$this->db->escape($this->condition_notes)."'" : "NULL");
			$sql .= ", ".((int) $this->condition_status);
			$sql .= ", '".$this->db->idate($this->date_last_updated)."'";
			$sql .= ")";
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($existing <= 0) {
				$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'svc_service_log');
			}
			return 1;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Compute condition score: lower = better condition.
	 * Score factors: service_count * 10 + service_hours / 100
	 *
	 * @return int Score
	 */
	public function computeConditionScore()
	{
		return (int) (($this->service_count * 10) + ($this->service_hours / 100));
	}

	/**
	 * Get available serials for a product from refurbished warehouse,
	 * sorted by the requested strategy.
	 *
	 * @param  DoliDB $db         Database handler
	 * @param  int    $product_id Product ID
	 * @param  int    $warehouse_id Warehouse to search (refurb stock)
	 * @param  string $strategy   'fifo'|'least_serviced'|'best_condition'
	 * @return array              Array of ['serial', 'condition_score', 'service_count']
	 */
	public static function getAvailableSerials($db, $product_id, $warehouse_id, $strategy = 'fifo')
	{
		// Query native Dolibarr product_lot + stock tables
		$sql = "SELECT pl.batch as serial, pl.rowid as lot_id,";
		$sql .= " COALESCE(sl.condition_score, 0) as condition_score,";
		$sql .= " COALESCE(sl.service_count, 0) as service_count,";
		$sql .= " COALESCE(sl.service_hours, 0) as service_hours,";
		$sql .= " ps.reel as qty_available";
		$sql .= " FROM ".MAIN_DB_PREFIX."product_lot pl";
		$sql .= " JOIN ".MAIN_DB_PREFIX."product_stock ps ON ps.fk_product = pl.fk_product AND ps.fk_entrepot = ".((int) $warehouse_id);
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."svc_service_log sl ON sl.serial_number = pl.batch";
		$sql .= " WHERE pl.fk_product = ".((int) $product_id);
		$sql .= " AND ps.reel > 0";

		switch ($strategy) {
			case 'least_serviced':
				$sql .= " ORDER BY service_count ASC, pl.rowid ASC";
				break;
			case 'best_condition':
				$sql .= " ORDER BY condition_score ASC, pl.rowid ASC";
				break;
			case 'fifo':
			default:
				$sql .= " ORDER BY pl.rowid ASC";
				break;
		}

		$serials = array();
		$resql   = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$serials[] = array(
					'serial'          => $obj->serial,
					'lot_id'          => $obj->lot_id,
					'condition_score' => $obj->condition_score,
					'service_count'   => $obj->service_count,
					'service_hours'   => $obj->service_hours,
					'qty_available'   => $obj->qty_available,
				);
			}
		}

		return $serials;
	}
}
