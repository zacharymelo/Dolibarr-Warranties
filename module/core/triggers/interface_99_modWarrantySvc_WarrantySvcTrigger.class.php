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

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarrantytype.class.php';


/**
 * Class InterfaceWarrantySvcTrigger
 */
class InterfaceWarrantySvcTrigger extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name        = preg_replace('/^Interface/i', '', get_class($this));
		$this->description = 'Email notifications and automation for Warranty & Service module events';
		$this->version     = '1.0.0';
		$this->picto       = 'email';
		$this->family      = 'warrantysvc';
	}

	/**
	 * Return name of trigger file
	 *
	 * @return string
	 */
	public function getName()
	{
		return 'WarrantySvcTrigger';
	}

	/**
	 * Return description of trigger file
	 *
	 * @return string
	 */
	public function getDesc()
	{
		return $this->description;
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
			case 'WARRANTYSVC_CREATE':
				// Sync claim_count on the linked warranty
				if (!empty($object->fk_warranty)) {
					$this->_syncWarrantyClaimCount($object->fk_warranty);
				}
				dol_syslog('WarrantySvcTrigger: WARRANTYSVC_CREATE ref='.$object->ref, LOG_DEBUG);
				return 1;

			// ------------------------------------------------------------------
			// Service Request validated — notify assigned technician
			// ------------------------------------------------------------------
			case 'WARRANTYSVC_VALIDATE':
				// Sync claim_count — warranty may have been linked during validation
				if (!empty($object->fk_warranty)) {
					$this->_syncWarrantyClaimCount($object->fk_warranty);
				}
				if (!empty($object->fk_user_assigned)) {
					$this->_notifyTechnician($object, $user, $langs, $conf, 'validate');
				}
				return 1;

			// ------------------------------------------------------------------
			// Service Request set in progress
			// ------------------------------------------------------------------
			case 'WARRANTYSVC_SETINPROGRESS':
				if (!empty($object->fk_user_assigned)) {
					$this->_notifyTechnician($object, $user, $langs, $conf, 'inprogress');
				}
				return 1;

			// ------------------------------------------------------------------
			// Awaiting return — notify customer to ship back
			// ------------------------------------------------------------------
			case 'WARRANTYSVC_AWAITRETURN':
				$this->_notifyCustomer($object, $user, $langs, $conf, 'awaitreturn');
				return 1;

			// ------------------------------------------------------------------
			// Resolved — notify customer
			// ------------------------------------------------------------------
			case 'WARRANTYSVC_RESOLVE':
				$this->_notifyCustomer($object, $user, $langs, $conf, 'resolved');
				return 1;

			// ------------------------------------------------------------------
			// Closed
			// ------------------------------------------------------------------
			case 'WARRANTYSVC_CLOSE':
				dol_syslog('WarrantySvcTrigger: WARRANTYSVC_CLOSE ref='.$object->ref, LOG_DEBUG);
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
			// Shipment closed or validated — auto-create warranty records
			// for each shipped serialized product line.
			// Gated by WARRANTYSVC_AUTO_WARRANTY_ON_SHIPMENT (master switch)
			// and WARRANTYSVC_WARRANTY_TRIGGER_EVENT (validate|close|both).
			// ------------------------------------------------------------------
			case 'SHIPPING_CLOSED':
			case 'SHIPPING_VALIDATE':
				if (getDolGlobalInt('WARRANTYSVC_AUTO_WARRANTY_ON_SHIPMENT')) {
					$trigger_setting = getDolGlobalString('WARRANTYSVC_WARRANTY_TRIGGER_EVENT', 'close');
					$fire = false;
					if ($trigger_setting === 'both') {
						$fire = true;
					} elseif ($trigger_setting === 'close' && $action === 'SHIPPING_CLOSED') {
						$fire = true;
					} elseif ($trigger_setting === 'validate' && $action === 'SHIPPING_VALIDATE') {
						$fire = true;
					}
					if ($fire) {
						$this->_autoCreateWarrantiesFromShipment($object, $user, $langs);
					}
				}
				return 1;

			// ------------------------------------------------------------------
			// Order closed (classified as delivered) — auto-create warranty
			// records by iterating linked shipments. Reuses the existing
			// _autoCreateWarrantiesFromShipment() method for each expedition.
			// ------------------------------------------------------------------
			case 'ORDER_CLOSE':
				if (getDolGlobalInt('WARRANTYSVC_AUTO_WARRANTY_ON_ORDER_CLOSE')) {
					$object->fetchObjectLinked('', 'expedition', $object->id, 'commande');
					if (!empty($object->linkedObjects['expedition'])) {
						foreach ($object->linkedObjects['expedition'] as $expedition) {
							// Ensure the expedition has its socid set (needed by _autoCreateWarrantiesFromShipment)
							if (empty($expedition->socid) && !empty($object->socid)) {
								$expedition->socid = $object->socid;
							}
							$this->_autoCreateWarrantiesFromShipment($expedition, $user, $langs);
						}
					}
				}
				return 1;

			// ------------------------------------------------------------------
			// Sales order created — if origin is an SR, auto-link via
			// llx_element_element and store fk_commande on the SR
			// ------------------------------------------------------------------
			case 'ORDER_CREATE':
				if (!empty($object->origin) && $object->origin === 'warrantysvc_svcrequest' && !empty($object->origin_id)) {
					$this->_linkOrderToSvcRequest($object, $user);
				}
				return 0;

			// ------------------------------------------------------------------
			// Customer Return validated — if linked to an SR, auto-advance the SR
			// ------------------------------------------------------------------
			case 'CUSTOMERRETURN_CUSTOMERRETURN_VALIDATE':
				if (!getDolGlobalString('WARRANTYSVC_USE_CUSTOMERRETURN')) {
					return 0;
				}
				$this->_handleCustomerReturnValidated($object, $user);
				return 0;

			default:
				return 0;
		}
	}

	/**
	 * Auto-link a newly created SO to its originating SvcRequest.
	 * Inserts an llx_element_element row and stores fk_commande on the SR.
	 *
	 * @param  Commande $object  Newly created SO
	 * @param  User     $user    Actor
	 * @return void
	 */
	private function _linkOrderToSvcRequest($object, $user)
	{
		$so_id = (int) $object->id;
		$sr_id = (int) $object->origin_id;

		// Bidirectional link in Dolibarr's native element_element table
		// Direction 1: SO as source → SR as target
		$sql1 = "INSERT INTO ".MAIN_DB_PREFIX."element_element (fk_source, sourcetype, fk_target, targettype) VALUES (".$so_id.", 'commande', ".$sr_id.", 'warrantysvc_svcrequest')";
		$this->db->query($sql1); // non-fatal if it fails (e.g. duplicate)
		// Direction 2: SR as source → SO as target
		$sql2 = "INSERT INTO ".MAIN_DB_PREFIX."element_element (fk_source, sourcetype, fk_target, targettype) VALUES (".$sr_id.", 'warrantysvc_svcrequest', ".$so_id.", 'commande')";
		$this->db->query($sql2); // non-fatal if it fails (e.g. duplicate)

		// Store on the SR so the action panel can display the link directly
		require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcrequest.class.php';
		$sr = new SvcRequest($this->db);
		if ($sr->fetch($sr_id) > 0 && empty($sr->fk_commande)) {
			$sr->fk_commande = $so_id;
			$sr->update($user);
		}

		dol_syslog('WarrantySvcTrigger: linked SO '.$so_id.' to SvcRequest '.$sr_id, LOG_DEBUG);
	}

	/**
	 * When a Customer Return is validated, check if it was linked to an SR.
	 * If so, set date_return_received and advance the SR status.
	 *
	 * @param  object $object  The validated CustomerReturn
	 * @param  User   $user    Actor
	 * @return void
	 */
	private function _handleCustomerReturnValidated($object, $user)
	{
		$cr_id = (int) $object->id;

		// Look for an SR linked as source → this customerreturn as target
		$sql = "SELECT fk_source FROM ".MAIN_DB_PREFIX."element_element WHERE fk_target = ".$cr_id." AND targettype IN ('customerreturn', 'customerreturn_customerreturn') AND sourcetype = 'warrantysvc_svcrequest' LIMIT 1";
		$res = $this->db->query($sql);
		if (!$res) {
			return;
		}
		$row = $this->db->fetch_object($res);
		if (!$row || empty($row->fk_source)) {
			return;
		}

		$sr_id = (int) $row->fk_source;
		require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcrequest.class.php';
		$sr = new SvcRequest($this->db);
		if ($sr->fetch($sr_id) <= 0) {
			return;
		}

		// Update return-received date
		$sr->date_return_received = dol_now();
		$sr->update($user);

		// Advance SR: if awaiting return → in progress; if in progress → resolve
		if ($sr->status == SvcRequest::STATUS_AWAIT_RETURN) {
			$sr->setInProgress($user);
		}

		dol_syslog('WarrantySvcTrigger: CustomerReturn '.$cr_id.' validated, updated SR '.$sr_id, LOG_DEBUG);
	}

	/**
	 * Sync claim_count on a warranty record from actual linked service requests.
	 * Idempotent — safe to call from any trigger without risk of double-counting.
	 *
	 * @param  int  $fk_warranty  Warranty ID
	 * @return void
	 */
	private function _syncWarrantyClaimCount($fk_warranty)
	{
		$fk = (int) $fk_warranty;
		$sql = "UPDATE ".MAIN_DB_PREFIX."svc_warranty SET claim_count = (SELECT COUNT(*) FROM ".MAIN_DB_PREFIX."svc_request WHERE fk_warranty = ".$fk.") WHERE rowid = ".$fk;
		$this->db->query($sql);
		dol_syslog('WarrantySvcTrigger: synced claim_count on warranty '.$fk, LOG_DEBUG);
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
		$lines[] = $langs->trans('SvcSerialNumber').': '.($object->serial_number ?? '-');
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
	 * Auto-create SvcWarranty records for serialized products
	 * in a closed shipment (SHIPPING_CLOSED trigger).
	 *
	 * For each expedition line with a serial/lot (llx_expeditiondet_batch),
	 * check if a warranty already exists for that serial. If not, create one
	 * using the product-specific warranty type default (if configured),
	 * falling back to the first active warranty type, with coverage_terms
	 * and exclusions populated from the resolved type.
	 *
	 * @param  Expedition $object Closed shipment object
	 * @param  User       $user   Actor
	 * @param  Translate  $langs  Lang
	 * @return void
	 */
	private function _autoCreateWarrantiesFromShipment($object, $user, $langs)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarranty.class.php';
		require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcwarrantytype.class.php';

		$global_coverage_days = getDolGlobalInt('WARRANTYSVC_DEFAULT_COVERAGE_DAYS', 365);

		// Pre-load all active warranty types (used for coverage_terms/exclusions lookup)
		$all_types = SvcWarrantyType::fetchAllForForm($this->db);

		// Resolve order ID from shipment origin
		$order_id = 0;
		if (!empty($object->origin) && $object->origin == 'commande' && !empty($object->origin_id)) {
			$order_id = (int) $object->origin_id;
		}

		// Fetch serialized lines for this shipment
		// llx_expeditiondet_batch.batch is the serial/lot string directly
		$sql  = "SELECT edl.batch as serial_number, ed.fk_product";
		$sql .= " FROM ".MAIN_DB_PREFIX."expeditiondet_batch edl";
		$sql .= " JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON ed.rowid = edl.fk_expeditiondet";
		$sql .= " WHERE ed.fk_expedition = ".((int) $object->id);
		$sql .= " AND edl.batch IS NOT NULL AND edl.batch != ''";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog('WarrantySvcTrigger: SHIPPING_CLOSED query failed: '.$this->db->lasterror(), LOG_WARNING);
			return;
		}

		while ($line = $this->db->fetch_object($resql)) {
			// Skip if a warranty already exists for this serial ON THIS SHIPMENT
			$sql_dup = "SELECT rowid FROM ".MAIN_DB_PREFIX."svc_warranty";
			$sql_dup .= " WHERE serial_number = '".$this->db->escape($line->serial_number)."'";
			$sql_dup .= " AND fk_expedition = ".((int) $object->id);
			$sql_dup .= " AND entity = ".((int) $conf->entity);
			$res_dup = $this->db->query($sql_dup);
			if ($res_dup && $this->db->fetch_object($res_dup)) {
				continue; // warranty already exists for this serial on this shipment
			}

			// Auto-void active warranties for this serial held by a DIFFERENT customer
			// (unit was returned and resold). Same-customer warranties are kept (sub-coverage).
			$sql_void = "SELECT rowid, fk_soc FROM ".MAIN_DB_PREFIX."svc_warranty";
			$sql_void .= " WHERE serial_number = '".$this->db->escape($line->serial_number)."'";
			$sql_void .= " AND status = 'active'";
			$sql_void .= " AND entity = ".((int) $conf->entity);
			$res_void = $this->db->query($sql_void);
			if ($res_void) {
				while ($row_void = $this->db->fetch_object($res_void)) {
					if ((int) $row_void->fk_soc !== (int) $object->socid) {
						$this->db->query("UPDATE ".MAIN_DB_PREFIX."svc_warranty SET status = 'voided' WHERE rowid = ".((int) $row_void->rowid));
						dol_syslog('WarrantySvcTrigger: voided warranty '.$row_void->rowid.' for serial '.$line->serial_number.' (resold to different customer)', LOG_INFO);
					}
				}
			}

			// ---- Resolve warranty type: product default > first active type > 'standard' ----
			$type_code = '';
			$product_coverage_days = 0;

			// 1. Check product-specific warranty default
			$sql_pd  = "SELECT warranty_type, coverage_days FROM ".MAIN_DB_PREFIX."warrantysvc_product_default";
			$sql_pd .= " WHERE fk_product = ".((int) $line->fk_product)." AND entity = ".((int) $conf->entity);
			$res_pd  = $this->db->query($sql_pd);
			$row_pd  = ($res_pd) ? $this->db->fetch_object($res_pd) : null;

			// 2. If not found, check parent product (variant cascade)
			if (!$row_pd && isModEnabled('variants')) {
				$sql_par  = "SELECT fk_product_parent FROM ".MAIN_DB_PREFIX."product_attribute_combination";
				$sql_par .= " WHERE fk_product_child = ".((int) $line->fk_product);
				$sql_par .= " AND entity IN (".getEntity('product').")";
				$res_par  = $this->db->query($sql_par);
				if ($res_par && ($row_par = $this->db->fetch_object($res_par))) {
					$sql_pd2  = "SELECT warranty_type, coverage_days FROM ".MAIN_DB_PREFIX."warrantysvc_product_default";
					$sql_pd2 .= " WHERE fk_product = ".((int) $row_par->fk_product_parent)." AND entity = ".((int) $conf->entity);
					$res_pd2  = $this->db->query($sql_pd2);
					$row_pd   = ($res_pd2) ? $this->db->fetch_object($res_pd2) : null;
				}
			}

			if ($row_pd && !empty($row_pd->warranty_type)) {
				$type_code = $row_pd->warranty_type;
				$product_coverage_days = ($row_pd->coverage_days > 0) ? (int) $row_pd->coverage_days : 0;
			}

			// 3. Find the matching type object for coverage_terms and exclusions
			$matched_type = null;
			if ($all_types) {
				foreach ($all_types as $wt) {
					if ($type_code && $wt->code === $type_code) {
						$matched_type = $wt;
						break;
					}
				}
				// Fallback to first active type if no product-specific match
				if (!$matched_type) {
					$matched_type = $all_types[0];
					$type_code = $matched_type->code;
				}
			}

			// ---- Build warranty record ----
			$warranty                  = new SvcWarranty($this->db);
			$warranty->serial_number   = $line->serial_number;
			$warranty->fk_product      = $line->fk_product;
			$warranty->fk_soc          = $object->socid;
			$warranty->fk_expedition   = $object->id;
			$warranty->fk_commande     = $order_id;
			$warranty->warranty_type   = $type_code ?: 'standard';
			$warranty->coverage_terms  = $matched_type ? $matched_type->coverage_terms : '';
			$warranty->exclusions      = $matched_type ? $matched_type->exclusions : '';
			$warranty->start_date      = dol_now();

			// Coverage days priority: product default > type default > global config
			if ($product_coverage_days > 0) {
				$warranty->coverage_days = $product_coverage_days;
			} elseif ($matched_type && $matched_type->default_coverage_days > 0) {
				$warranty->coverage_days = (int) $matched_type->default_coverage_days;
			} else {
				$warranty->coverage_days = $global_coverage_days;
			}
			// expiry_date auto-computed in create() from coverage_days + start_date

			$result = $warranty->create($user);
			if ($result > 0) {
				// Link warranty to shipment and order in element_element
				if ($warranty->fk_expedition > 0) {
					$warranty->add_object_linked('expedition', $warranty->fk_expedition);
				}
				if ($warranty->fk_commande > 0) {
					$warranty->add_object_linked('commande', $warranty->fk_commande);
					// Discover and link invoices tied to this order
					$sql_inv = "SELECT fk_target FROM ".MAIN_DB_PREFIX."element_element WHERE fk_source = ".((int) $warranty->fk_commande)." AND sourcetype = 'commande' AND targettype = 'facture'";
					$res_inv = $this->db->query($sql_inv);
					if ($res_inv) {
						while ($row_inv = $this->db->fetch_object($res_inv)) {
							$warranty->add_object_linked('facture', (int) $row_inv->fk_target);
						}
					}
				}
			} else {
				dol_syslog(
					'WarrantySvcTrigger: failed to create warranty for serial '.$line->serial_number.': '.$warranty->error,
					LOG_WARNING
				);
			}
		}

		$this->db->free($resql);
	}
}
