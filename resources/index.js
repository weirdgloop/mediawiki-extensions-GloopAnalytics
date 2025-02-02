var moment = require( 'moment' );

(async function () {
	'use strict';

	const api = new mw.Rest(),
		pageViewsContainer = $( '#gloopanalytics-page-views-all' ),
		pageViewsSingleContainer = $( '#gloopanalytics-page-views-single' ),
		topPagesContainer = $( '#gloopanalytics-top-pages' );

	const fetchData = (type, query = {}) => {
		return api.get( `/analytics/v0/fetch/${type}`, query );
	};

	const addExportButton = ( container, chart, data ) => {
		let items = [
			new OO.ui.MenuOptionWidget( {
				data: 'csv',
				label: 'CSV'
			} ),
			new OO.ui.MenuOptionWidget( {
				data: 'json',
				label: 'JSON'
			} )
		];

		if ( chart ) {
			items.push(
				new OO.ui.MenuOptionWidget( {
					data: 'png',
					label: 'PNG'
				} )
			);
		}

		const buttonMenu = new OO.ui.ButtonMenuSelectWidget( {
			label: 'Export',
			icon: 'download',
			menu: {
				items: items
			}
		} );

		buttonMenu.getMenu().on( 'choose', function ( opt ) {
			const type = opt.getData();
			let a = document.createElement( 'a' );
			switch ( type ) {
				case 'png':
					a.href = chart.toBase64Image();
					a.download = `Analytics_export_${Date.now()}.png`;
					a.click();
					break;
				case 'csv':
					let csv = '';
					for (const v of data) {
						csv += `${Object.values(v).map((v) => `"${v}"`).join(',')}\r\n`;
					}
					a.href = URL.createObjectURL( new Blob( [ csv ], {
						type: 'text/csv;charset=utf-8;'
					} ) );
					a.download = `Analytics_export_${Date.now()}.csv`;
					break;
				case 'json':
					a.href = URL.createObjectURL( new Blob( [ JSON.stringify( data, undefined, 2 ) ], {
						type: 'application/json'
					} ) );
					a.download = `Analytics_export_${Date.now()}.json`;
					break;
			}
			a.click();
		} );

		container.find( '.gloopanalytics-export' ).append( buttonMenu.$element );
	};

	const setupPageViews = async () => {
		const pageViewsData = await fetchData( 'page_views_30d' );

		// Create container for page views
		const chartIns = new Chart(
			pageViewsContainer.find( 'canvas' ),
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

		addExportButton( pageViewsContainer, chartIns, pageViewsData );
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
			if ( submitButton.isDisabled() ) {
				return;
			}

			const page = titleInput.getValue();
			const container = pageViewsSingleContainer.find( '.gloopanalytics-chart' );
			container.empty();
			pageViewsSingleContainer.find( '.gloopanalytics-export' ).empty();
			if ( page ) {
				submitButton.setDisabled( true );
				container.append( $( '<span>' ).text( 'Fetching...' ) );
				const data = await fetchData( 'page_views_30d', { page: page } );
				container.empty();

				if ( !data.length ) {
					container.append( $( '<span>' ).text( 'No data.' ) );
					submitButton.setDisabled( false );
					return;
				}

				const canvas = $( '<canvas>' );
				container.append( canvas );

				// Create container for page views
				const chartIns = new Chart(
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

				addExportButton( pageViewsSingleContainer, chartIns, data );
				submitButton.setDisabled( false );
			}
		};

		titleInput.on( 'enter', onClick );
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
			topPagesContainer.find( 'table tbody' ).append( $( '<tr>' ).append(
				$( '<td>' ).append( $( '<a>' ).text( row['Path'] ).attr( 'href', row['Path'] ) ),
				$( '<td>' ).text( mw.language.convertNumber( row['Views'] ) )
			) );
		}

		addExportButton( topPagesContainer, null, topPagesData );
	};

	await Promise.all([
		setupPageViews(),
		setupPageViewsForPage(),
		setupTopPages()
	]);
})();
