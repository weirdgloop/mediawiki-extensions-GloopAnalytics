<?php

namespace MediaWiki\Extension\GloopAnalytics;

use MediaWiki\Config\Config;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class RestApiAnalytics extends SimpleHandler {

	private const VALID_TYPES = [ 'page_views_30d', 'top_pages_30d' ];

	/** @var AnalyticsFetcher */
	private $fetcher;

	/**
	 * @var Config
	 */
	private Config $config;

	/**
	 * @var int
	 */
	private int $minViews;

	/**
	 * @var string|null
	 */
	private string|null $domain;

	public function __construct( AnalyticsFetcher $fetcher, Config $config ) {
		$this->fetcher = $fetcher;
		$this->config = $config;
		$this->minViews = $this->config->get( 'GloopAnalyticsMinViews' );
		$this->domain = $this->config->get( 'GloopAnalyticsDomainOverride' );
	}

	/**
	 * @throws HttpException
	 */
	public function run( $type ) {
		if ( !$this->getAuthority()->isAllowed( 'gloopanalytics' ) ) {
			throw new HttpException( 'Invalid permission.', 401 );
		}

		switch ( $type ) {
			case 'page_views_30d':
				$query = $this->fetcher->pageViews30dSql( $this->domain, $this->getValidatedParams()['page'] );
				break;
			case 'top_pages_30d':
				$query = $this->fetcher->topPages30dSql( $this->domain );
				break;
			default:
				// should never be reached because of input validation
				$query = '';
		}

		$status = $this->fetcher->fetch( $query );
		if ( !$status->isGood() ) {
			throw new HttpException( 'There was a problem fetching the data.' );
		}

		/* @type array $results */
		$results = $status->getValue();

		if ( $type === 'page_views_30d' ) {
			// For privacy, remove any data points that have less than X requests
			$results = array_filter( $results, function ( $row ) {
				return $row['Requests'] > $this->minViews;
			} );
		}

		return $results;
	}

	public function getParamSettings() {
		return [
			'type' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => self::VALID_TYPES,
				ParamValidator::PARAM_REQUIRED => true
			],
			'page' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false
			]
		];
	}
}
