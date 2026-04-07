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
	
	blocks.registerBlockType('wpforo/forums', {
		title: __('wpForo Forums', 'wpforo'),
		icon: 'admin-comments',
		category: 'widgets',
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			
			var boards = window.wpforo_block_data ? window.wpforo_block_data.boards : [];
			var boardOptions = boards.map(function (board) {
				return { value: board.boardid, label: board.title };
			});
			
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
							label: __('Board', 'wpforo'),
							value: attributes.boardid,
							options: boardOptions,
							onChange: function (value) {
								setAttributes({ boardid: parseInt(value) });
							},
						}),
						el(ToggleControl, {
							label: __('Display as dropdown', 'wpforo'),
							checked: attributes.dropdown,
							onChange: function (value) {
								setAttributes({ dropdown: value });
							},
						}),
					),
				),
				el('div', blockProps,
					el('div', { className: 'wpf-block-placeholder' },
						el('span', { className: 'dashicons dashicons-admin-comments' }),
						__('wpForo Forums', 'wpforo'),
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
