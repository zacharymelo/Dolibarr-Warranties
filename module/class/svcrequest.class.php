<?php
/* Copyright (C) 2026 DPG Supply
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/svcrequest.class.php
 * \ingroup warrantysvc
 * \brief   Class for RMA case management
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class to manage RMA cases
 */
class SvcRequest extends CommonObject
{
	/** @var string Trigger prefix */
	public $TRIGGER_PREFIX = 'WARRANTYSVC';

	/** @var string Module name (used by getElementType() for prefixing) */
	public $module = 'warrantysvc';

	/** @var string Element name */
	public $element = 'svcrequest';

	/** @var string Table name (without prefix) */
	public $table_element = 'svc_request';

	/** @var string Table line name */
	public $table_element_line = 'svc_request_line';

	/** @var string Class name for lines */
	public $class_element_line = 'SvcRequestLine';

	/** @var string Icon */
	public $picto = 'technic';

	/** @var string Ref field name in table */
	protected $table_ref_field = 'ref';

	// Status constants
	const STATUS_DRAFT         = 0;
	const STATUS_VALIDATED     = 1;
	const STATUS_IN_PROGRESS   = 2;
	const STATUS_AWAIT_RETURN  = 3;
	const STATUS_RESOLVED      = 4;
	const STATUS_CLOSED        = 5;
	const STATUS_CANCELLED     = 9;

	// Resolution type constants
	const RESOLUTION_COMPONENT         = 'component';
	const RESOLUTION_COMPONENT_RETURN  = 'component_return';
	const RESOLUTION_SWAP_CROSS        = 'swap_cross';
	const RESOLUTION_SWAP_WAIT         = 'swap_wait';
	const RESOLUTION_INTERVENTION      = 'intervention';
	const RESOLUTION_GUIDANCE          = 'guidance';

