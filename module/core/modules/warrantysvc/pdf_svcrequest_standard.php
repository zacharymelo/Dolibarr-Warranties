<?php
/* Copyright (C) 2026 DPG Supply */

/**
 * \file    core/modules/warrantysvc/pdf_svcrequest_standard.php
 * \ingroup warrantysvc
 * \brief   Standard PDF model for Service Request authorization slip
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcrequest.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/class/svcrequestline.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/lib/warrantysvc.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/warrantysvc/core/modules/warrantysvc/modules_warrantysvc.php';


/**
 * Class to generate PDF for a Service Request (authorization slip)
 */
class pdf_svcrequest_standard extends ModelePDFWarrantySvc
{
	/** @var DoliDB Database handler */
	public $db;

	/** @var string Model name */
	public $name = 'standard';

	/** @var string Model description */
	public $description = 'Standard Service Request authorization slip';

	/** @var int Version */
	public $version = 1;

	/** @var string Dolibarr version compatibility */
	public $phpmin = array(7, 0);

	/** @var array List of page formats */
	public $type = 'pdf';

	/** @var float Left margin (mm) */
	public $marge_gauche;

	/** @var float Right margin (mm) */
	public $marge_droite;

	/** @var float Top margin (mm) */
	public $marge_haute;

	/** @var float Bottom margin (mm) */
	public $marge_basse;

	/** @var CommonHookActions Hook handler */
	public $hookhandler;

	/** @var Translate Lang object */
	public $outputlangs;


	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs, $mysoc;

		$this->db = $db;

