<?php

class StoreInvoiceApi {
	protected $viewPath;

	public function __construct() {
		$this->viewPath = dirname(__FILE__).'/view';
	}

	public function process(StoreNavigation $request) {
		$invoiceHash = $request->getArg(1);

		$order = StoreModuleOrder::findByHash($invoiceHash);
		$valid = $order && $order->getInvoiceDocumentNumber();
		if( $valid ) {
			foreach( $order->getItems() as $item ) {
				if( !($item instanceof StoreModuleOrderItem) ) {
					$valid = false;
					break;
				}
			}
		}

		if( !$valid ) {
			@header("Connection: close", true, 404);
			exit;
		}

		if( $order->getLang() ) {
			$request->lang = $order->getLang();
			SiteModule::setLang($request->lang);
		}

		$pdf = new StoreInvoice();

		$pdf->SetCreator("TCPDF");
		$pdf->SetAuthor("");
		$pdf->SetTitle(StoreModule::__('Invoice') . " " . $order->getInvoiceDocumentNumber());
		$pdf->SetSubject(StoreModule::__('Invoice') . " " . $order->getInvoiceDocumentNumber());
		// $invoice->SetKeywords('TCPDF, PDF, example, test, guide');

		ob_start();
		$this->renderView($this->viewPath.'/invoice.pdf.php', array(
			"pdf" => $pdf,
			"order" => $order,
			"invoiceTitlePhrase" => StoreData::getInvoiceTitlePhrase(),
			"invoiceTextBeginning" => StoreData::getInvoiceTextBeginning(),
			"invoiceTextEnding" => StoreData::getInvoiceTextEnding(),
			"sellerCompanyInfo" => StoreData::getCompanyInfo(),
		));
		ini_set("display_errors", false);

		$html = ob_get_clean();

		$pdf->AddPage();

		$er = @error_reporting();
		@error_reporting($er & ~E_NOTICE);
		$pdf->writeHTML($html);

		$invoiceLogo = StoreData::getInvoiceLogo();
		if ($invoiceLogo) {
			$imgData = file_get_contents(dirname(__DIR__, 2) . '/' . $invoiceLogo);
			$pdf->Image('@' . $imgData, 15, 15, 10, 10, '', '', '', true, 300, '', false, false, 0, 'CM');
		}
		@error_reporting($er);
		$pdf->lastPage();
		$pdf->endPage();
		$pdf->Close();

		$invoiceFileNumber = (string)$order->getInvoiceDocumentNumber();
		if( extension_loaded("intl") && class_exists("Transliterator") )
			$invoiceFileNumber = Transliterator::create('Any-Latin;Latin-ASCII')->transliterate($invoiceFileNumber);
		$invoiceFileNumber = preg_replace("#[^a-z0-9_\\-]#isu", "_", mb_strtolower($invoiceFileNumber, "utf-8"));

		StoreModule::respondWithPDF($pdf, "invoice_" . $invoiceFileNumber . ".pdf"); // ends with exit()
	}

	/**
	 * Render template.
	 * @param string $templatePath path to template file.
	 * @param array $vars associative array with template variable values.
	 */
	protected function renderView($templatePath, $vars) {
		extract($vars);
		require $templatePath;
	}
}
