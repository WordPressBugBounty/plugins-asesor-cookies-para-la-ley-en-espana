(function(blocks, blockEditor, components, element, i18n) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var labels = window.cdpCookiesConsentBlock || {};
	function label(key, fallback) {
		return labels[key] || __(fallback, 'asesor-cookies-para-la-ley-en-espana');
	}
	var InspectorControls = blockEditor.InspectorControls;
	var InnerBlocks = blockEditor.InnerBlocks;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var cookieIcon = el(
		'svg',
		{ viewBox: '0 0 24 24', width: 24, height: 24, xmlns: 'http://www.w3.org/2000/svg', 'aria-hidden': true, focusable: false },
		el('path', {
			fill: 'currentColor',
			d: 'M12 2.25a9.75 9.75 0 1 0 9.75 9.75 3.2 3.2 0 0 1-4.25-4.25 3.15 3.15 0 0 1-3.9-3.9A3.2 3.2 0 0 1 12 2.25Zm-3.9 5.9a1.35 1.35 0 1 1 0 2.7 1.35 1.35 0 0 1 0-2.7Zm3.75 6a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5Zm3.65-3.3a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5ZM7.75 16.4a1.1 1.1 0 1 1 0 2.2 1.1 1.1 0 0 1 0-2.2Z'
		})
	);

	blocks.registerBlockType('cdp-cookies/consent-container', {
		title: label('title', 'Consent-protected content'),
		description: label('description', 'Wrap videos, maps, iframes, and other external embeds that should load only after consent.'),
		icon: cookieIcon,
		category: 'widgets',
		attributes: {
			category: {
				type: 'string',
				default: 'personalization'
			},
			service: {
				type: 'string',
				default: label('externalContent', 'External content')
			},
			title: {
				type: 'string',
				default: label('externalContentBlocked', 'External content blocked')
			}
		},
		supports: {
			html: false
		},
		edit: function(props) {
			var attributes = props.attributes;
			var blockProps = useBlockProps({
				className: 'cdp-cookies-block-editor'
			});

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: label('consentSettings', 'Consent settings'), initialOpen: true },
						el(SelectControl, {
							label: label('consentCategory', 'Consent category'),
							value: attributes.category,
							options: [
								{ label: label('analytics', 'Analytics'), value: 'analytics' },
								{ label: label('marketing', 'Marketing'), value: 'marketing' },
								{ label: label('personalization', 'Personalization'), value: 'personalization' }
							],
							onChange: function(value) {
								props.setAttributes({ category: value });
							}
						}),
						el(TextControl, {
							label: label('serviceName', 'Service name'),
							value: attributes.service,
							onChange: function(value) {
								props.setAttributes({ service: value });
							}
						}),
						el(TextControl, {
							label: label('placeholderTitle', 'Placeholder title'),
							value: attributes.title,
							onChange: function(value) {
								props.setAttributes({ title: value });
							}
						})
					)
				),
				el(
					'div',
					{ className: 'cdp-cookies-block-editor__notice' },
					el('strong', null, label('title', 'Consent-protected content')),
					el('p', null, label('editorHelp', 'Place the video, map, iframe, or external embed inside this container.'))
				),
				el(InnerBlocks)
			);
		},
		save: function() {
			return el(InnerBlocks.Content);
		}
	});
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n);