		$this->name              = 'standard';
		$this->description       = 'Standard Service Request authorization slip';
		$this->page_orientation  = 'P';
		$this->type              = 'pdf';
		$this->page_format       = pdf_getFormat();
		$this->marge_gauche      = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite      = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute       = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse       = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->option_logo       = 1;
		$this->option_tva        = 1;
		$this->option_draft_watermark = 1;
	}


	/**
	 * Generate the PDF file for a SvcRequest
	 *
	 * @param  SvcRequest $object          Service request object
	 * @param  Translate  $outputlangs     Language for output
	 * @param  string     $srctemplatepath Unused (ODT only)
	 * @param  int        $hidedetails     Hide line details
	 * @param  int        $hidedesc        Hide description
	 * @param  int        $hideref         Hide ref
	 * @return int                         1 if OK, <=0 if KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $conf, $langs, $hookmanager, $mysoc;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$outputlangs->loadLangs(array('main', 'dict', 'companies', 'bills', 'warrantysvc@warrantysvc'));

		// Fetch lines if not already loaded
		if (empty($object->lines)) {
			$object->fetchLines();
		}

		$dir = $conf->warrantysvc->multidir_output[$object->entity] ?? $conf->warrantysvc->dir_output;
		if (empty($dir)) {
			$this->error = 'warrantysvc output dir not configured';
			return -1;
		}

		if (!file_exists($dir)) {
			if (dol_mkdir($dir) < 0) {
				$this->error = $langs->trans('ErrorCanNotCreateDir', $dir);
				return -1;
			}
		}

		$filename = 'SvcRequest_'.$object->ref.'.pdf';
		$filepath = $dir.'/'.$filename;

		// Instantiate PDF
		$pdf = pdf_getInstance($this->page_format, 'mm', $this->page_orientation);

		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}

		$pdf->SetAutoPageBreak(1, 0);

		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}

		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetSubject($outputlangs->transnoentities('ServiceRequest'));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($mysoc->name));
		$pdf->SetKeywords($object->ref);

		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);

		$pdf->AddPage();

		$tab_top        = 90;    // Y below header block
		$tab_top_newpage = 20;
		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$heightrow      = 5;     // row height mm

		// ---- PAGE WIDTH ----
		$pagewidth = $this->page_format == 'letter' ? 216 : 210;
		$usablewidth = $pagewidth - $this->marge_gauche - $this->marge_droite;

		// ---- HEADER ----
		$this->_pagehead($pdf, $object, 1, $outputlangs);

		// ---- INFO BLOCK ----
		$curY = $tab_top;
		pdf_writeLinkedObjects($pdf, $object, $outputlangs, $curY, $default_font_size, 'small');

		// Two-column info table
		$colw = $usablewidth / 2;
		$pdf->SetFont('', '', $default_font_size - 1);

		// Left column: Customer / Product / Serial / Issue Date
		$leftcol = array(
			array($outputlangs->transnoentities('Customer'),     $this->_getCustomerName($object)),
			array($outputlangs->transnoentities('Product'),      $this->_getProductLabel($object)),
			array($outputlangs->transnoentities('SerialNumber'), $object->serial_number),
			array($outputlangs->transnoentities('IssueDate'),    dol_print_date($object->issue_date, 'day', false, $outputlangs)),
		);
		// Right column: Resolution / Warranty / Assigned / Status
		$rightcol = array(
			array($outputlangs->transnoentities('ResolutionType'), svcrequest_resolution_label($object->resolution_type)),
			array($outputlangs->transnoentities('WarrantyStatus'),  $object->warranty_status ? $outputlangs->transnoentities(ucfirst($object->warranty_status)) : $outputlangs->transnoentities('NoCoverage')),
			array($outputlangs->transnoentities('AssignedTo'),      $this->_getAssignedUser($object)),
			array($outputlangs->transnoentities('Status'),          svcrequest_status_badge($object->status, 1)),
		);

		$labelw = 42;
		$valuew = $colw - $labelw - 2;

		for ($i = 0; $i < count($leftcol); $i++) {
			// Left cell label
			$pdf->SetXY($this->marge_gauche, $curY);
			$pdf->SetFont('', 'B', $default_font_size - 1);
			$pdf->Cell($labelw, $heightrow, $outputlangs->convToOutputCharset($leftcol[$i][0]).':', 0, 0, 'L');
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->Cell($valuew, $heightrow, $outputlangs->convToOutputCharset($leftcol[$i][1]), 0, 0, 'L');

			// Right cell label
			$pdf->SetX($this->marge_gauche + $colw + 2);
			$pdf->SetFont('', 'B', $default_font_size - 1);
			$pdf->Cell($labelw, $heightrow, $outputlangs->convToOutputCharset($rightcol[$i][0]).':', 0, 0, 'L');
			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->Cell($valuew, $heightrow, $outputlangs->convToOutputCharset($rightcol[$i][1]), 0, 1, 'L');

			$curY += $heightrow;
		}

		$curY += 3;

		// Horizontal rule
		$pdf->SetDrawColor(200, 200, 200);
		$pdf->Line($this->marge_gauche, $curY, $pagewidth - $this->marge_droite, $curY);
		$curY += 4;

		// ---- ISSUE DESCRIPTION ----
		if (!empty($object->issue_description) && !$hidedesc) {
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->SetXY($this->marge_gauche, $curY);
			$pdf->Cell($usablewidth, $heightrow, $outputlangs->transnoentities('IssueDescription'), 0, 1, 'L');
			$curY += $heightrow;

			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetXY($this->marge_gauche, $curY);
			$desctext = strip_tags(str_replace('<br>', "\n", $object->issue_description));
			$nblines  = $pdf->getNumLines($desctext, $usablewidth);
			$pdf->MultiCell($usablewidth, $heightrow, $outputlangs->convToOutputCharset($desctext), 0, 'L', false, 1);
			$curY = $pdf->GetY() + 3;
		}

		// ---- COMPONENT LINES TABLE ----
		if (!empty($object->lines) && !$hidedetails) {
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->SetXY($this->marge_gauche, $curY);
			$pdf->Cell($usablewidth, $heightrow, $outputlangs->transnoentities('ComponentLines'), 0, 1, 'L');
			$curY += $heightrow;

			// Table header
			$col_desc = $usablewidth * 0.50;
			$col_type = $usablewidth * 0.25;
			$col_qty  = $usablewidth * 0.12;
			$col_ship = $usablewidth * 0.13;

			$pdf->SetFillColor(230, 230, 230);
			$pdf->SetFont('', 'B', $default_font_size - 1);
			$pdf->SetXY($this->marge_gauche, $curY);
			$pdf->Cell($col_desc, $heightrow, $outputlangs->transnoentities('Product'), 1, 0, 'L', 1);
			$pdf->Cell($col_type, $heightrow, $outputlangs->transnoentities('LineType'), 1, 0, 'C', 1);
			$pdf->Cell($col_qty,  $heightrow, $outputlangs->transnoentities('Qty'),      1, 0, 'C', 1);
			$pdf->Cell($col_ship, $heightrow, $outputlangs->transnoentities('Shipped'),  1, 1, 'C', 1);
			$curY += $heightrow;

			$pdf->SetFont('', '', $default_font_size - 1);
			$fill = false;
			foreach ($object->lines as $line) {
				if ($pdf->GetY() > (297 - $this->marge_basse - 30)) {
					$pdf->AddPage();
					$this->_pagehead($pdf, $object, 0, $outputlangs);
					$curY = $tab_top_newpage;
					$pdf->SetXY($this->marge_gauche, $curY);
				}

				$product_label = $this->_getLineProductLabel($line);
				$line_type     = $this->_getLineTypeLabel($line->line_type, $outputlangs);

				$pdf->SetXY($this->marge_gauche, $pdf->GetY());
				$pdf->Cell($col_desc, $heightrow, $outputlangs->convToOutputCharset($product_label), 1, 0, 'L', $fill);
				$pdf->Cell($col_type, $heightrow, $outputlangs->convToOutputCharset($line_type),     1, 0, 'C', $fill);
				$pdf->Cell($col_qty,  $heightrow, (int) $line->qty,                                  1, 0, 'C', $fill);
				$pdf->Cell($col_ship, $heightrow, $line->shipped ? $outputlangs->transnoentities('Yes') : $outputlangs->transnoentities('No'), 1, 1, 'C', $fill);

				$fill = !$fill;
			}

			$curY = $pdf->GetY() + 4;
		}

		// ---- TRACKING INFO ----
		$has_tracking = ($object->outbound_tracking || $object->return_tracking || $object->serial_out || $object->serial_in);
		if ($has_tracking) {
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->SetXY($this->marge_gauche, $pdf->GetY());
			$pdf->Cell($usablewidth, $heightrow, $outputlangs->transnoentities('TrackingInfo'), 0, 1, 'L');

			$tracking_rows = array();
			if ($object->serial_out)        $tracking_rows[] = array($outputlangs->transnoentities('SerialOut'),        $object->serial_out);
			if ($object->serial_in)         $tracking_rows[] = array($outputlangs->transnoentities('SerialIn'),         $object->serial_in);
			if ($object->outbound_carrier)  $tracking_rows[] = array($outputlangs->transnoentities('OutboundCarrier'),  $object->outbound_carrier);
			if ($object->outbound_tracking) $tracking_rows[] = array($outputlangs->transnoentities('OutboundTracking'), $object->outbound_tracking);
			if ($object->return_carrier)    $tracking_rows[] = array($outputlangs->transnoentities('ReturnCarrier'),    $object->return_carrier);
			if ($object->return_tracking)   $tracking_rows[] = array($outputlangs->transnoentities('ReturnTracking'),   $object->return_tracking);

			$pdf->SetFont('', '', $default_font_size - 1);
			foreach ($tracking_rows as $row) {
				$pdf->SetXY($this->marge_gauche, $pdf->GetY());
				$pdf->SetFont('', 'B', $default_font_size - 1);
				$pdf->Cell(50, $heightrow, $outputlangs->convToOutputCharset($row[0]).':', 0, 0, 'L');
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->Cell($usablewidth - 50, $heightrow, $outputlangs->convToOutputCharset($row[1]), 0, 1, 'L');
			}
			$pdf->SetY($pdf->GetY() + 3);
		}

		// ---- RESOLUTION NOTES ----
		if (!empty($object->resolution_notes)) {
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->SetXY($this->marge_gauche, $pdf->GetY());
			$pdf->Cell($usablewidth, $heightrow, $outputlangs->transnoentities('ResolutionNotes'), 0, 1, 'L');

			$pdf->SetFont('', '', $default_font_size - 1);
			$pdf->SetXY($this->marge_gauche, $pdf->GetY());
			$notes = strip_tags(str_replace('<br>', "\n", $object->resolution_notes));
			$pdf->MultiCell($usablewidth, $heightrow, $outputlangs->convToOutputCharset($notes), 0, 'L', false, 1);
			$pdf->SetY($pdf->GetY() + 3);
		}

		// ---- SIGNATURE BLOCK ----
		$this->_signatureblock($pdf, $object, $outputlangs, $pagewidth);

		// ---- DRAFT WATERMARK ----
		if ($object->status == SvcRequest::STATUS_DRAFT && getDolGlobalString('WARRANTYSVC_DRAFT_WATERMARK')) {
			pdf_watermark($pdf, $outputlangs, 0, $this->page_format, $this->marge_gauche, $outputlangs->transnoentities('Draft'));
		}

		// ---- PAGE FOOTER ----
		$this->_pagefoot($pdf, $object, $outputlangs);

		// ---- OUTPUT ----
		$pdf->Output($filepath, 'F');
		$this->result = array('fullpath' => $filepath);

		return 1;
	}


	// -----------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------

	/**
	 * Print page header with logo and title
	 *
	 * @param TCPDF    $pdf         PDF instance
	 * @param SvcRequest $object    Object
	 * @param int      $showaddress 1=show company address
	 * @param Translate $outputlangs Lang
	 * @return void
	 */
	private function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
	{
		global $conf, $langs, $mysoc, $hookmanager;

		$outputlangs->loadLangs(array('main', 'bills', 'orders', 'companies', 'warrantysvc@warrantysvc'));

		$default_font_size = pdf_getPDFFontSize($outputlangs);
		pdf_pagehead($pdf, $outputlangs, $this->page_format == 'letter' ? 216 : 210);

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$w = 100;
		$posy = $this->marge_haute;
		$posx = $this->page_format == 'letter' ? 216 : 210;
		$posx -= $this->marge_droite + $w;

		// Document type title
		$pdf->SetXY($this->marge_gauche, $posy);
		$pdf->MultiCell(80, 3, $outputlangs->transnoentities('ServiceRequest'), '', 'L');

		// Ref
		$pdf->SetFont('', 'B', $default_font_size + 1);
		$pdf->SetXY($this->marge_gauche, $posy + 7);
		$pdf->MultiCell(80, 4, $outputlangs->convToOutputCharset($object->ref), '', 'L');
		$pdf->SetTextColor(0, 0, 0);

		// Date printed
		$pdf->SetFont('', '', $default_font_size - 1);
		$pdf->SetXY($this->marge_gauche, $posy + 13);
		$pdf->MultiCell(80, 3, $outputlangs->transnoentities('DatePrinted').': '.dol_print_date(dol_now(), 'day', false, $outputlangs), '', 'L');

		// Billable flag
		if ($object->billable) {
			$pdf->SetFont('', 'B', $default_font_size - 1);
			$pdf->SetTextColor(180, 0, 0);
			$pdf->SetXY($this->marge_gauche, $posy + 18);
			$pdf->MultiCell(80, 3, $outputlangs->transnoentities('Billable'), '', 'L');
			$pdf->SetTextColor(0, 0, 0);
		}

		// Logo + sender address on the right
		if ($showaddress) {
			pdf_logo_and_address($pdf, $outputlangs, $mysoc, $posx, $posy, $w, $this->marge_haute, $this->marge_gauche);
		}

		$pdf->SetFont('', '', $default_font_size - 1);
	}

	/**
	 * Print page footer with page numbers
	 *
	 * @param TCPDF      $pdf         PDF instance
	 * @param SvcRequest $object      Object
	 * @param Translate  $outputlangs Lang
	 * @return void
	 */
	private function _pagefoot(&$pdf, $object, $outputlangs)
	{
		$default_font_size = pdf_getPDFFontSize($outputlangs);
		pdf_pagefoot($pdf, $outputlangs, 'MAIN_PDF_FOOTER_TEXT', null, $this->marge_basse, $this->marge_gauche, $this->page_format, $object, 1, 1);
	}

	/**
	 * Print signature block near bottom of last page
	 *
	 * @param TCPDF      $pdf         PDF instance
	 * @param SvcRequest $object      Object
	 * @param Translate  $outputlangs Lang
	 * @param int        $pagewidth   Page width mm
	 * @return void
	 */
	private function _signatureblock(&$pdf, $object, $outputlangs, $pagewidth)
	{
		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$usablewidth = $pagewidth - $this->marge_gauche - $this->marge_droite;

		// Place near bottom
		$sigY = 250;
		if ($pdf->GetY() > $sigY) {
			$sigY = $pdf->GetY() + 5;
		}

		$pdf->SetDrawColor(180, 180, 180);
		$pdf->Line($this->marge_gauche, $sigY, $pagewidth - $this->marge_droite, $sigY);
		$sigY += 4;

		$colw = $usablewidth / 3;

		$pdf->SetFont('', 'B', $default_font_size - 1);
		$pdf->SetXY($this->marge_gauche, $sigY);
		$pdf->Cell($colw, 5, $outputlangs->transnoentities('TechnicianSignature'), 0, 0, 'C');
		$pdf->Cell($colw, 5, $outputlangs->transnoentities('CustomerSignature'),   0, 0, 'C');
		$pdf->Cell($colw, 5, $outputlangs->transnoentities('DateSigned'),          0, 1, 'C');

		// Blank lines for signatures
		$sigY += 12;
		$pdf->SetFont('', '', $default_font_size - 1);
		$pdf->Line($this->marge_gauche,             $sigY, $this->marge_gauche + $colw - 5,             $sigY);
		$pdf->Line($this->marge_gauche + $colw,     $sigY, $this->marge_gauche + $colw * 2 - 5,         $sigY);
		$pdf->Line($this->marge_gauche + $colw * 2, $sigY, $this->marge_gauche + $colw * 3 - 5,         $sigY);
	}

	/**
	 * Fetch and return customer name string
	 *
	 * @param SvcRequest $object Object
	 * @return string Customer name
	 */
	private function _getCustomerName($object)
	{
		$soc = new Societe($this->db);
		if ($soc->fetch($object->fk_soc) > 0) {
			return $soc->name;
		}
		return '';
	}

	/**
	 * Fetch and return product ref+label string
	 *
	 * @param SvcRequest $object Object
	 * @return string Product ref label
	 */
	private function _getProductLabel($object)
	{
		if (empty($object->fk_product)) {
			return '';
		}
		$product = new Product($this->db);
		if ($product->fetch($object->fk_product) > 0) {
			return $product->ref.($product->label ? ' - '.$product->label : '');
		}
		return '';
	}

	/**
	 * Fetch and return assigned user full name
	 *
	 * @param SvcRequest $object Object
	 * @return string User name
	 */
	private function _getAssignedUser($object)
	{
		global $langs;
		if (empty($object->fk_user_assigned)) {
			return '';
		}
		$u = new User($this->db);
		if ($u->fetch($object->fk_user_assigned) > 0) {
			return $u->getFullName($langs);
		}
		return '';
	}

	/**
	 * Get product label for a component line
	 *
	 * @param SvcRequestLine $line Line object
	 * @return string Label
	 */
	private function _getLineProductLabel($line)
	{
		if (empty($line->fk_product)) {
			return '';
		}
		$product = new Product($this->db);
		if ($product->fetch($line->fk_product) > 0) {
			return $product->ref.($product->label ? ' - '.$product->label : '');
		}
		return '';
	}

	/**
	 * Get translated label for a line type constant
	 *
	 * @param string    $type        Line type string
	 * @param Translate $outputlangs Lang
	 * @return string Translated label
	 */
	private function _getLineTypeLabel($type, $outputlangs)
	{
		$map = array(
			'component_out'  => 'LineTypeComponentOut',
			'component_in'   => 'LineTypeComponentIn',
			'consumed_site'  => 'LineTypeConsumedSite',
		);
		return isset($map[$type]) ? $outputlangs->transnoentities($map[$type]) : $type;
	}
}
