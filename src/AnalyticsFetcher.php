<?php

namespace MediaWiki\Extension\GloopAnalytics;

use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\JobConfigurationInterface;
use MediaWiki\Config\Config;
use MediaWiki\Status\Status;
use MediaWiki\Title\TitleFactory;
use Psr\Log\LoggerInterface;

class AnalyticsFetcher {
	/**
	 * @var Config
	 */
	private Config $config;

	/**
	 * @var TitleFactory
	 */
	private TitleFactory $titleFactory;

	/**
	 * @var BigQueryClient
	 */
	private BigQueryClient $bq;

	/**
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * @var string
	 */
	private string $domain;

	function __construct( Config $config, TitleFactory $titleFactory, LoggerInterface $logger ) {
		$this->config = $config;
		$this->titleFactory = $titleFactory;
		$this->bq = new BigQueryClient([
			'projectId' => $this->config->get( 'GloopAnalyticsGCPProjectID' ),
			'keyFile' => json_decode( $this->config->get( 'GloopAnalyticsGCPCredentials' ), true )
		]);
		$this->logger = $logger;
		$this->domain = $this->config->get( 'ServerName' );
	}

	/**
	 * Returns the SQL query for aggregating page views.
	 * @param string|null $domain
	 * @param string|null $page
	 * @return JobConfigurationInterface
	 */
	public function pageViews30dSql( ?string $domain = null, ?string $page = null ) {
		$project = $this->config->get( 'GloopAnalyticsGCPProjectID' );
		$domain = $domain ?? $this->domain;
		$start = $this->bq->timestamp((new \DateTime())->sub(new \DateInterval( 'P30D' )));

		if ( $page ) {
			$page = str_replace( 'wiki', 'w', $this->titleFactory->newFromText( $page )->getLocalURL() );
		}

		return $this->bq->query(
			"SELECT SUM(Requests) as Requests, STRING(Day) as Day FROM `$project.cloudflare_logs.cf_logs_daily_views` WHERE Domain = @domain AND Day > @start AND CASE @page WHEN '' THEN TRUE ELSE Path = @page END GROUP BY Day ORDER BY 2 ASC"
		)
			->location( 'US' )
			->parameters([
				'start' => $start,
				'domain' => $domain,
				'page' => $page ?? ''
			]);
	}

	/**
	 * Returns the SQL query for aggregating most popular pages (30 days).
	 * @param string|null $domain
	 * @return JobConfigurationInterface
	 */
	public function topPages30dSql( ?string $domain = null ) {
		$project = $this->config->get( 'GloopAnalyticsGCPProjectID' );
		$domain = $domain ?? $this->domain;
		$minViews = $this->config->get( 'GloopAnalyticsMinViews' );
		$max = $this->config->get( 'GloopAnalyticsTopPagesMaxResults' );

		return $this->bq->query(
			"SELECT Path, Domain, SUM(Requests) as Views FROM `$project.cloudflare_logs.cf_logs_daily_views` WHERE Domain = @domain and TIMESTAMP_TRUNC(Day, DAY) >= TIMESTAMP(DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)) and Requests > @minViews GROUP BY 1, 2 ORDER BY 3 DESC LIMIT @max"
		)
			->location( 'US' )
			->parameters( [
				'domain' => $domain,
				'minViews' => $minViews,
				'max' => $max
			] );
	}

	/**
	 * Fetch the data from BigQuery for the given query and return a Status object containing it.
	 * @param JobConfigurationInterface $jobConfig
	 * @return Status
	 */
	public function fetch( JobConfigurationInterface $jobConfig ) {
		$status = new Status();

		try {
			$job = $this->bq->startQuery( $jobConfig );
			$results = $job->queryResults();

			$results->waitUntilComplete();

			$data = [];
			foreach ( $results as $row ) {
				$data[] = $row;
			}
			$status->setResult( true, $data );
		} catch ( \Throwable $ex ) {
			$this->logger->error(
				'Error from BigQuery while fetching analytics data: {exception}',
				[
					'exception' => $ex
				]
			);
			$status->setOK( false );
		}

		return $status;
	}
}
