(function (blocks, editor, components, i18n, element) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var InspectorControls = editor.InspectorControls;
	var useBlockProps = editor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	
	// Check for NumberControl existence, fallback to TextControl if needed
	var NumberControl = components.NumberControl || components.TextControl;
	
	blocks.registerBlockType('wpforo/tags', {
		title: __('wpForo Topic Tags', 'wpforo'),
		icon: 'tag',
		category: 'widgets',
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			
			var boards = window.wpforo_block_data ? window.wpforo_block_data.boards : [];
			var options = boards.map(function (board) {
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
							options: options,
							onChange: function (value) {
								setAttributes({ boardid: parseInt(value) });
							},
						}),
						el(ToggleControl, {
							label: __('Topic Counts', 'wpforo'),
							checked: attributes.topics,
							onChange: function (value) {
								setAttributes({ topics: value });
							},
						}),
						el(NumberControl, {
							label: __('Number of Items', 'wpforo'),
							value: attributes.count,
							min: 1,
							type: 'number',
							onChange: function (value) {
								setAttributes({ count: parseInt(value) });
							},
						}),
					),
				),
				el('div', blockProps,
					el('div', { className: 'wpf-block-placeholder' },
						el('span', { className: 'dashicons dashicons-tag' }),
						__('wpForo Topic Tags', 'wpforo'),
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
