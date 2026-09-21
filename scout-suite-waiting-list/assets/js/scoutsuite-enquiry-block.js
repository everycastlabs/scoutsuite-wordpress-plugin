/**
 * Scout Suite Enquiry block.
 *
 * Dynamic block with no build step: the editor shows a simple placeholder
 * and the real form is rendered in PHP on the front end.
 */
( function ( blocks, element, blockEditor, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'scoutsuite/enquiry', {
		title: __( 'Scout Suite Enquiry', 'scoutsuite-waitlist' ),
		description: __( 'A general "get in touch" form that sends enquiries straight into Scout Suite. Works on a Group, District or County site.', 'scoutsuite-waitlist' ),
		icon: 'email-alt',
		category: 'widgets',
		keywords: [ 'scout', 'enquiry', 'enquire', 'contact', 'get in touch' ],
		supports: {
			html: false,
			multiple: false
		},

		edit: function () {
			var blockProps = blockEditor.useBlockProps
				? blockEditor.useBlockProps( { className: 'sswl-block-placeholder' } )
				: { className: 'sswl-block-placeholder' };

			return el(
				'div',
				blockProps,
				el( 'strong', {}, __( 'Scout Suite Enquiry', 'scoutsuite-waitlist' ) ),
				el(
					'p',
					{},
					__( 'The enquiry form appears here on the published page. Configure it under Settings, Scout Suite.', 'scoutsuite-waitlist' )
				)
			);
		},

		// Dynamic block: nothing is saved to post content.
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n );
