(function ($) {
	jQuery( document ).ready(
		function ($) {
			$( ".generate-packing-slip, .re-generate-packing-slip" ).click(
				function (e) {
					e.preventDefault();
					let button = $( this );
					$( button )
					.find( ".spinner" )
					.css( { visibility: "visible", display: "block" } );
					var orderID = $( this ).data( "order-id" );
					HuxloeGeneratePackingSlip( orderID );
				}
			);

			function HuxloeGeneratePackingSlip(orderID) {
					$.post(
						HuxloeAjax.ajaxurl,
						{
							action: "generate_packing_slip",
							order_id: orderID,
							security: HuxloeAjax.nonce,
						},
						function (response) {
							$( ".generate-packing-slip, .re-generate-packing-slip" )
							.find( ".spinner" )
							.css( { visibility: "hidden", display: "none" } );

							if (response.success === false) {
								$(
									".huxloe-error-container.notice.notice-error.is-dismissible"
								).remove();

								let errors    = Array.isArray( response.data )
								? response.data
								: [response.data];
								let errorHTML =
								'<div class="huxloe-error-container notice notice-error is-dismissible" style="margin-bottom: 10px;">';
								errorHTML    +=
								'<button type="button" class="notice-dismiss"></button>';
								errorHTML    += '<ul style="list-style: disc; margin: 10px;">';

								errors.forEach(
									function (error) {
											// Remove single quotes from error messages.
											errorHTML += ` < li > ${error.replace( /'/g, "" )} < / li > `;
									}
								);

								errorHTML += "</ul></div>";
								$( "#wpbody-content" ).prepend( errorHTML );

								$( ".huxloe-error-container.is-dismissible" ).on(
									"click",
									".notice-dismiss",
									function () {
											$( this ).closest( ".huxloe-error-container" ).remove();
									}
								);
							} else {
								// If successful, reload the page.
								alert( response.data );
								window.location.reload();
							}
						}
					);
			}
		}
	);
})( jQuery );
