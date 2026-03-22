<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    core/triggers/interface_99_modWarrantySvc_WarrantySvcTrigger.class.php
 * \ingroup warrantysvc
 * \brief   Email notification trigger for Warranty & Service module events
 *
 * Fires on SVCREQUEST_* and SVCWARRANTY_* trigger codes produced by
 * SvcRequest::call_trigger() / SvcWarranty::call_trigger().
 * Also listens on FICHINTER_CLOSE to update SvcServiceLog.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarrantytype.class.php';


/**
 * Class WarrantySvcTrigger
 */
class interface_99_modWarrantySvc_WarrantySvcTrigger extends CommonHookActions
{
	/** @var string Trigger name */
	public $name        = 'WarrantySvcTrigger';

	/** @var string Description */
	public $description = 'Email notifications for Warranty & Service module events';

	/** @var string Version */
	public $version     = '1.0';

	/** @var string Family */
	public $picto       = 'email';

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
	 * Run trigger
	 *
	 * @param  string    $action  Event code
	 * @param  mixed     $object  Object
	 * @param  User      $user    User
	 * @param  Translate $langs   Language
	 * @param  Conf      $conf    Config
	 * @return int                0=nothing done, 1=OK, <0=error
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('warrantysvc')) {
			return 0;
		}

		$langs->loadLangs(array('warrantysvc@warrantysvc', 'mails'));

		switch ($action) {

			// ------------------------------------------------------------------
			// Service Request: new record created (draft)
			// ------------------------------------------------------------------
			case 'SVCREQUEST_CREATE':
				// No outbound email on creation — just log the event internally
				dol_syslog('WarrantySvcTrigger: SVCREQUEST_CREATE ref='.$object->ref, LOG_DEBUG);
				return 1;

			// ------------------------------------------------------------------
			// Service Request validated — notify assigned technician
			// ------------------------------------------------------------------
			case 'SVCREQUEST_VALIDATE':
				if (!empty($object->fk_user_assigned)) {
					$this->_notifyTechnician($object, $user, $langs, $conf, 'validate');
				}
				return 1;

			// ------------------------------------------------------------------
			// Service Request set in progress
			// ------------------------------------------------------------------
			case 'SVCREQUEST_SETINPROGRESS':
				if (!empty($object->fk_user_assigned)) {
					$this->_notifyTechnician($object, $user, $langs, $conf, 'inprogress');
				}
				return 1;

			// ------------------------------------------------------------------
			// Awaiting return — notify customer to ship back
			// ------------------------------------------------------------------
			case 'SVCREQUEST_AWAITRETURN':
				$this->_notifyCustomer($object, $user, $langs, $conf, 'awaitreturn');
				return 1;

			// ------------------------------------------------------------------
			// Resolved — notify customer
			// ------------------------------------------------------------------
			case 'SVCREQUEST_RESOLVE':
				$this->_notifyCustomer($object, $user, $langs, $conf, 'resolved');
				return 1;

			// ------------------------------------------------------------------
			// Closed
			// ------------------------------------------------------------------
			case 'SVCREQUEST_CLOSE':
				dol_syslog('WarrantySvcTrigger: SVCREQUEST_CLOSE ref='.$object->ref, LOG_DEBUG);
				return 1;

			// ------------------------------------------------------------------
			// Fichinter (Intervention) closed — update SvcServiceLog
			// ------------------------------------------------------------------
			case 'FICHINTER_CLOSE':
				$this->_syncServiceLogFromIntervention($object, $user);
				return 1;

			// ------------------------------------------------------------------
			// Warranty created — notify customer (if configured)
			// ------------------------------------------------------------------
			case 'SVCWARRANTY_CREATE':
				if (getDolGlobalInt('WARRANTYSVC_NOTIFY_WARRANTY_CREATED')) {
					$this->_notifyWarrantyCreated($object, $user, $langs, $conf);
				}
				return 1;

			// ------------------------------------------------------------------
			// Shipment validated — auto-create warranty records
			// for each shipped serialized product line (gated by config flag)
			// ------------------------------------------------------------------
			case 'SHIPPING_VALIDATE':
				if (getDolGlobalInt('WARRANTYSVC_AUTO_WARRANTY_ON_SHIPMENT')) {
					$this->_autoCreateWarrantiesFromShipment($object, $user, $langs);
				}
				return 1;

			default:
				return 0;
		}
	}


	// -----------------------------------------------------------------
	// Private notification helpers
	// -----------------------------------------------------------------

	/**
	 * Send notification email to the assigned technician
	 *
	 * @param  SvcRequest $object Object
	 * @param  User       $user   Actor
	 * @param  Translate  $langs  Lang
	 * @param  Conf       $conf   Config
	 * @param  string     $event  'validate'|'inprogress'
	 * @return void
	 */
	private function _notifyTechnician($object, $user, $langs, $conf, $event)
	{
		$tech = new User($this->db);
		if ($tech->fetch($object->fk_user_assigned) <= 0 || empty($tech->email)) {
			return;
		}

		$from_email = $this->_getSenderEmail($conf);
		if (empty($from_email)) {
			return;
		}

		if ($event == 'validate') {
			$subject = $langs->trans('NotifTechValidateSubject', $object->ref);
			$body    = $langs->trans('NotifTechValidateBody', $object->ref, $tech->getFullName($langs));
		} else {
			$subject = $langs->trans('NotifTechInProgressSubject', $object->ref);
			$body    = $langs->trans('NotifTechInProgressBody', $object->ref, $tech->getFullName($langs));
		}

		$body .= $this->_requestSummary($object, $langs);

		$mail = new CMailFile($subject, $tech->email, $from_email, $body);
		if ($mail->sendfile() <= 0) {
			dol_syslog('WarrantySvcTrigger: failed to send tech notification: '.$mail->error, LOG_WARNING);
		}
	}

	/**
	 * Send notification email to the customer (thirdparty email)
	 *
	 * @param  SvcRequest $object Object
	 * @param  User       $user   Actor
	 * @param  Translate  $langs  Lang
	 * @param  Conf       $conf   Config
	 * @param  string     $event  'awaitreturn'|'resolved'
	 * @return void
	 */
	private function _notifyCustomer($object, $user, $langs, $conf, $event)
	{
		$soc = new Societe($this->db);
		if ($soc->fetch($object->fk_soc) <= 0 || empty($soc->email)) {
			return;
		}

		$from_email = $this->_getSenderEmail($conf);
		if (empty($from_email)) {
			return;
		}

		if ($event == 'awaitreturn') {
			$subject = $langs->trans('NotifCustAwaitReturnSubject', $object->ref);
			$body    = $langs->trans('NotifCustAwaitReturnBody', $object->ref, $object->serial_number ?? '');
			if (!empty($object->outbound_carrier)) {
				$body .= "\n\n".$langs->trans('OutboundCarrier').': '.$object->outbound_carrier;
			}
			if (!empty($object->outbound_tracking)) {
				$body .= "\n".$langs->trans('OutboundTracking').': '.$object->outbound_tracking;
			}
		} else {
			$subject = $langs->trans('NotifCustResolvedSubject', $object->ref);
			$body    = $langs->trans('NotifCustResolvedBody', $object->ref);
			if (!empty($object->resolution_notes)) {
				$body .= "\n\n".strip_tags(str_replace('<br>', "\n", $object->resolution_notes));
			}
		}

		$mail = new CMailFile($subject, $soc->email, $from_email, $body);
		if ($mail->sendfile() <= 0) {
			dol_syslog('WarrantySvcTrigger: failed to send customer notification: '.$mail->error, LOG_WARNING);
		}
	}

	/**
	 * Notify customer that a new warranty record was created for their unit
	 *
	 * @param  SvcWarranty $object Warranty object
	 * @param  User        $user   Actor
	 * @param  Translate   $langs  Lang
	 * @param  Conf        $conf   Config
	 * @return void
	 */
	private function _notifyWarrantyCreated($object, $user, $langs, $conf)
	{
		$soc = new Societe($this->db);
		if ($soc->fetch($object->fk_soc) <= 0 || empty($soc->email)) {
			return;
		}

		$from_email = $this->_getSenderEmail($conf);
		if (empty($from_email)) {
			return;
		}

		$expiry_label = $object->expiry_date ? dol_print_date($object->expiry_date, 'day') : $langs->trans('NoExpiryDate');

		$subject = $langs->trans('NotifWarrantyCreatedSubject', $object->ref);
		$body    = $langs->trans('NotifWarrantyCreatedBody',
			$object->ref,
			$object->serial_number,
			$expiry_label
		);

		$mail = new CMailFile($subject, $soc->email, $from_email, $body);
		if ($mail->sendfile() <= 0) {
			dol_syslog('WarrantySvcTrigger: failed to send warranty notification: '.$mail->error, LOG_WARNING);
		}
	}

	/**
	 * On FICHINTER_CLOSE: find any SvcRequest linked to this intervention,
	 * then upsert SvcServiceLog with updated service hours and count.
	 *
	 * @param  Fichinter $object Intervention
	 * @param  User      $user   Actor
	 * @return void
	 */
	private function _syncServiceLogFromIntervention($object, $user)
	{
		// Find the SvcRequest that references this intervention
		$sql  = "SELECT rowid, serial_number, fk_product FROM ".MAIN_DB_PREFIX."svc_request";
		$sql .= " WHERE fk_intervention = ".((int) $object->id);
		$sql .= " AND entity IN (".getEntity('svcrequest').")";
		$sql .= " LIMIT 1";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		if (!$obj || empty($obj->serial_number)) {
			return;
		}

		require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcservicelog.class.php';

		$log = new SvcServiceLog($this->db);
		$log->fetchBySerial($obj->serial_number);

		// Accumulate hours from intervention lines
		$total_hours = 0;
		if (!empty($object->lines)) {
			foreach ($object->lines as $line) {
				$total_hours += (float) ($line->duree ?? 0) / 3600; // duree is in seconds
			}
		}

		$log->serial_number      = $obj->serial_number;
		$log->fk_product         = $obj->fk_product;
		$log->service_hours      = $log->service_hours + $total_hours;
		$log->service_count      = $log->service_count + 1;
		$log->last_service_date  = dol_now();
		$log->condition_score    = $log->computeConditionScore();

		$log->save($user);
	}

	/**
	 * Build a short text summary of a SvcRequest for email bodies
	 *
	 * @param  SvcRequest $object Object
	 * @param  Translate  $langs  Lang
	 * @return string             Multi-line summary
	 */
	private function _requestSummary($object, $langs)
	{
		$lines   = array();
		$lines[] = '';
		$lines[] = $langs->trans('Ref').': '.$object->ref;
		$lines[] = $langs->trans('SerialNumber').': '.($object->serial_number ?? '-');
		$lines[] = $langs->trans('ResolutionType').': '.svcrequest_resolution_label($object->resolution_type);
		return "\n".implode("\n", $lines);
	}

	/**
	 * Return the configured sender email address
	 *
	 * @param  Conf $conf Dolibarr config
	 * @return string     Email or empty string
	 */
	private function _getSenderEmail($conf)
	{
		$email = getDolGlobalString('MAIN_MAIL_FROM_EMAIL', getDolGlobalString('MAIN_INFO_SOCIETE_MAIL', ''));
		return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
	}

	/**
	 * STRETCH #14: Auto-create SvcWarranty records for serialized products
	 * in a validated shipment (EXPEDITION_VALIDATE trigger).
	 *
	 * For each expedition line with a serial/lot (llx_expeditiondet_batch),
	 * check if a warranty already exists for that serial. If not, create one
	 * using the default coverage from WARRANTYSVC_DEFAULT_COVERAGE_DAYS (default 12).
	 *
	 * @param  Expedition $object Validated shipment object
	 * @param  User       $user   Actor
	 * @param  Translate  $langs  Lang
	 * @return void
	 */
	private function _autoCreateWarrantiesFromShipment($object, $user, $langs)
	{
		require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarranty.class.php';

		$coverage_days = getDolGlobalInt('WARRANTYSVC_DEFAULT_COVERAGE_DAYS', 365);

		// Fetch serialized lines for this shipment
		// llx_expeditiondet_batch.batch is the serial/lot string directly
		$sql  = "SELECT edl.batch as serial_number, ed.fk_product";
		$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet_batch edl";
		$sql .= " JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.rowid = edl.fk_expeditiondet";
		$sql .= " WHERE ed.fk_expedition = ".((int) $object->id);
		$sql .= " AND edl.batch IS NOT NULL AND edl.batch != ''";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog('WarrantySvcTrigger: SHIPPING_VALIDATE query failed: '.$this->db->lasterror(), LOG_WARNING);
			return;
		}

		while ($line = $this->db->fetch_object($resql)) {
			// Skip if a warranty already exists for this serial
			$existing = new SvcWarranty($this->db);
			if ($existing->fetchBySerial($line->serial_number) > 0) {
				continue; // already covered
			}

			$warranty                  = new SvcWarranty($this->db);
			$warranty->serial_number   = $line->serial_number;
			$warranty->fk_product      = $line->fk_product;
			$warranty->fk_soc          = $object->socid;
			$warranty->fk_expedition   = $object->id;
			// Use the first active warranty type (by position) as the default, falling back to 'standard'
			$default_types = SvcWarrantyType::fetchAllForForm($this->db);
			$warranty->warranty_type = $default_types ? $default_types[0]->code : 'standard';
			$warranty->start_date      = dol_now();
			$warranty->coverage_days = $coverage_days;
			// expiry_date auto-computed in create() from coverage_days + start_date

			$result = $warranty->create($user);
			if ($result < 0) {
				dol_syslog(
					'WarrantySvcTrigger: failed to create warranty for serial '.$line->serial_number.': '.$warranty->error,
					LOG_WARNING
				);
			}
		}

		$this->db->free($resql);
	}
}
