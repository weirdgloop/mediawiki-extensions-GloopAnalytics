<?php

namespace MediaWiki\Extension\GloopAnalytics;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

/**
 * GloopAnalytics wiring for MediaWiki services.
 */
return [
	'GloopAnalytics.AnalyticsFetcher' => static function ( MediaWikiServices $services ): AnalyticsFetcher {
		return new AnalyticsFetcher(
			$services->getMainConfig(),
			$services->getTitleFactory(),
			LoggerFactory::getInstance( 'GloopAnalytics' )
		);
	},
];
