<?php

namespace MediaWiki\Extension\GloopAnalytics;

use MediaWiki\Html\TemplateParser;
use MediaWiki\SpecialPage\SpecialPage;

class SpecialAnalytics extends SpecialPage {

	private TemplateParser $templateParser;

	function __construct() {
		parent::__construct( 'Analytics', 'gloopanalytics' );
		$this->templateParser = new TemplateParser( __DIR__ . '/templates' );
	}

	function execute( $par ) {
		$this->setHeaders();
		$this->checkPermissions();


		$out = $this->getOutput();
		$out->setSubtitle( $this->msg( 'gloopanalytics-subtitle' )->text() );
		$out->addModuleStyles( 'ext.GloopAnalytics.styles' );
		$out->addModules( 'ext.GloopAnalytics' );

		$html = $this->templateParser->processTemplate(
			'Analytics',
			[
				'initial_page' => $par,
				'page_views' => $this->msg( 'gloopanalytics-page-views' )->text(),
				'page_views_specific' => $this->msg( 'gloopanalytics-page-views-specific' )->text(),
				'top_pages' => $this->msg( 'gloopanalytics-top-pages' )->text(),
				'top_pages_path' => $this->msg( 'gloopanalytics-top-pages-path' )->text(),
				'top_pages_requests' => $this->msg( 'gloopanalytics-top-pages-requests' )->text(),
			]
		);
		$out->addHTML( $html );
	}

	function getGroupName() {
		return 'wiki';
	}
}
