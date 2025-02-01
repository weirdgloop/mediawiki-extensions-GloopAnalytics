var moment = require( 'moment' );

(async function () {
	'use strict';

	const api = new mw.Rest(),
		pageViewsChart = $( '#gloopanalytics-page-views-all canvas' ),
		topPagesTable = $( '#gloopanalytics-top-pages table tbody' );

	const fetchData = (type, query = {}) => {
		return api.get( `/analytics/v0/fetch/${type}`, query );
	};

	const setupPageViews = async () => {
		const pageViewsData = await fetchData( 'page_views_30d' );

		// Create container for page views
		new Chart(
			pageViewsChart,
			{
				type: 'line',
				options: {
					plugins: {
						legend: {
							display: false
						},
						tooltip: {
							intersect: false
						}
					}
				},
				data: {
					labels: pageViewsData.map(row => moment( row.Day ).format( 'D MMMM' )),
					datasets: [
						{
							label: 'Views',
							data: pageViewsData.map(row => row.Requests),
							fill: true
						}
					]
				}
			}
		);
	};

	const setupPageViewsForPage = async () => {
		const titleInput = new mw.widgets.TitleInputWidget( {
			placeholder: OO.ui.msg( 'gloopanalytics-page-name' ),
		} );
		const submitButton = new OO.ui.ButtonWidget( {
			label: OO.ui.msg( 'gloopanalytics-page-submit' )
		} );
		const actionField = new OO.ui.ActionFieldLayout( titleInput, submitButton, {
			align: 'top',
		} );

		const onClick = async () => {
			const page = titleInput.getValue();
			const container = $( '#gloopanalytics-page-views-single .gloopanalytics-chart' );
			container.empty();
			if ( page ) {
				container.append( $( '<span>' ).text( 'Fetching...' ) );
				const data = await fetchData( 'page_views_30d', { page: page } );
				container.empty();

				if ( !data.length ) {
					container.append( $( '<span>' ).text( 'No data.' ) );
					return;
				}

				const canvas = $( '<canvas>' );
				container.append( canvas );

				// Create container for page views
				new Chart(
					canvas,
					{
						type: 'line',
						options: {
							plugins: {
								legend: {
									display: false
								},
								tooltip: {
									intersect: false
								}
							}
						},
						data: {
							labels: data.map(row => moment( row.Day ).format( 'D MMMM' )),
							datasets: [
								{
									label: 'Views',
									data: data.map(row => row.Requests),
									fill: true
								}
							]
						}
					}
				);
			}
		};

		submitButton.on( 'click', onClick );

		$( '#gloopanalytics-page-views-form' ).append( actionField.$element );

		const initialPage = $( '#gloopanalytics-page-views-single' ).attr( 'data-initial' );
		if ( initialPage ) {
			titleInput.setValue( mw.Title.newFromText( initialPage ).getPrefixedText() );
			onClick();
		}
	};

	const setupTopPages = async () => {
		const topPagesData = await fetchData( 'top_pages_30d' );

		// Add table rows
		for ( let row of topPagesData ) {
			topPagesTable.append( $( '<tr>' ).append(
				$( '<td>' ).append( $( '<a>' ).text( row['Path'] ).attr( 'href', row['Path'] ) ),
				$( '<td>' ).text( mw.language.convertNumber( row['Views'] ) )
			) );
		}
	};

	await Promise.all([
		setupPageViews(),
		setupPageViewsForPage(),
		setupTopPages()
	]);
})();
