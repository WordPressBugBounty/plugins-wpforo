(function (blocks, editor, components, i18n, element) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var InspectorControls = editor.InspectorControls;
	var useBlockProps = editor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	
	blocks.registerBlockType('wpforo/profile', {
		title: __('wpForo User Profile & Notifications', 'wpforo'),
		icon: 'admin-users',
		category: 'widgets',
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			
			return el(Fragment, null,
				el(InspectorControls, { key: 'inspector' },
					el(PanelBody, { title: __('Settings', 'wpforo') },
						el(TextControl, {
							label: __('Title for Users', 'wpforo'),
							value: attributes.title,
							onChange: function (value) {
								setAttributes({ title: value });
							},
						}),
						el(TextControl, {
							label: __('Title for Guests', 'wpforo'),
							value: attributes.title_guest,
							onChange: function (value) {
								setAttributes({ title_guest: value });
							},
						}),
						el(ToggleControl, {
							label: __('Hide avatar', 'wpforo'),
							checked: attributes.hide_avatar,
							onChange: function (value) {
								setAttributes({ hide_avatar: value });
							},
						}),
						el(ToggleControl, {
							label: __('Hide user name', 'wpforo'),
							checked: attributes.hide_name,
							onChange: function (value) {
								setAttributes({ hide_name: value });
							},
						}),
						el(ToggleControl, {
							label: __('Hide notification bell', 'wpforo'),
							checked: attributes.hide_notification,
							onChange: function (value) {
								setAttributes({ hide_notification: value });
							},
						}),
						el(ToggleControl, {
							label: __('Hide user data', 'wpforo'),
							checked: attributes.hide_data,
							onChange: function (value) {
								setAttributes({ hide_data: value });
							},
						}),
						el(ToggleControl, {
							label: __('Hide buttons', 'wpforo'),
							checked: attributes.hide_buttons,
							onChange: function (value) {
								setAttributes({ hide_buttons: value });
							},
						}),
						el(ToggleControl, {
							label: __('Hide this block for guests', 'wpforo'),
							checked: attributes.hide_for_guests,
							onChange: function (value) {
								setAttributes({ hide_for_guests: value });
							},
						}),
					),
				),
				el('div', blockProps,
					el('div', { className: 'wpf-block-placeholder' },
						el('span', { className: 'dashicons dashicons-admin-users' }),
						__('wpForo User Profile & Notifications', 'wpforo'),
					),
				),
			);
		},
		save: function () {
			return null;
		},
	});
})(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.element,
);
