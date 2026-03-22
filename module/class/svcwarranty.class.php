<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    class/svcwarranty.class.php
 * \ingroup warrantysvc
 * \brief   Class for warranty records per serialized product
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class to manage warranty records
 */
class SvcWarranty extends CommonObject
{
	/** @var string Trigger prefix */
	public $TRIGGER_PREFIX = 'SVCWARRANTY';

	/** @var string Element name */
	public $element = 'svcwarranty';

	/** @var string Table name without prefix */
	public $table_element = 'svc_warranty';

	/** @var string Icon */
	public $picto = 'bill';

	/** @var string Ref field */
	protected $table_ref_field = 'ref';

	// Status constants
	const STATUS_ACTIVE   = 'active';
	const STATUS_EXPIRED  = 'expired';
	const STATUS_VOIDED   = 'voided';

	public $ref;
	public $entity;
	public $fk_product;
	public $serial_number;
	public $fk_soc;
	public $socid; // alias
	public $warranty_type;
	public $start_date;
	public $expiry_date;
	public $coverage_months;
	public $coverage_terms;
	public $exclusions;
	public $status = self::STATUS_ACTIVE;
	public $fk_contract;
	public $fk_commande;
	public $fk_expedition;
	public $claim_count       = 0;
	public $total_claimed_value = 0;
	public $date_creation;
	public $fk_user_creat;
	public $import_key;
	public $note_private;
	public $note_public;

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
	 * Create warranty in DB
	 *
	 * @param  User $user      User
	 * @param  int  $notrigger 0=triggers, 1=disable
	 * @return int             >0 if OK, <0 if KO
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$this->db->begin();

		if (empty($this->ref)) {
			$this->ref = $this->getNextNumRef();
		}

		$now = dol_now();
		$this->date_creation = $now;
		$this->fk_user_creat = $user->id;

		if (!empty($this->socid) && empty($this->fk_soc)) {
			$this->fk_soc = $this->socid;
		}

