<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    core/modules/warrantysvc/mod_warrantysvc_standard.php
 * \ingroup warrantysvc
 * \brief   Standard numbering model for Service Requests: SRQ-YYYYMMDD-0001
 */

require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/core/modules/warrantysvc/modules_warrantysvc.php';

/**
 * Class to number Service Requests with format SRQ-YYYYMMDD-NNNN
 */
class mod_warrantysvc_standard extends ModeleNumRefWarrantySvc
{
	/** @var string Version */
	public $version = '1.0.0';

	/** @var string Model name */
	public $name = 'standard';

	/** @var string Prefix */
	public $prefix = 'SRQ';

	/** @var string Error */
	public $error = '';

	/**
	 * Return description of this numbering model
	 *
	 * @param  Translate $langs Lang object
	 * @return string           Description
	 */
	public function info($langs)
	{
		return 'Service Request standard numbering: '.$this->prefix.'-YYYYMMDD-NNNN';
	}

	/**
	 * Return an example of numbering
	 *
	 * @return string Example ref
	 */
	public function getExample()
	{
		return $this->prefix.'-20260319-0001';
	}

	/**
	 * Check whether existing refs conflict with this model
	 *
	 * @param  object $object Object (unused)
	 * @return bool           true if no conflict
	 */
	public function canBeActivated($object = null)
	{
		return true;
	}

	/**
	 * Return next free reference value
	 *
	 * @param  Societe|string    $objsoc  Thirdparty (unused)
	 * @param  SvcRequest|string $object  SvcRequest object
	 * @return string|int                 Next ref, or <=0 on error
	 */
	public function getNextValue($objsoc = '', $object = '')
	{
		global $db, $conf;

		$date = !empty($object->issue_date) ? $object->issue_date : dol_now();
		$ymd  = dol_print_date($date, '%Y%m%d');

		// Position of the counter within the ref string after "SRQ-YYYYMMDD-"
		$prefix_len = strlen($this->prefix) + 1 + 8 + 1; // "SRQ-" + "YYYYMMDD" + "-"

		$sql  = "SELECT MAX(CAST(SUBSTRING(ref FROM ".($prefix_len + 1).") AS SIGNED)) as max";
		$sql .= " FROM ".MAIN_DB_PREFIX."svc_request";
		$sql .= " WHERE ref LIKE '".$db->escape($this->prefix)."-".$ymd."-%'";
		$sql .= " AND entity = ".$conf->entity;

		$max   = 0;
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$max = intval($obj->max);
			}
		} else {
			$this->error = $db->lasterror();
			return -1;
		}

		return $this->prefix.'-'.$ymd.'-'.sprintf('%04d', $max + 1);
	}
}
