(function (blocks, editor, components, i18n, element) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var InspectorControls = editor.InspectorControls;
	var useBlockProps = editor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	var SelectControl = components.SelectControl;
	
	// Check for NumberControl existence, fallback to TextControl if needed
	var NumberControl = components.NumberControl || components.TextControl;
	
	blocks.registerBlockType('wpforo/online-members', {
		title: __('wpForo Online Members', 'wpforo'),
		icon: 'groups',
		category: 'widgets',
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			
			var userGroups = window.wpforo_block_data ? window.wpforo_block_data.user_groups : [];
			if (userGroups.length === 0) {
				console.warn('wpForo Online Members: No user groups found in window.wpforo_block_data');
			}
			var options = userGroups.map(function (group) {
				return { value: String(group.groupid), label: group.name + ' (' + group.count + ')' };
			});
			
			var groupids = attributes.groupids || [];
			var selectedGroupids = groupids.map(String);
			
			return el(Fragment, null,
				el(InspectorControls, { key: 'inspector' },
					el(PanelBody, { title: __('Settings', 'wpforo') },
						el(TextControl, {
							label: __('Title', 'wpforo'),
							value: attributes.title,
							onChange: function (value) {
								setAttributes({ title: value });
							},
						}),
						el(SelectControl, {
							multiple: true,
							label: __('User Groups', 'wpforo'),
							value: selectedGroupids,
							options: options,
							onChange: function (value) {
								setAttributes({ groupids: value.map(Number) });
							},
						}),
						el(NumberControl, {
							label: __('Number of Items', 'wpforo'),
							value: attributes.count,
							min: 1,
							type: 'number',
							onChange: function (value) {
								setAttributes({ count: parseInt(value) || 1 });
							},
						}),
						el(ToggleControl, {
							label: __('Display with avatars', 'wpforo'),
							checked: attributes.display_avatar,
							onChange: function (value) {
								setAttributes({ display_avatar: value });
							},
						}),
						el(NumberControl, {
							label: __('Auto Refresh Interval Seconds', 'wpforo'),
							help: __('Set 0 to disable autorefresh', 'wpforo'),
							value: attributes.refresh_interval,
							min: 0,
							type: 'number',
							onChange: function (value) {
								setAttributes({ refresh_interval: parseInt(value) || 0 });
							},
						}),
					),
				),
				el('div', blockProps,
					el('div', { className: 'wpf-block-placeholder' },
						el('span', { className: 'dashicons dashicons-groups' }),
						__('wpForo Online Members', 'wpforo'),
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