		// Auto-compute expiry from coverage_months if not set
		if (empty($this->expiry_date) && !empty($this->coverage_months) && !empty($this->start_date)) {
			$this->expiry_date = dol_time_plus_duree($this->start_date, $this->coverage_months, 'm');
		}

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."svc_warranty";
		$sql .= " (ref, entity, fk_product, serial_number, fk_soc, warranty_type,";
		$sql .= "  start_date, expiry_date, coverage_months, coverage_terms, exclusions,";
		$sql .= "  status, fk_contract, fk_commande, fk_expedition,";
		$sql .= "  date_creation, fk_user_creat, import_key, note_private, note_public)";
		$sql .= " VALUES (";
		$sql .= "'".$this->db->escape($this->ref)."'";
		$sql .= ", ".((int) $conf->entity);
		$sql .= ", ".((int) $this->fk_product);
		$sql .= ", '".$this->db->escape($this->serial_number)."'";
		$sql .= ", ".((int) $this->fk_soc);
		$sql .= ", ".($this->warranty_type ? "'".$this->db->escape($this->warranty_type)."'" : "NULL");
		$sql .= ", '".$this->db->idate($this->start_date)."'";
		$sql .= ", ".($this->expiry_date ? "'".$this->db->idate($this->expiry_date)."'" : "NULL");
		$sql .= ", ".($this->coverage_months > 0 ? ((int) $this->coverage_months) : "NULL");
		$sql .= ", ".($this->coverage_terms ? "'".$this->db->escape($this->coverage_terms)."'" : "NULL");
		$sql .= ", ".($this->exclusions ? "'".$this->db->escape($this->exclusions)."'" : "NULL");
		$sql .= ", '".$this->db->escape($this->status ? $this->status : self::STATUS_ACTIVE)."'";
		$sql .= ", ".($this->fk_contract > 0 ? ((int) $this->fk_contract) : "NULL");
		$sql .= ", ".($this->fk_commande > 0 ? ((int) $this->fk_commande) : "NULL");
		$sql .= ", ".($this->fk_expedition > 0 ? ((int) $this->fk_expedition) : "NULL");
		$sql .= ", '".$this->db->idate($this->date_creation)."'";
		$sql .= ", ".((int) $this->fk_user_creat);
		$sql .= ", ".($this->import_key ? "'".$this->db->escape($this->import_key)."'" : "NULL");
		$sql .= ", ".($this->note_private ? "'".$this->db->escape($this->note_private)."'" : "NULL");
		$sql .= ", ".($this->note_public ? "'".$this->db->escape($this->note_public)."'" : "NULL");
		$sql .= ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'svc_warranty');

		if (!$notrigger) {
			$this->call_trigger('SVCWARRANTY_CREATE', $user);
		}

		$this->db->commit();
		return $this->id;
	}

	/**
	 * Load warranty by ID or ref
	 *
	 * @param  int    $id  ID
	 * @param  string $ref Ref
	 * @return int         >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id, $ref = '')
	{
		global $conf;

		$sql = "SELECT rowid, ref, entity, fk_product, serial_number, fk_soc,";
		$sql .= " warranty_type, start_date, expiry_date, coverage_months,";
		$sql .= " coverage_terms, exclusions, status,";
		$sql .= " fk_contract, fk_commande, fk_expedition,";
		$sql .= " claim_count, total_claimed_value,";
		$sql .= " date_creation, tms, fk_user_creat,";
		$sql .= " import_key, note_private, note_public";
		$sql .= " FROM ".MAIN_DB_PREFIX."svc_warranty";
		if ($id) {
			$sql .= " WHERE rowid = ".((int) $id);
		} elseif ($ref) {
			$sql .= " WHERE ref = '".$this->db->escape($ref)."' AND entity = ".$conf->entity;
		} else {
			return -1;
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$this->id                  = $obj->rowid;
				$this->ref                 = $obj->ref;
				$this->entity              = $obj->entity;
				$this->fk_product          = $obj->fk_product;
				$this->serial_number       = $obj->serial_number;
				$this->fk_soc              = $obj->fk_soc;
				$this->socid               = $obj->fk_soc;
				$this->warranty_type       = $obj->warranty_type;
				$this->start_date          = $this->db->jdate($obj->start_date);
				$this->expiry_date         = $this->db->jdate($obj->expiry_date);
				$this->coverage_months     = $obj->coverage_months;
				$this->coverage_terms      = $obj->coverage_terms;
				$this->exclusions          = $obj->exclusions;
				$this->status              = $obj->status;
				$this->fk_contract         = $obj->fk_contract;
				$this->fk_commande         = $obj->fk_commande;
				$this->fk_expedition       = $obj->fk_expedition;
				$this->claim_count         = $obj->claim_count;
				$this->total_claimed_value = $obj->total_claimed_value;
				$this->date_creation       = $this->db->jdate($obj->date_creation);
				$this->fk_user_creat       = $obj->fk_user_creat;
				$this->import_key          = $obj->import_key;
				$this->note_private        = $obj->note_private;
				$this->note_public         = $obj->note_public;

				// Sync status based on expiry date
				$this->syncStatus();

				return 1;
			}
			return 0;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Fetch warranty by serial number
	 *
	 * @param  string $serial_number Serial number
	 * @return int                   >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetchBySerial($serial_number)
	{
		global $conf;

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."svc_warranty";
		$sql .= " WHERE serial_number = '".$this->db->escape($serial_number)."'";
		$sql .= " AND entity = ".$conf->entity;
		$sql .= " ORDER BY rowid DESC LIMIT 1";

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				return $this->fetch($obj->rowid);
			}
			return 0;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Update warranty in DB
	 *
	 * @param  User $user      User
	 * @param  int  $notrigger 0=triggers, 1=disable
	 * @return int             >0 if OK, <0 if KO
	 */
	public function update($user, $notrigger = 0)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."svc_warranty SET";
		$sql .= " fk_product = ".((int) $this->fk_product);
		$sql .= ", serial_number = '".$this->db->escape($this->serial_number)."'";
		$sql .= ", fk_soc = ".((int) $this->fk_soc);
		$sql .= ", warranty_type = ".($this->warranty_type ? "'".$this->db->escape($this->warranty_type)."'" : "NULL");
		$sql .= ", start_date = '".$this->db->idate($this->start_date)."'";
		$sql .= ", expiry_date = ".($this->expiry_date ? "'".$this->db->idate($this->expiry_date)."'" : "NULL");
		$sql .= ", coverage_months = ".($this->coverage_months > 0 ? ((int) $this->coverage_months) : "NULL");
		$sql .= ", coverage_terms = ".($this->coverage_terms ? "'".$this->db->escape($this->coverage_terms)."'" : "NULL");
		$sql .= ", exclusions = ".($this->exclusions ? "'".$this->db->escape($this->exclusions)."'" : "NULL");
		$sql .= ", status = '".$this->db->escape($this->status)."'";
		$sql .= ", fk_contract = ".($this->fk_contract > 0 ? ((int) $this->fk_contract) : "NULL");
		$sql .= ", fk_commande = ".($this->fk_commande > 0 ? ((int) $this->fk_commande) : "NULL");
		$sql .= ", fk_expedition = ".($this->fk_expedition > 0 ? ((int) $this->fk_expedition) : "NULL");
		$sql .= ", claim_count = ".((int) $this->claim_count);
		$sql .= ", total_claimed_value = ".((float) $this->total_claimed_value);
		$sql .= ", note_private = ".($this->note_private ? "'".$this->db->escape($this->note_private)."'" : "NULL");
		$sql .= ", note_public = ".($this->note_public ? "'".$this->db->escape($this->note_public)."'" : "NULL");
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			if (!$notrigger) {
				$this->call_trigger('SVCWARRANTY_MODIFY', $user);
			}
			return 1;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Delete warranty
	 *
	 * @param  User $user User
	 * @return int        >0 if OK, <0 if KO
	 */
	public function delete($user)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."svc_warranty WHERE rowid = ".((int) $this->id);
		if ($this->db->query($sql)) {
			return 1;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Sync status field based on expiry date (called after fetch)
	 *
	 * @return void
	 */
	private function syncStatus()
	{
		if ($this->status == self::STATUS_VOIDED) {
			return;
		}
		if (!empty($this->expiry_date) && $this->expiry_date < dol_now()) {
			$this->status = self::STATUS_EXPIRED;
		} else {
			$this->status = self::STATUS_ACTIVE;
		}
	}

	/**
	 * Get next ref
	 *
	 * @return string Next ref
	 */
	public function getNextNumRef()
	{
		global $conf;
		$year  = dol_print_date(dol_now(), '%Y');
		$month = dol_print_date(dol_now(), '%m');

		$sql = "SELECT MAX(CAST(SUBSTRING(ref, 15) AS SIGNED)) as max FROM ".MAIN_DB_PREFIX."svc_warranty";
		$sql .= " WHERE ref LIKE 'WTY-".$year.$month."-%' AND entity = ".$conf->entity;

		$max = 0;
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$max = intval($obj->max);
			}
		}

		return 'WTY-'.$year.$month.'-'.sprintf('%04d', $max + 1);
	}

	/**
	 * Return status label
	 *
	 * @param  string $status Status value
	 * @param  int    $mode   0=long, 1=short
	 * @return string         Label
	 */
	public function LibStatut($status = '', $mode = 0)
	{
		global $langs;
		$langs->load('warrantysvc');

		if (empty($status)) {
			$status = $this->status;
		}

		$labels = array(
			self::STATUS_ACTIVE  => array('label' => 'Active',  'class' => 'badge-status1'),
			self::STATUS_EXPIRED => array('label' => 'Expired', 'class' => 'badge-status8'),
			self::STATUS_VOIDED  => array('label' => 'Voided',  'class' => 'badge-status9'),
		);

		$s = isset($labels[$status]) ? $labels[$status] : array('label' => 'Unknown', 'class' => 'badge-status0');
		return '<span class="badge '.$s['class'].'">'.$langs->trans($s['label']).'</span>';
	}
}
