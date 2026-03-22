<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    class/svcrequestline.class.php
 * \ingroup warrantysvc
 * \brief   Class for RMA case lines (components shipped/returned)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';

/**
 * Class for lines of an RMA case
 */
class SvcRequestLine extends CommonObjectLine
{
	/** @var string Element name */
	public $element = 'svc_request_line';

	/** @var string Table name without prefix */
	public $table_element = 'svc_request_line';

	/** @var string FK field pointing to parent */
	public $fk_element = 'fk_svc_request';

	// Line type constants
	const TYPE_COMPONENT_OUT  = 'component_out';   // Part shipped to customer
	const TYPE_COMPONENT_IN   = 'component_in';    // Part returned from customer
	const TYPE_CONSUMED_SITE  = 'consumed_onsite'; // Part used during on-site visit

	public $fk_svc_request;
	public $fk_product;
	public $product_type = 0;
	public $description;
	public $qty          = 1;
	public $line_type;
	public $fk_expedition_line;
	public $fk_reception_line;
	public $subprice;
	public $total_ht;
	public $tva_tx       = 0;
	public $shipped      = 0;
	public $received     = 0;
	public $rang         = 0;
	public $note;

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
	 * Fetch line from DB
	 *
	 * @param  int $rowid Line ID
	 * @return int        >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($rowid)
	{
		$sql = "SELECT rowid, fk_svc_request, fk_product, product_type, description, qty,";
		$sql .= " line_type, fk_expedition_line, fk_reception_line,";
		$sql .= " subprice, total_ht, tva_tx, shipped, received, rang, note";
		$sql .= " FROM ".MAIN_DB_PREFIX."svc_request_line";
		$sql .= " WHERE rowid = ".((int) $rowid);

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$this->id                 = $obj->rowid;
				$this->fk_svc_request        = $obj->fk_svc_request;
				$this->fk_product         = $obj->fk_product;
				$this->product_type       = $obj->product_type;
				$this->description        = $obj->description;
				$this->qty                = $obj->qty;
				$this->line_type          = $obj->line_type;
				$this->fk_expedition_line = $obj->fk_expedition_line;
				$this->fk_reception_line  = $obj->fk_reception_line;
				$this->subprice           = $obj->subprice;
				$this->total_ht           = $obj->total_ht;
				$this->tva_tx             = $obj->tva_tx;
				$this->shipped            = $obj->shipped;
				$this->received           = $obj->received;
				$this->rang               = $obj->rang;
				$this->note               = $obj->note;
				return 1;
			}
			return 0;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Insert line into DB
	 *
	 * @param  User $user      User
	 * @param  int  $notrigger 0=triggers, 1=disable
	 * @return int             rowid if OK, <0 if KO
	 */
	public function insert($user, $notrigger = 0)
	{
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."svc_request_line";
		$sql .= " (fk_svc_request, fk_product, product_type, description, qty,";
		$sql .= "  line_type, subprice, total_ht, tva_tx, shipped, received, rang, note)";
		$sql .= " VALUES (";
		$sql .= ((int) $this->fk_svc_request);
		$sql .= ", ".((int) $this->fk_product);
		$sql .= ", ".((int) $this->product_type);
		$sql .= ", ".($this->description ? "'".$this->db->escape($this->description)."'" : "NULL");
		$sql .= ", ".((float) $this->qty);
		$sql .= ", ".($this->line_type ? "'".$this->db->escape($this->line_type)."'" : "NULL");
		$sql .= ", ".($this->subprice !== null ? ((float) $this->subprice) : "NULL");
		$sql .= ", ".($this->total_ht !== null ? ((float) $this->total_ht) : "NULL");
		$sql .= ", ".((float) $this->tva_tx);
		$sql .= ", ".((int) $this->shipped);
		$sql .= ", ".((int) $this->received);
		$sql .= ", ".((int) $this->rang);
		$sql .= ", ".($this->note ? "'".$this->db->escape($this->note)."'" : "NULL");
		$sql .= ")";

		$resql = $this->db->query($sql);
		if ($resql) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'svc_request_line');
			return $this->id;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Update line in DB
	 *
	 * @param  User $user      User
	 * @param  int  $notrigger 0=triggers, 1=disable
	 * @return int             >0 if OK, <0 if KO
	 */
	public function update($user, $notrigger = 0)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."svc_request_line SET";
		$sql .= " fk_product = ".((int) $this->fk_product);
		$sql .= ", product_type = ".((int) $this->product_type);
		$sql .= ", description = ".($this->description ? "'".$this->db->escape($this->description)."'" : "NULL");
		$sql .= ", qty = ".((float) $this->qty);
		$sql .= ", line_type = ".($this->line_type ? "'".$this->db->escape($this->line_type)."'" : "NULL");
		$sql .= ", fk_expedition_line = ".($this->fk_expedition_line > 0 ? ((int) $this->fk_expedition_line) : "NULL");
		$sql .= ", fk_reception_line = ".($this->fk_reception_line > 0 ? ((int) $this->fk_reception_line) : "NULL");
		$sql .= ", subprice = ".($this->subprice !== null ? ((float) $this->subprice) : "NULL");
		$sql .= ", total_ht = ".($this->total_ht !== null ? ((float) $this->total_ht) : "NULL");
		$sql .= ", tva_tx = ".((float) $this->tva_tx);
		$sql .= ", shipped = ".((int) $this->shipped);
		$sql .= ", received = ".((int) $this->received);
		$sql .= ", rang = ".((int) $this->rang);
		$sql .= ", note = ".($this->note ? "'".$this->db->escape($this->note)."'" : "NULL");
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql) {
			return 1;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Delete line from DB
	 *
	 * @param  User $user      User
	 * @param  int  $notrigger 0=triggers, 1=disable
	 * @return int             >0 if OK, <0 if KO
	 */
	public function delete($user, $notrigger = 0)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."svc_request_line WHERE rowid = ".((int) $this->id);
		if ($this->db->query($sql)) {
			return 1;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}
}
