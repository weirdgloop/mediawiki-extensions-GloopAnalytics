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
				'initial_page' => $par
			]
		);
		$out->addHTML( $html );
	}

	function getGroupName() {
		return 'wiki';
	}
}