	/** @var array Field definitions for ORM */
	public $fields = array(
		'rowid'                => array('type' => 'integer',      'label' => 'TechnicalID',      'enabled' => 1, 'visible' => -1, 'notnull' => 1, 'position' => 10),
		'ref'                  => array('type' => 'varchar(30)',   'label' => 'Ref',              'enabled' => 1, 'visible' => 1,  'notnull' => 1, 'showoncombobox' => 1, 'position' => 20),
		'entity'               => array('type' => 'integer',      'label' => 'Entity',           'enabled' => 1, 'visible' => -2, 'notnull' => 1, 'position' => 25),
		'fk_soc'               => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 30),
		'fk_product'           => array('type' => 'integer:Product:product/class/product.class.php', 'label' => 'Product',    'enabled' => 1, 'visible' => 1, 'notnull' => 1, 'position' => 35),
		'serial_number'        => array('type' => 'varchar(128)', 'label' => 'SerialNumber',     'enabled' => 1, 'visible' => 1,  'position' => 40),
		'fk_contact'           => array('type' => 'integer:Contact:contact/class/contact.class.php', 'label' => 'Contact', 'enabled' => 1, 'visible' => -1, 'position' => 45),
		'customer_site'        => array('type' => 'varchar(255)', 'label' => 'CustomerSite',     'enabled' => 1, 'visible' => -1, 'position' => 50),
		'fk_project'           => array('type' => 'integer:Project:projet/class/project.class.php:1:(fk_statut:=:1)', 'label' => 'Project', 'enabled' => 'isModEnabled("project")', 'visible' => -1, 'position' => 55),
		'fk_commande'          => array('type' => 'integer:Commande:commande/class/commande.class.php', 'label' => 'Order', 'enabled' => 'isModEnabled("order")', 'visible' => -1, 'position' => 60),
		'fk_expedition_origin' => array('type' => 'integer',      'label' => 'OriginShipment',   'enabled' => 1, 'visible' => -1, 'position' => 65),
		'fk_lot'               => array('type' => 'integer',      'label' => 'Lot',              'enabled' => 1, 'visible' => -1, 'position' => 70),
		'issue_description'    => array('type' => 'text',         'label' => 'IssueDescription', 'enabled' => 1, 'visible' => 1,  'position' => 80),
		'issue_date'           => array('type' => 'datetime',     'label' => 'IssueDate',        'enabled' => 1, 'visible' => 1,  'position' => 85),
		'reported_via'         => array('type' => 'varchar(50)',  'label' => 'ReportedVia',      'enabled' => 1, 'visible' => -1, 'position' => 90),
		'fk_pbxcall'           => array('type' => 'integer',      'label' => 'PBXCall',          'enabled' => 1, 'visible' => -1, 'position' => 95),
		'resolution_type'      => array('type' => 'varchar(50)',  'label' => 'ResolutionType',   'enabled' => 1, 'visible' => 1,  'position' => 100),
		'resolution_notes'     => array('type' => 'text',         'label' => 'ResolutionNotes',  'enabled' => 1, 'visible' => -1, 'position' => 105),
		'fk_warranty'          => array('type' => 'integer',      'label' => 'Warranty',         'enabled' => 1, 'visible' => -1, 'position' => 110),
		'warranty_status'      => array('type' => 'varchar(20)',  'label' => 'WarrantyStatus',   'enabled' => 1, 'visible' => 1,  'position' => 115),
		'billable'             => array('type' => 'integer',      'label' => 'Billable',         'enabled' => 1, 'visible' => 1,  'position' => 120),
		'fk_facture'           => array('type' => 'integer',      'label' => 'Invoice',          'enabled' => 1, 'visible' => -1, 'position' => 125),
		'serial_in'            => array('type' => 'varchar(128)', 'label' => 'SerialIn',         'enabled' => 1, 'visible' => 1,  'position' => 130),
		'serial_out'           => array('type' => 'varchar(128)', 'label' => 'SerialOut',        'enabled' => 1, 'visible' => 1,  'position' => 135),
		'fk_warehouse_source'  => array('type' => 'integer',      'label' => 'SvcWarehouseSource',  'enabled' => 1, 'visible' => -1, 'position' => 140),
		'fk_warehouse_return'  => array('type' => 'integer',      'label' => 'SvcWarehouseReturn',  'enabled' => 1, 'visible' => -1, 'position' => 145),
		'outbound_carrier'     => array('type' => 'varchar(100)', 'label' => 'OutboundCarrier',  'enabled' => 1, 'visible' => -1, 'position' => 150),
		'outbound_tracking'    => array('type' => 'varchar(100)', 'label' => 'OutboundTracking', 'enabled' => 1, 'visible' => -1, 'position' => 155),
		'date_shipped'         => array('type' => 'datetime',     'label' => 'DateShipped',      'enabled' => 1, 'visible' => -1, 'position' => 160),
		'fk_shipment'          => array('type' => 'integer',      'label' => 'Shipment',         'enabled' => 1, 'visible' => -1, 'position' => 165),
		'return_carrier'       => array('type' => 'varchar(100)', 'label' => 'ReturnCarrier',    'enabled' => 1, 'visible' => -1, 'position' => 170),
		'return_tracking'      => array('type' => 'varchar(100)', 'label' => 'ReturnTracking',   'enabled' => 1, 'visible' => -1, 'position' => 175),
		'date_return_expected' => array('type' => 'date',         'label' => 'DateReturnExpected', 'enabled' => 1, 'visible' => -1, 'position' => 180),
		'date_return_received' => array('type' => 'datetime',     'label' => 'DateReturnReceived', 'enabled' => 1, 'visible' => -1, 'position' => 185),
		'return_reminder_count'=> array('type' => 'integer',      'label' => 'ReturnReminderCount','enabled' => 1,'visible' => -1, 'position' => 190),
		'fk_reception'         => array('type' => 'integer',      'label' => 'Reception',        'enabled' => 1, 'visible' => -1, 'position' => 195),
		'fk_intervention'      => array('type' => 'integer',      'label' => 'Intervention',     'enabled' => 1, 'visible' => -1, 'position' => 200),
		'fk_user_assigned'     => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AssignedTo', 'enabled' => 1, 'visible' => 1, 'position' => 205),
		'status'               => array('type' => 'integer',      'label' => 'Status',           'enabled' => 1, 'visible' => 1,  'notnull' => 1, 'position' => 500),
		'date_creation'        => array('type' => 'datetime',     'label' => 'DateCreation',     'enabled' => 1, 'visible' => -1, 'position' => 510),
		'date_validation'      => array('type' => 'datetime',     'label' => 'DateValidation',   'enabled' => 1, 'visible' => -1, 'position' => 515),
		'date_closed'          => array('type' => 'datetime',     'label' => 'DateClosed',       'enabled' => 1, 'visible' => -1, 'position' => 520),
		'tms'                  => array('type' => 'timestamp',    'label' => 'DateModification', 'enabled' => 1, 'visible' => -1, 'position' => 530),
		'fk_user_creat'        => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserCreation', 'enabled' => 1, 'visible' => -2, 'position' => 540),
		'fk_user_valid'        => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserValidation', 'enabled' => 1, 'visible' => -1, 'position' => 545),
		'fk_user_close'        => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserClose', 'enabled' => 1, 'visible' => -1, 'position' => 550),
		'import_key'           => array('type' => 'varchar(14)',  'label' => 'ImportId',         'enabled' => 1, 'visible' => -2, 'position' => 555),
		'model_pdf'            => array('type' => 'varchar(255)', 'label' => 'PDFTemplate',      'enabled' => 1, 'visible' => 0,  'position' => 560),
		'note_private'         => array('type' => 'html',         'label' => 'NotePrivate',      'enabled' => 1, 'visible' => 0,  'position' => 570),
		'note_public'          => array('type' => 'html',         'label' => 'NotePublic',       'enabled' => 1, 'visible' => 0,  'position' => 575),
	);

	// Public properties mapped from DB columns
	public $ref;
	public $entity;
	public $fk_soc;
	public $socid; // alias for compatibility
	public $fk_product;
	public $serial_number;
	public $fk_contact;
	public $customer_site;
	public $fk_project;
	public $fk_commande;
	public $fk_expedition_origin;
	public $fk_lot;
	public $issue_description;
	public $issue_date;
	public $reported_via;
	public $fk_pbxcall;
	public $resolution_type;
	public $resolution_notes;
	public $fk_warranty;
	public $warranty_status;
	public $billable;
	public $fk_facture;
	public $serial_in;
	public $serial_out;
	public $fk_warehouse_source;
	public $fk_warehouse_return;
	public $outbound_carrier;
	public $outbound_tracking;
	public $date_shipped;
	public $fk_shipment;
	public $return_carrier;
	public $return_tracking;
	public $date_return_expected;
	public $date_return_received;
	public $return_reminder_count = 0;
	public $fk_reception;
	public $fk_intervention;
	public $fk_user_assigned;
	public $status = self::STATUS_DRAFT;
	public $date_creation;
	public $date_validation;
	public $date_closed;
	public $fk_user_creat;
	public $fk_user_valid;
	public $fk_user_close;
	public $import_key;
	public $model_pdf;
	public $note_private;
	public $note_public;

	/** @var SvcRequestLine[] lines */
	public $lines = array();

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
	 * Create RMA case in database
	 *
	 * @param  User $user      User creating the record
	 * @param  int  $notrigger 0=launch triggers, 1=disable
	 * @return int             >0 if OK, <0 if KO
	 */
	public function create($user, $notrigger = 0)
	{
		global $conf;

		$error = 0;

		$this->db->begin();

		// Generate ref
		if (empty($this->ref) || $this->ref == '(PROV)') {
			$this->ref = $this->getNextNumRef();
		}

		$now = dol_now();
		$this->date_creation = $now;
		$this->fk_user_creat = $user->id;
		if (empty($this->status)) {
			$this->status = self::STATUS_DRAFT;
		}
		if (empty($this->issue_date)) {
			$this->issue_date = $now;
		}

		// fk_soc alias
		if (!empty($this->socid) && empty($this->fk_soc)) {
			$this->fk_soc = $this->socid;
		}

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."svc_request (";
		$sql .= " ref, entity, fk_soc, fk_product, serial_number, fk_contact, customer_site,";
		$sql .= " fk_project, fk_commande, fk_expedition_origin, fk_lot,";
		$sql .= " issue_description, issue_date, reported_via, fk_pbxcall,";
		$sql .= " resolution_type, resolution_notes,";
		$sql .= " fk_warranty, warranty_status, billable,";
		$sql .= " serial_in, serial_out, fk_warehouse_source, fk_warehouse_return,";
		$sql .= " fk_user_assigned, status,";
		$sql .= " date_creation, fk_user_creat,";
		$sql .= " model_pdf, note_private, note_public";
		$sql .= ") VALUES (";
		$sql .= " '".$this->db->escape($this->ref)."'";
		$sql .= ", ".((int) $conf->entity);
		$sql .= ", ".((int) $this->fk_soc);
		$sql .= ", ".((int) $this->fk_product);
		$sql .= ", ".($this->serial_number ? "'".$this->db->escape($this->serial_number)."'" : "NULL");
		$sql .= ", ".($this->fk_contact > 0 ? ((int) $this->fk_contact) : "NULL");
		$sql .= ", ".($this->customer_site ? "'".$this->db->escape($this->customer_site)."'" : "NULL");
		$sql .= ", ".($this->fk_project > 0 ? ((int) $this->fk_project) : "NULL");
		$sql .= ", ".($this->fk_commande > 0 ? ((int) $this->fk_commande) : "NULL");
		$sql .= ", ".($this->fk_expedition_origin > 0 ? ((int) $this->fk_expedition_origin) : "NULL");
		$sql .= ", ".($this->fk_lot > 0 ? ((int) $this->fk_lot) : "NULL");
		$sql .= ", ".($this->issue_description ? "'".$this->db->escape($this->issue_description)."'" : "NULL");
		$sql .= ", '".$this->db->idate($this->issue_date)."'";
		$sql .= ", ".($this->reported_via ? "'".$this->db->escape($this->reported_via)."'" : "NULL");
		$sql .= ", ".($this->fk_pbxcall > 0 ? ((int) $this->fk_pbxcall) : "NULL");
		$sql .= ", ".($this->resolution_type ? "'".$this->db->escape($this->resolution_type)."'" : "NULL");
		$sql .= ", ".($this->resolution_notes ? "'".$this->db->escape($this->resolution_notes)."'" : "NULL");
		$sql .= ", ".($this->fk_warranty > 0 ? ((int) $this->fk_warranty) : "NULL");
		$sql .= ", '".$this->db->escape($this->warranty_status ? $this->warranty_status : 'none')."'";
		$sql .= ", ".((int) $this->billable);
		$sql .= ", ".($this->serial_in ? "'".$this->db->escape($this->serial_in)."'" : "NULL");
		$sql .= ", ".($this->serial_out ? "'".$this->db->escape($this->serial_out)."'" : "NULL");
		$sql .= ", ".($this->fk_warehouse_source > 0 ? ((int) $this->fk_warehouse_source) : "NULL");
		$sql .= ", ".($this->fk_warehouse_return > 0 ? ((int) $this->fk_warehouse_return) : "NULL");
		$sql .= ", ".($this->fk_user_assigned > 0 ? ((int) $this->fk_user_assigned) : "NULL");
		$sql .= ", ".((int) $this->status);
		$sql .= ", '".$this->db->idate($this->date_creation)."'";
		$sql .= ", ".((int) $this->fk_user_creat);
		$sql .= ", ".($this->model_pdf ? "'".$this->db->escape($this->model_pdf)."'" : "NULL");
		$sql .= ", ".($this->note_private ? "'".$this->db->escape($this->note_private)."'" : "NULL");
		$sql .= ", ".($this->note_public ? "'".$this->db->escape($this->note_public)."'" : "NULL");
		$sql .= ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = $this->db->lasterror();
		}

		if (!$error) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'svc_request');

			// Insert extrafields
			if (!$error) {
				$result = $this->insertExtraFields();
				if ($result < 0) {
					$error++;
				}
			}

			if (!$notrigger) {
				$result = $this->call_trigger('WARRANTYSVC_CREATE', $user);
				if ($result < 0) {
					$error++;
				}
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return $this->id;
	}

	/**
	 * Load RMA case from database
	 *
	 * @param  int    $id   ID
	 * @param  string $ref  Ref
	 * @return int          >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id, $ref = '')
	{
		global $conf;

		$sql = "SELECT t.rowid, t.ref, t.entity, t.fk_soc, t.fk_product, t.serial_number,";
		$sql .= " t.fk_contact, t.customer_site, t.fk_project, t.fk_commande,";
		$sql .= " t.fk_expedition_origin, t.fk_lot,";
		$sql .= " t.issue_description, t.issue_date, t.reported_via, t.fk_pbxcall,";
		$sql .= " t.resolution_type, t.resolution_notes,";
		$sql .= " t.fk_warranty, t.warranty_status, t.billable, t.fk_facture,";
		$sql .= " t.serial_in, t.serial_out, t.fk_warehouse_source, t.fk_warehouse_return,";
		$sql .= " t.outbound_carrier, t.outbound_tracking, t.date_shipped, t.fk_shipment,";
		$sql .= " t.return_carrier, t.return_tracking, t.date_return_expected, t.date_return_received,";
		$sql .= " t.return_reminder_count, t.fk_reception, t.fk_intervention,";
		$sql .= " t.fk_user_assigned, t.status,";
		$sql .= " t.date_creation, t.date_validation, t.date_closed, t.tms,";
		$sql .= " t.fk_user_creat, t.fk_user_valid, t.fk_user_close,";
		$sql .= " t.import_key, t.model_pdf, t.last_main_doc,";
		$sql .= " t.note_private, t.note_public";
		$sql .= " FROM ".MAIN_DB_PREFIX."svc_request as t";
		if ($id) {
			$sql .= " WHERE t.rowid = ".((int) $id)." AND t.entity IN (".getEntity('svcrequest').")";
		} elseif ($ref) {
			$sql .= " WHERE t.ref = '".$this->db->escape($ref)."' AND t.entity = ".$conf->entity;
		} else {
			return -1;
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$this->id                   = $obj->rowid;
				$this->ref                  = $obj->ref;
				$this->entity               = $obj->entity;
				$this->fk_soc               = $obj->fk_soc;
				$this->socid                = $obj->fk_soc;
				$this->fk_product           = $obj->fk_product;
				$this->serial_number        = $obj->serial_number;
				$this->fk_contact           = $obj->fk_contact;
				$this->customer_site        = $obj->customer_site;
				$this->fk_project           = $obj->fk_project;
				$this->fk_commande          = $obj->fk_commande;
				$this->fk_expedition_origin = $obj->fk_expedition_origin;
				$this->fk_lot               = $obj->fk_lot;
				$this->issue_description    = $obj->issue_description;
				$this->issue_date           = $this->db->jdate($obj->issue_date);
				$this->reported_via         = $obj->reported_via;
				$this->fk_pbxcall           = $obj->fk_pbxcall;
				$this->resolution_type      = $obj->resolution_type;
				$this->resolution_notes     = $obj->resolution_notes;
				$this->fk_warranty          = $obj->fk_warranty;
				$this->warranty_status      = $obj->warranty_status;
				$this->billable             = $obj->billable;
				$this->fk_facture           = $obj->fk_facture;
				$this->serial_in            = $obj->serial_in;
				$this->serial_out           = $obj->serial_out;
				$this->fk_warehouse_source  = $obj->fk_warehouse_source;
				$this->fk_warehouse_return  = $obj->fk_warehouse_return;
				$this->outbound_carrier     = $obj->outbound_carrier;
				$this->outbound_tracking    = $obj->outbound_tracking;
				$this->date_shipped         = $this->db->jdate($obj->date_shipped);
				$this->fk_shipment          = $obj->fk_shipment;
				$this->return_carrier       = $obj->return_carrier;
				$this->return_tracking      = $obj->return_tracking;
				$this->date_return_expected = $this->db->jdate($obj->date_return_expected);
				$this->date_return_received = $this->db->jdate($obj->date_return_received);
				$this->return_reminder_count = $obj->return_reminder_count;
				$this->fk_reception         = $obj->fk_reception;
				$this->fk_intervention      = $obj->fk_intervention;
				$this->fk_user_assigned     = $obj->fk_user_assigned;
				$this->status               = $obj->status;
				$this->date_creation        = $this->db->jdate($obj->date_creation);
				$this->date_validation      = $this->db->jdate($obj->date_validation);
				$this->date_closed          = $this->db->jdate($obj->date_closed);
				$this->tms                  = $this->db->jdate($obj->tms);
				$this->fk_user_creat        = $obj->fk_user_creat;
				$this->fk_user_valid        = $obj->fk_user_valid;
				$this->fk_user_close        = $obj->fk_user_close;
				$this->import_key           = $obj->import_key;
				$this->model_pdf            = $obj->model_pdf;
				$this->last_main_doc        = $obj->last_main_doc;
				$this->note_private         = $obj->note_private;
				$this->note_public          = $obj->note_public;

				// Fetch lines
				$this->fetchLines();

				// Fetch extrafields
				$this->fetch_optionals();

				return 1;
			}
			return 0;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Fetch lines for this RMA case
	 *
	 * @return int >0 if OK, <0 if KO
	 */
	public function fetchLines()
	{
		$this->lines = array();

		require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcrequestline.class.php';

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."svc_request_line";
		$sql .= " WHERE fk_svc_request = ".((int) $this->id);
		$sql .= " ORDER BY rang ASC, rowid ASC";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$line = new SvcRequestLine($this->db);
				$line->fetch($obj->rowid);
				$this->lines[] = $line;
			}
			return 1;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Update RMA case in database
	 *
	 * @param  User $user      User performing update
	 * @param  int  $notrigger 0=launch triggers, 1=disable
	 * @return int             >0 if OK, <0 if KO
	 */
	public function update($user, $notrigger = 0)
	{
		$error = 0;

		$this->db->begin();

		if (!empty($this->socid) && empty($this->fk_soc)) {
			$this->fk_soc = $this->socid;
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX."svc_request SET";
		$sql .= " fk_soc = ".((int) $this->fk_soc);
		$sql .= ", fk_product = ".((int) $this->fk_product);
		$sql .= ", serial_number = ".($this->serial_number ? "'".$this->db->escape($this->serial_number)."'" : "NULL");
		$sql .= ", fk_contact = ".($this->fk_contact > 0 ? ((int) $this->fk_contact) : "NULL");
		$sql .= ", customer_site = ".($this->customer_site ? "'".$this->db->escape($this->customer_site)."'" : "NULL");
		$sql .= ", fk_project = ".($this->fk_project > 0 ? ((int) $this->fk_project) : "NULL");
		$sql .= ", fk_commande = ".($this->fk_commande > 0 ? ((int) $this->fk_commande) : "NULL");
		$sql .= ", fk_expedition_origin = ".($this->fk_expedition_origin > 0 ? ((int) $this->fk_expedition_origin) : "NULL");
		$sql .= ", fk_lot = ".($this->fk_lot > 0 ? ((int) $this->fk_lot) : "NULL");
		$sql .= ", issue_description = ".($this->issue_description ? "'".$this->db->escape($this->issue_description)."'" : "NULL");
		$sql .= ", issue_date = ".($this->issue_date ? "'".$this->db->idate($this->issue_date)."'" : "NULL");
		$sql .= ", reported_via = ".($this->reported_via ? "'".$this->db->escape($this->reported_via)."'" : "NULL");
		$sql .= ", fk_pbxcall = ".($this->fk_pbxcall > 0 ? ((int) $this->fk_pbxcall) : "NULL");
		$sql .= ", resolution_type = ".($this->resolution_type ? "'".$this->db->escape($this->resolution_type)."'" : "NULL");
		$sql .= ", resolution_notes = ".($this->resolution_notes ? "'".$this->db->escape($this->resolution_notes)."'" : "NULL");
		$sql .= ", fk_warranty = ".($this->fk_warranty > 0 ? ((int) $this->fk_warranty) : "NULL");
		$sql .= ", warranty_status = '".$this->db->escape($this->warranty_status ? $this->warranty_status : 'none')."'";
		$sql .= ", billable = ".((int) $this->billable);
		$sql .= ", fk_facture = ".($this->fk_facture > 0 ? ((int) $this->fk_facture) : "NULL");
		$sql .= ", serial_in = ".($this->serial_in ? "'".$this->db->escape($this->serial_in)."'" : "NULL");
		$sql .= ", serial_out = ".($this->serial_out ? "'".$this->db->escape($this->serial_out)."'" : "NULL");
		$sql .= ", fk_warehouse_source = ".($this->fk_warehouse_source > 0 ? ((int) $this->fk_warehouse_source) : "NULL");
		$sql .= ", fk_warehouse_return = ".($this->fk_warehouse_return > 0 ? ((int) $this->fk_warehouse_return) : "NULL");
		$sql .= ", outbound_carrier = ".($this->outbound_carrier ? "'".$this->db->escape($this->outbound_carrier)."'" : "NULL");
		$sql .= ", outbound_tracking = ".($this->outbound_tracking ? "'".$this->db->escape($this->outbound_tracking)."'" : "NULL");
		$sql .= ", date_shipped = ".($this->date_shipped ? "'".$this->db->idate($this->date_shipped)."'" : "NULL");
		$sql .= ", fk_shipment = ".($this->fk_shipment > 0 ? ((int) $this->fk_shipment) : "NULL");
		$sql .= ", return_carrier = ".($this->return_carrier ? "'".$this->db->escape($this->return_carrier)."'" : "NULL");
		$sql .= ", return_tracking = ".($this->return_tracking ? "'".$this->db->escape($this->return_tracking)."'" : "NULL");
		$sql .= ", date_return_expected = ".($this->date_return_expected ? "'".$this->db->idate($this->date_return_expected)."'" : "NULL");
		$sql .= ", date_return_received = ".($this->date_return_received ? "'".$this->db->idate($this->date_return_received)."'" : "NULL");
		$sql .= ", return_reminder_count = ".((int) $this->return_reminder_count);
		$sql .= ", fk_reception = ".($this->fk_reception > 0 ? ((int) $this->fk_reception) : "NULL");
		$sql .= ", fk_intervention = ".($this->fk_intervention > 0 ? ((int) $this->fk_intervention) : "NULL");
		$sql .= ", fk_user_assigned = ".($this->fk_user_assigned > 0 ? ((int) $this->fk_user_assigned) : "NULL");
		$sql .= ", status = ".((int) $this->status);
		$sql .= ", model_pdf = ".($this->model_pdf ? "'".$this->db->escape($this->model_pdf)."'" : "NULL");
		$sql .= ", note_private = ".($this->note_private ? "'".$this->db->escape($this->note_private)."'" : "NULL");
		$sql .= ", note_public = ".($this->note_public ? "'".$this->db->escape($this->note_public)."'" : "NULL");
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$error++;
			$this->errors[] = $this->db->lasterror();
		}

		// Update extrafields
		if (!$error) {
			$result = $this->insertExtraFields();
			if ($result < 0) {
				$error++;
			}
		}

		if (!$error && !$notrigger) {
			$result = $this->call_trigger('WARRANTYSVC_MODIFY', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Delete RMA case
	 *
	 * @param  User $user      User deleting
	 * @param  int  $notrigger 0=launch triggers, 1=disable
	 * @return int             >0 if OK, <0 if KO
	 */
	public function delete($user, $notrigger = 0)
	{
		$error = 0;

		$this->db->begin();

		if (!$notrigger) {
			$result = $this->call_trigger('WARRANTYSVC_DELETE', $user);
			if ($result < 0) {
				$error++;
			}
		}

		if (!$error) {
			// Delete lines first
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."svc_request_line WHERE fk_svc_request = ".((int) $this->id);
			if (!$this->db->query($sql)) {
				$error++;
				$this->errors[] = $this->db->lasterror();
			}
		}

		if (!$error) {
			// Delete extrafields
			$result = $this->deleteExtraFields();
			if ($result < 0) {
				$error++;
			}
		}

		if (!$error) {
			$sql = "DELETE FROM ".MAIN_DB_PREFIX."svc_request WHERE rowid = ".((int) $this->id);
			if (!$this->db->query($sql)) {
				$error++;
				$this->errors[] = $this->db->lasterror();
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Validate the RMA case (move to STATUS_VALIDATED)
	 *
	 * @param  User $user      User validating
	 * @param  int  $notrigger 0=launch triggers, 1=disable
	 * @return int             >0 if OK, <0 if KO
	 */
	public function validate($user, $notrigger = 0)
	{
		if ($this->status != self::STATUS_DRAFT) {
			$this->error = 'SvcRequestNotInDraftStatus';
			return -1;
		}

		$this->status          = self::STATUS_VALIDATED;
		$this->date_validation = dol_now();
		$this->fk_user_valid   = $user->id;

		// Auto-check warranty status
		$this->checkWarrantyStatus();

		$result = $this->update($user, $notrigger);
		if ($result < 0) {
			return $result;
		}

		if (!$notrigger) {
			$this->call_trigger('WARRANTYSVC_VALIDATE', $user);
		}

		return 1;
	}

	/**
	 * Set RMA to In Progress
	 *
	 * @param  User $user User performing action
	 * @return int        >0 if OK, <0 if KO
	 */
	public function setInProgress($user)
	{
		if ($this->status != self::STATUS_VALIDATED) {
			$this->error = 'SvcRequestNotInValidatedStatus';
			return -1;
		}

		$this->status = self::STATUS_IN_PROGRESS;
		return $this->update($user);
	}

	/**
	 * Mark as awaiting return (for swap_cross / swap_wait resolutions)
	 *
	 * @param  User $user User performing action
	 * @return int        >0 if OK, <0 if KO
	 */
	public function setAwaitingReturn($user)
	{
		$this->status = self::STATUS_AWAIT_RETURN;
		return $this->update($user);
	}

	/**
	 * Mark as resolved
	 *
	 * @param  User $user User performing action
	 * @return int        >0 if OK, <0 if KO
	 */
	public function resolve($user)
	{
		$this->status = self::STATUS_RESOLVED;
		return $this->update($user);
	}

	/**
	 * Close the RMA case
	 *
	 * @param  User $user      User closing
	 * @param  int  $notrigger 0=launch triggers, 1=disable
	 * @return int             >0 if OK, <0 if KO
	 */
	public function close($user, $notrigger = 0)
	{
		if (!in_array($this->status, array(self::STATUS_RESOLVED, self::STATUS_IN_PROGRESS, self::STATUS_AWAIT_RETURN))) {
			$this->error = 'SvcRequestCannotBeClosedFromCurrentStatus';
			return -1;
		}

		$this->status      = self::STATUS_CLOSED;
		$this->date_closed = dol_now();
		$this->fk_user_close = $user->id;

		$result = $this->update($user, $notrigger);
		if ($result > 0 && !$notrigger) {
			$this->call_trigger('WARRANTYSVC_CLOSE', $user);
		}

		return $result;
	}

	/**
	 * Cancel the RMA case
	 *
	 * @param  User $user User cancelling
	 * @return int        >0 if OK, <0 if KO
	 */
	public function cancel($user)
	{
		if (in_array($this->status, array(self::STATUS_CLOSED, self::STATUS_CANCELLED))) {
			$this->error = 'SvcRequestAlreadyClosedOrCancelled';
			return -1;
		}

		$this->status = self::STATUS_CANCELLED;
		return $this->update($user);
	}

	/**
	 * Re-open a closed/resolved RMA case back to in_progress
	 *
	 * @param  User $user User re-opening
	 * @return int        >0 if OK, <0 if KO
	 */
	public function reopen($user)
	{
		$this->status      = self::STATUS_IN_PROGRESS;
		$this->date_closed = null;
		$this->fk_user_close = null;
		return $this->update($user);
	}

	/**
	 * Check warranty coverage for this RMA case's serial number.
	 * Sets $this->warranty_status and $this->billable accordingly.
	 *
	 * @return string warranty status: 'active', 'expired', or 'none'
	 */
	public function checkWarrantyStatus()
	{
		require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarranty.class.php';

		$warranty = new SvcWarranty($this->db);

		// If a warranty was manually paired, use it directly; otherwise look up by serial
		if (!empty($this->fk_warranty)) {
			$result = $warranty->fetch($this->fk_warranty);
		} elseif (!empty($this->serial_number)) {
			$result = $warranty->fetchBySerial($this->serial_number);
		} else {
			$this->warranty_status = 'none';
			$this->billable        = 1;
			return 'none';
		}

		if ($result > 0) {
			$this->fk_warranty     = $warranty->id;
			$this->warranty_status = $warranty->status;
			if ($warranty->status == 'active') {
				$this->billable = 0;
			} else {
				$this->billable = 1;
			}
			return $warranty->status;
		}

		$this->warranty_status = 'none';
		$this->billable        = 1;
		return 'none';
	}

	/**
	 * Ensure element_element links exist for all populated FK fields.
	 * Idempotent — safe to call after every create/update. Skips links
	 * that already exist to avoid duplicates.
	 *
	 * @return void
	 */
	public function syncLinkedObjects()
	{
		if (empty($this->id)) {
			return;
		}

		$target_type = $this->getElementType(); // 'warrantysvc_svcrequest'
		$bare_target = $this->element;           // 'svcrequest'

		// Use prefixed element types for custom objects so showLinkedObjectBlock
		// can resolve the template path (warrantysvc_svcwarranty → warrantysvc/svcwarranty/tpl/).
		// Core objects (commande, shipping, etc.) use their native unprefixed names.
		// The 'stale' key lists unprefixed aliases to clean up from older versions.
		$links = array(
			array('type' => 'warrantysvc_svcwarranty', 'fk' => $this->fk_warranty,      'stale' => array('svcwarranty')),
			array('type' => 'commande',                'fk' => $this->fk_commande,       'stale' => array()),
			array('type' => 'shipping',                'fk' => $this->fk_shipment,       'stale' => array()),
			array('type' => 'facture',                 'fk' => $this->fk_facture,        'stale' => array()),
			array('type' => 'reception',               'fk' => $this->fk_reception,      'stale' => array()),
			array('type' => 'fichinter',               'fk' => $this->fk_intervention,   'stale' => array()),
		);

		foreach ($links as $link) {
			$source_type = $link['type'];
			$fk_id       = $link['fk'];

			if (empty($fk_id) || $fk_id <= 0) {
				continue;
			}

			// Clean up stale rows with unprefixed types from older versions
			foreach ($link['stale'] as $stale_type) {
				$sql_del = "DELETE FROM ".MAIN_DB_PREFIX."element_element WHERE ((fk_source = ".((int) $fk_id)." AND sourcetype = '".$this->db->escape($stale_type)."' AND fk_target = ".((int) $this->id).") OR (fk_target = ".((int) $fk_id)." AND targettype = '".$this->db->escape($stale_type)."' AND fk_source = ".((int) $this->id)."))";

				$this->db->query($sql_del);
			}

			// Also clean up rows where our target type is unprefixed
			if ($bare_target !== $target_type) {
				$sql_del2 = "DELETE FROM ".MAIN_DB_PREFIX."element_element WHERE fk_target = ".((int) $this->id)." AND targettype = '".$this->db->escape($bare_target)."'";
				$this->db->query($sql_del2);
			}

			// Check if the correct prefixed link already exists
			$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."element_element WHERE ((fk_source = ".((int) $fk_id)." AND sourcetype = '".$this->db->escape($source_type)."' AND fk_target = ".((int) $this->id)." AND targettype = '".$this->db->escape($target_type)."') OR (fk_source = ".((int) $this->id)." AND sourcetype = '".$this->db->escape($target_type)."' AND fk_target = ".((int) $fk_id)." AND targettype = '".$this->db->escape($source_type)."')) LIMIT 1";

			$res = $this->db->query($sql);
			if ($res && $this->db->num_rows($res) == 0) {
				$this->add_object_linked($source_type, $fk_id);
			}
		}
	}

	/**
	 * Whether this RMA is covered under active warranty
	 *
	 * @return bool
	 */
	public function isWarrantyCovered()
	{
		return ($this->warranty_status == 'active');
	}

	/**
	 * Return a clickable link to this SR (used by showLinkedObjectBlock)
	 *
	 * @param  int    $withpicto  0=no picto, 1=include picto
	 * @param  string $option     Unused
	 * @param  int    $notooltip  1=disable tooltip
	 * @return string             HTML link
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0)
	{
		$url = DOL_URL_ROOT.'/custom/warrantysvc/card.php?id='.$this->id;
		$label = $this->ref;
		$link = '<a href="'.$url.'" title="'.dol_escape_htmltag($label).'">';
		$linkend = '</a>';
		$result = $link;
		if ($withpicto) {
			$result .= img_picto('', 'technic', 'class="pictofixedwidth"');
		}
		$result .= $label.$linkend;
		return $result;
	}

	/**
	 * Create a return reception (Reception) for equipment coming back from the customer.
	 *
	 * The reception is created without a source PO. Product lines are derived from
	 * the outbound shipment (fk_shipment) so the customer can only return what was sent.
	 * Lines are inserted directly into receptiondet_batch (fk_commandefourndet = NULL).
	 *
	 * @param  User $user          User performing action
	 * @param  int  $fk_warehouse  Destination warehouse for the returned goods
	 * @return int                 Reception ID if OK, <0 if KO
	 */
	public function createReturnReception($user, $fk_warehouse)
	{
		global $conf;

		if (!isModEnabled('reception')) {
			$this->error = 'ModuleReceptionNotEnabled';
			return -1;
		}

		require_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';

		// Build lines to receive — prefer shipment lines, fall back to warranty product
		$lines_to_receive = array();
		if (!empty($this->fk_shipment)) {
			require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
			$shipment = new Expedition($this->db);
			if ($shipment->fetch($this->fk_shipment) <= 0) {
				$this->error = 'CannotLoadShipment';
				return -1;
			}
			if (empty($shipment->lines)) {
				$shipment->fetch_lines();
			}
			foreach ($shipment->lines as $exp_line) {
				$fk_product = (int) $exp_line->fk_product;
				$qty        = (float) $exp_line->qty;
				if ($fk_product > 0 && $qty > 0) {
					$lines_to_receive[] = array('fk_product' => $fk_product, 'qty' => $qty);
				}
			}
		} else {
			// No shipment — use warranty product (qty 1)
			require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarranty.class.php';
			$_war = new SvcWarranty($this->db);
			if (empty($this->fk_warranty) || $_war->fetch($this->fk_warranty) <= 0 || empty($_war->fk_product)) {
				$this->error = 'NoShipmentOrWarrantyProductToReceive';
				return -1;
			}
			$lines_to_receive[] = array('fk_product' => (int) $_war->fk_product, 'qty' => 1);
		}

		if (empty($lines_to_receive)) {
			$this->error = 'NoProductLinesToReceive';
			return -1;
		}

		$this->db->begin();

		$reception               = new Reception($this->db);
		$reception->socid        = $this->fk_soc;
		$reception->fk_projet    = $this->fk_project;
		$reception->note_private = 'RMA '.$this->ref.' - customer return';
		$reception->entity       = $conf->entity;

		$rec_id = $reception->create($user);
		if ($rec_id < 0) {
			$this->db->rollback();
			$this->error  = $reception->error;
			$this->errors = $reception->errors;
			return -1;
		}

		// Insert reception lines (no PO line — fk_commandefourndet NULL)
		foreach ($lines_to_receive as $line) {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."receptiondet_batch (fk_reception, fk_product, qty, fk_entrepot, fk_commandefourndet, comment, status) VALUES (".$rec_id.", ".$line['fk_product'].", ".$line['qty'].", ".((int) $fk_warehouse).", NULL, '".$this->db->escape('RMA '.$this->ref)."', 0)";
			if (!$this->db->query($sql)) {
				$this->db->rollback();
				$this->error = $this->db->lasterror();
				return -1;
			}
		}

		$this->fk_reception        = $rec_id;
		$this->fk_warehouse_return = (int) $fk_warehouse;
		if ($this->update($user) < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->syncLinkedObjects();
		$this->db->commit();
		return $rec_id;
	}

	/**
	 * Create a replacement shipment (Expedition) directly from this service request,
	 * bypassing the sales order flow. The shipment is created in Draft status.
	 *
	 * @param  User $user User performing action
	 * @return int        Shipment ID if OK, <0 if KO
	 */
	public function createReplacementShipment($user)
	{
		global $conf, $langs;

		if (!isModEnabled('shipping') && !isModEnabled('expedition')) {
			$this->error = $langs->trans('ErrorExpeditionModuleDisabled');
			return -1;
		}
		if (!isModEnabled('order') && !isModEnabled('commande')) {
			$this->error = $langs->trans('ErrorFieldRequired', 'Orders module');
			return -1;
		}

		if (empty($this->fk_product)) {
			$this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Product'));
			return -1;
		}

		// Determine warehouse: SR field → global setting
		$warehouse_id = !empty($this->fk_warehouse_source) ? (int) $this->fk_warehouse_source : getDolGlobalInt('WARRANTYSVC_WAREHOUSE_REFURB');
		if ($warehouse_id <= 0) {
			$this->error = $langs->trans('ErrorNoWarehouseSelected');
			return -1;
		}

		require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
		require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';

		$langs->load('warrantysvc@warrantysvc');

		$this->db->begin();

		// Step 1: Create a $0 warranty replacement order
		$order = new Commande($this->db);
		$order->socid = $this->fk_soc;
		$order->fk_project = $this->fk_project;
		$order->note_private = 'RMA '.$this->ref.' - warranty replacement (auto-created)';
		$order->entity = $conf->entity;
		$order->date_commande = dol_now();

		$order_id = $order->create($user);
		if ($order_id <= 0) {
			$this->db->rollback();
			$this->error = 'Order create failed: '.($order->error ?: $this->db->lasterror());
			$this->errors = $order->errors;
			return -1;
		}

		// Add product line at $0
		$result = $order->addline(
			$langs->trans('ReplacementForSR', $this->ref),
			0,
			1,
			0,
			0,
			0,
			$this->fk_product
		);
		if ($result <= 0) {
			$this->db->rollback();
			$this->error = 'Order addline failed: '.($order->error ?: $this->db->lasterror());
			return -1;
		}
		$order_line_id = $result;

		// Validate the order
		$result = $order->valid($user);
		if ($result <= 0) {
			$this->db->rollback();
			$this->error = 'Order validate failed: '.($order->error ?: $this->db->lasterror());
			return -1;
		}

		// Step 2: Create shipment from the order
		$expedition = new Expedition($this->db);
		$expedition->socid = $this->fk_soc;
		$expedition->origin = 'commande';
		$expedition->origin_id = $order_id;
		$expedition->entrepot_id = $warehouse_id;
		$expedition->fk_project = $this->fk_project;
		$expedition->note_private = 'RMA '.$this->ref.' - replacement shipment';
		$expedition->entity = $conf->entity;
		$expedition->date_creation = dol_now();

		$exp_id = $expedition->create($user);
		if ($exp_id <= 0) {
			$this->db->rollback();
			$this->error = 'Expedition create failed: '.($expedition->error ?: $this->db->lasterror());
			$this->errors = $expedition->errors;
			return -1;
		}

		// Add the order line to the shipment
		// Check if product is batch-tracked — if so, use addline_batch
		// Fetch order line via Commande::fetch_lines to get product_tobatch populated
		$order->fetch_lines();
		$orderline = null;
		foreach ($order->lines as $ol) {
			if ($ol->id == $order_line_id) {
				$orderline = $ol;
				break;
			}
		}
		if (!$orderline) {
			$this->db->rollback();
			$this->error = 'Order line not found after creation';
			return -1;
		}

		$is_batch_product = false;
		if (isModEnabled('productbatch')) {
			require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
			$prod_check = new Product($this->db);
			$prod_check->fetch($this->fk_product);
			$is_batch_product = !empty($prod_check->status_batch);
		}

		if (!$is_batch_product) {
			// Non-batch product — add line via API
			$line_result = $expedition->addline($warehouse_id, $order_line_id, 1);
			if ($line_result < 0) {
				$this->db->rollback();
				$this->error = 'Expedition addline failed: '.($expedition->error ?: $this->db->lasterror());
				return -1;
			}
		} else {
			// Batch-tracked product — insert expeditiondet with fk_elementdet
			// pointing to the order line so the dispatch page can render the
			// serial picker. Direct SQL required because addline() rejects batch
			// products and create_line() requires fk_elementdet OR fk_parent but
			// then addline() blocks batch products anyway.
			// TODO: Dolibarr 23+ has addlinefree() — switch to that when upgrading.
			$sql_line = "INSERT INTO ".MAIN_DB_PREFIX."expeditiondet";
			$sql_line .= " (fk_expedition, fk_entrepot, fk_elementdet, fk_product, qty, element_type, rang)";
			$sql_line .= " VALUES (".$exp_id.", ".((int) $warehouse_id).", ".((int) $order_line_id);
			$sql_line .= ", ".((int) $this->fk_product).", 1, 'order', 0)";
			if (!$this->db->query($sql_line)) {
				$this->db->rollback();
				$this->error = 'Insert expeditiondet failed: '.$this->db->lasterror();
				return -1;
			}
		}

		// Link shipment and order to this SR via element_element
		$this->add_object_linked('shipping', $exp_id);

		// Update SR with shipment and order references
		$this->fk_shipment = $exp_id;
		$this->fk_commande = $order_id;
		if ($this->update($user, 1) < 0) {
			$this->db->rollback();
			$this->error = 'SR update failed: '.$this->error;
			return -1;
		}

		$this->db->commit();
		return $exp_id;
	}

	/**
	 * Validate the return reception linked to this RMA.
	 *
	 * @param  User $user User performing action
	 * @return int        >0 if OK, <0 if KO
	 */
	public function validateReception($user)
	{
		if (empty($this->fk_reception)) {
			$this->error = 'NoReceptionLinked';
			return -1;
		}

		require_once DOL_DOCUMENT_ROOT.'/reception/class/reception.class.php';

		$reception = new Reception($this->db);
		if ($reception->fetch($this->fk_reception) <= 0) {
			$this->error = 'CannotLoadReception';
			return -1;
		}
		if ((int) $reception->statut >= 1) {
			return 1; // already validated
		}

		if ($reception->valid($user) < 0) {
			$this->error  = $reception->error;
			$this->errors = $reception->errors;
			return -1;
		}

		$this->date_return_received = dol_now();
		return $this->update($user);
	}

	/**
	 * Link an existing shipment to this RMA
	 *
	 * @param  int    $shipment_id Expedition ID
	 * @param  string $tracking    Tracking number
	 * @param  string $carrier     Carrier name
	 * @param  User   $user        User performing action
	 * @return int                 >0 if OK, <0 if KO
	 */
	public function linkShipment($shipment_id, $tracking, $carrier, $user)
	{
		$this->fk_shipment      = $shipment_id;
		$this->outbound_tracking = $tracking;
		$this->outbound_carrier  = $carrier;
		$this->date_shipped      = dol_now();
		return $this->update($user);
	}

	/**
	 * Link a reception (return receipt) to this RMA
	 *
	 * @param  int    $reception_id  Reception ID
	 * @param  string $condition     Condition notes for service log
	 * @param  User   $user          User performing action
	 * @return int                   >0 if OK, <0 if KO
	 */
	public function linkReception($reception_id, $condition, $user)
	{
		$this->fk_reception          = $reception_id;
		$this->date_return_received  = dol_now();
		return $this->update($user);
	}

	/**
	 * Create a linked Intervention (fichinter) for repair work
	 *
	 * @param  User   $user User performing action
	 * @param  string $type 'repair' or 'onsite'
	 * @return int          Fichinter ID if OK, <0 if KO
	 */
	public function createLinkedIntervention($user, $type = 'repair')
	{
		if (!isModEnabled('ficheinter')) {
			$this->error = 'ModuleInterventionNotEnabled';
			return -1;
		}

		require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';

		$intervention = new Fichinter($this->db);
		$intervention->socid       = $this->fk_soc;
		$intervention->fk_projet   = $this->fk_project;
		$intervention->description = 'RMA '.$this->ref.' - '.($this->issue_description ? substr($this->issue_description, 0, 200) : '');
		$intervention->datec       = dol_now();
		$intervention->datei       = dol_now();

		// Store back-reference in extrafields
		$intervention->array_options['options_rma_ref']           = $this->ref;
		$intervention->array_options['options_rma_serial_in']     = $this->serial_in ? $this->serial_in : $this->serial_number;

		$fichinter_id = $intervention->create($user);
		if ($fichinter_id < 0) {
			$this->error  = $intervention->error;
			$this->errors = $intervention->errors;
			return -1;
		}

		$this->fk_intervention = $fichinter_id;
		$this->update($user);
		$this->syncLinkedObjects();

		return $fichinter_id;
	}

	/**
	 * Link an existing intervention to this RMA
	 *
	 * @param  int  $fichinter_id Fichinter ID
	 * @param  User $user         User
	 * @return int                >0 if OK, <0 if KO
	 */
	public function linkIntervention($fichinter_id, $user)
	{
		$this->fk_intervention = $fichinter_id;
		return $this->update($user);
	}

	/**
	 * Send overdue return reminder email to customer contact
	 *
	 * Uses CMailFile with Dolibarr's mail infrastructure. Increments
	 * return_reminder_count and sets return_reminder_date on success.
	 *
	 * @param  User $user User sending reminder
	 * @return int        1 if OK, -1 if KO
	 */
	public function sendReturnReminder($user)
	{
		global $conf, $langs;

		$langs->loadLangs(array('warrantysvc@warrantysvc', 'mails'));

		// Load customer email
		$soc = new Societe($this->db);
		if ($soc->fetch($this->fk_soc) <= 0 || empty($soc->email)) {
			$this->error = 'NoEmailForCustomer';
			return -1;
		}

		// Load company info for sender
		require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

		$from_name  = getDolGlobalString('MAIN_MAIL_FROM_NAME', $conf->global->MAIN_INFO_SOCIETE_NOM ?? '');
		$from_email = getDolGlobalString('MAIN_MAIL_FROM_EMAIL', getDolGlobalString('MAIN_INFO_SOCIETE_MAIL', ''));

		if (empty($from_email)) {
			$this->error = 'NoSenderEmailConfigured';
			return -1;
		}

		$to_email = $soc->email;
		$subject  = $langs->trans('ReminderReturnSubject', $this->ref);

		// Build body
		$body  = $langs->trans('ReminderReturnBody', $this->ref, $this->serial_number ? $this->serial_number : '-');
		$body .= "\n\n";
		if (!empty($this->outbound_carrier)) {
			$body .= $langs->trans('OutboundCarrier').': '.$this->outbound_carrier."\n";
		}
		if (!empty($this->outbound_tracking)) {
			$body .= $langs->trans('OutboundTracking').': '.$this->outbound_tracking."\n";
		}
		$body .= "\n".$langs->trans('ReminderReturnFooter');

		$mail = new CMailFile(
			$subject,
			$to_email,
			$from_email,
			$body,
			array(),
			array(),
			array(),
			'',
			'',
			0,
			-1,
			'',
			'',
			'',
			$from_name
		);

		if ($mail->sendfile() <= 0) {
			$this->error = $mail->error;
			return -1;
		}

		$this->return_reminder_count = (int) $this->return_reminder_count + 1;
		$this->return_reminder_date  = dol_now();
		return $this->update($user, 1); // notrigger=1 — suppress trigger on reminder update
	}

	/**
	 * Generate an invoice for a non-returned unit
	 *
	 * @param  User $user     User creating invoice
	 * @param  bool $validate Auto-validate the invoice
	 * @return int            Invoice ID if OK, <0 if KO
	 */
	public function invoiceForNonReturn($user, $validate = false)
	{
		if (!isModEnabled('facture')) {
			$this->error = 'ModuleFactureNotEnabled';
			return -1;
		}

		require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

		$invoice = new Facture($this->db);
		$invoice->socid      = $this->fk_soc;
		$invoice->type       = Facture::TYPE_STANDARD;
		$invoice->fk_project = $this->fk_project;
		$invoice->note_public = 'RMA '.$this->ref.' - Unit not returned by deadline';

		$invoice_id = $invoice->create($user);
		if ($invoice_id < 0) {
			$this->error  = $invoice->error;
			$this->errors = $invoice->errors;
			return -1;
		}

		$this->fk_facture = $invoice_id;
		$this->billable   = 1;
		$this->update($user);
		$this->syncLinkedObjects();

		return $invoice_id;
	}

	/**
	 * Suggest a replacement serial number from refurbished stock
	 *
	 * @param  int    $product_id Product ID to match
	 * @param  string $strategy   'fifo'|'least_serviced'|'best_condition'
	 * @return string|null        Serial number or null if none available
	 */
	public static function suggestReplacementSerial($product_id, $strategy = 'fifo')
	{
		// TODO: query llx_product_lot / llx_entrepot to find available serials
		// Strategy logic to be implemented once warehouse config is known
		return null;
	}

	/**
	 * Cron job: check for overdue returns, send reminders, and auto-invoice
	 *
	 * Reads WARRANTYSVC_RETURN_GRACE_DAYS (default 7) and
	 * WARRANTYSVC_RETURN_INVOICE_DAYS (default 30) from config.
	 *
	 * - Sends a reminder email to each customer whose return is past the
	 *   grace period and who has not yet been reminded today.
	 * - Creates an invoice for each customer whose return is past the
	 *   invoice deadline and who does not yet have an invoice linked.
	 *
	 * @return int >0 number of records processed, <0 if fatal error
	 */
	public function checkOverdueReturns()
	{
		global $conf, $user;

		$grace_days   = getDolGlobalInt('WARRANTYSVC_RETURN_GRACE_DAYS', 7);
		$invoice_days = getDolGlobalInt('WARRANTYSVC_RETURN_INVOICE_DAYS', 30);

		$now          = dol_now();
		$grace_limit  = $now - ($grace_days * 86400);
		$invoice_limit= $now - ($invoice_days * 86400);

		// Fetch all awaiting-return records with no reception and past grace period
		$sql  = "SELECT rowid FROM ".MAIN_DB_PREFIX."svc_request";
		$sql .= " WHERE status = ".self::STATUS_AWAIT_RETURN;
		$sql .= " AND fk_reception IS NULL";
		$sql .= " AND date_return_expected IS NOT NULL";
		$sql .= " AND date_return_expected < '".$this->db->idate($grace_limit)."'";
		$sql .= " AND entity IN (".getEntity('svcrequest').")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$processed = 0;

		while ($obj = $this->db->fetch_object($resql)) {
			$rma = new SvcRequest($this->db);
			if ($rma->fetch($obj->rowid) <= 0) {
				continue;
			}

			// --- AUTO-INVOICE for severely overdue ---
			if ($rma->date_return_expected < $invoice_limit && empty($rma->fk_facture)) {
				$rma->invoiceForNonReturn($user);
			}

			// --- SEND REMINDER if not sent today ---
			$already_reminded_today = (!empty($rma->return_reminder_date)
				&& dol_print_date($rma->return_reminder_date, 'day') == dol_print_date($now, 'day'));

			if (!$already_reminded_today) {
				$rma->sendReturnReminder($user);
			}

			$processed++;
		}

		$this->db->free($resql);
		return $processed > 0 ? $processed : 1;
	}

	/**
	 * Get next reference number
	 *
	 * @param  Societe|null $objsoc Thirdparty (unused, for compatibility)
	 * @return string               Next ref
	 */
	public function getNextNumRef($objsoc = null)
	{
		global $db, $conf;

		require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/core/modules/warrantysvc/modules_warrantysvc.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/core/modules/warrantysvc/mod_warrantysvc_standard.php';

		$mod = new mod_warrantysvc_standard();
		return $mod->getNextValue($objsoc, $this);
	}

	/**
	 * Return label of a status (wrapper for LibStatut)
	 *
	 * @param  int    $mode   0=long label, 1=short, 3=picto
	 * @return string         Label
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 * Return label for a status code
	 *
	 * @param  int    $status Status code
	 * @param  int    $mode   0=long label, 1=short, 2=picto+label
	 * @return string         Label
	 */
	public function LibStatut($status, $mode = 0)
	{
		global $langs;
		$langs->load('warrantysvc');

		$statusLabels = array(
			self::STATUS_DRAFT        => array('label' => 'SvcDraft',        'picto' => 'status0'),
			self::STATUS_VALIDATED    => array('label' => 'SvcValidated',    'picto' => 'status1'),
			self::STATUS_IN_PROGRESS  => array('label' => 'SvcInProgress',   'picto' => 'status3'),
			self::STATUS_AWAIT_RETURN => array('label' => 'AwaitingReturn','picto' => 'status4'),
			self::STATUS_RESOLVED     => array('label' => 'SvcResolved',     'picto' => 'status6'),
			self::STATUS_CLOSED       => array('label' => 'SvcClosed',       'picto' => 'status6'),
			self::STATUS_CANCELLED    => array('label' => 'SvcCancelled',    'picto' => 'status9'),
		);

		$s = isset($statusLabels[$status]) ? $statusLabels[$status] : array('label' => 'Unknown', 'picto' => 'status0');

		if ($mode == 1) {
			return $langs->trans($s['label']);
		}

		return '<span class="badge badge-status'.$status.'">'.$langs->trans($s['label']).'</span>';
	}

	/**
	 * Return label of resolution type
	 *
	 * @param  string $type Resolution type constant
	 * @return string       Translated label
	 */
	public function getResolutionLabel($type = '')
	{
		global $langs;
		$langs->load('warrantysvc');

		if (empty($type)) {
			$type = $this->resolution_type;
		}

		$labels = array(
			self::RESOLUTION_COMPONENT        => 'ResolutionComponent',
			self::RESOLUTION_COMPONENT_RETURN => 'ResolutionComponentReturn',
			self::RESOLUTION_SWAP_CROSS       => 'ResolutionSwapCross',
			self::RESOLUTION_SWAP_WAIT        => 'ResolutionSwapWait',
			self::RESOLUTION_INTERVENTION     => 'ResolutionIntervention',
			self::RESOLUTION_GUIDANCE         => 'ResolutionGuidance',
		);

		return isset($labels[$type]) ? $langs->trans($labels[$type]) : $type;
	}

	/**
	 * Load customer serials from their shipment history for a given product
	 *
	 * @param  int $fk_soc     Thirdparty ID
	 * @param  int $fk_product Product ID (0 = all products)
	 * @return array           Array of serial numbers
	 */
	public function getCustomerSerials($fk_soc, $fk_product = 0)
	{
		$serials = array();

		$sql = "SELECT DISTINCT pl.batch as serial_number, p.ref as product_ref, p.label as product_label";
		$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet_batch edl";
		$sql .= " JOIN ".MAIN_DB_PREFIX."product_lot pl ON pl.rowid = edl.fk_lot";
		$sql .= " JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.rowid = edl.fk_expeditiondet";
		$sql .= " JOIN ".MAIN_DB_PREFIX."expedition e ON e.rowid = ed.fk_expedition";
		$sql .= " JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ed.fk_product";
		$sql .= " WHERE e.fk_soc = ".((int) $fk_soc);
		if ($fk_product > 0) {
			$sql .= " AND ed.fk_product = ".((int) $fk_product);
		}
		$sql .= " ORDER BY pl.batch ASC";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$serials[] = array(
					'serial'        => $obj->serial_number,
					'product_ref'   => $obj->product_ref,
					'product_label' => $obj->product_label,
				);
			}
		}

		return $serials;
	}

	/**
	 * Factory: create a SvcRequest pre-populated from a CRM phone call (actioncomm)
	 *
	 * Reads the actioncomm record to pull: fk_soc, fk_contact, description,
	 * date, and the call record ID. Saves the new SvcRequest as draft and
	 * links back via fk_pbxcall / add_object_linked().
	 *
	 * @param  int  $actioncomm_id  ID of the llx_actioncomm record (phone call)
	 * @param  User $user           User creating the request
	 * @return int                  >0 = new SvcRequest ID, <0 = error
	 */
	public function createFromCall($actioncomm_id, $user)
	{
		if (empty($actioncomm_id)) {
			$this->error = 'MissingActionCommId';
			return -1;
		}

		// Load the actioncomm (CRM event / phone call)
		$sql  = "SELECT rowid, fk_soc, fk_contact, note, datep, fk_user_action";
		$sql .= " FROM ".MAIN_DB_PREFIX."actioncomm";
		$sql .= " WHERE rowid = ".((int) $actioncomm_id);
		$sql .= " AND entity IN (".getEntity('actioncomm').")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		$call = $this->db->fetch_object($resql);
		$this->db->free($resql);

		if (!$call) {
			$this->error = 'ActionCommNotFound';
			return -1;
		}

		// Pre-fill from call
		$this->fk_soc          = $call->fk_soc;
		$this->fk_contact      = $call->fk_contact;
		$this->issue_description = $call->note ? strip_tags($call->note) : '';
		$this->issue_date      = $this->db->jdate($call->datep);
		$this->reported_via    = 'phone';
		$this->fk_pbxcall      = $actioncomm_id;
		$this->resolution_type = 'guidance'; // default — agent will update

		if (empty($this->fk_user_assigned) && !empty($call->fk_user_action)) {
			$this->fk_user_assigned = $call->fk_user_action;
		}

		// Check warranty automatically if configured
		global $conf;
		if (getDolGlobalInt('WARRANTYSVC_AUTO_WARRANTY_CHECK') && !empty($this->serial_number)) {
			$this->checkWarrantyStatus();
		}

		$result = $this->create($user);
		if ($result <= 0) {
			return $result;
		}

		// Link back to the actioncomm via object_linked table
		$this->add_object_linked('actioncomm', $actioncomm_id);

		return $result;
	}

	/**
	 * Count service requests linked to a project.
	 * Used by complete_head_from_modules() for the project tab badge.
	 *
	 * @param  int $projectid Project ID
	 * @return int             Number of service requests
	 */
	public function countForProject($projectid)
	{
		$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE fk_project = ".((int) $projectid);
		$sql .= " AND entity IN (".getEntity('svcrequest').")";
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			return (int) $obj->nb;
		}
		return 0;
	}
}
