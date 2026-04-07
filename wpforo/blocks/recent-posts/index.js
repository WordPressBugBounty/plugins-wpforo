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
	var CheckboxControl = components.CheckboxControl;
	var RadioControl = components.RadioControl;
	
	// Check for NumberControl existence, fallback to TextControl if needed
	var NumberControl = components.NumberControl || components.TextControl;
	
	blocks.registerBlockType('wpforo/recent-posts', {
		title: __('wpForo Recent Posts', 'wpforo'),
		icon: 'format-chat',
		category: 'widgets',
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			
			var data = window.wpforo_block_data || {};
			var boards = data.boards || [];
			var forums = data.forums || [];
			
			var boardOptions = boards.map(function (board) {
				return { value: board.boardid, label: board.title };
			});
			
			function buildHierarchicalOptions (forums, parentId, level) {
				var options = [];
				var level = level || 0;
				var prefix = Array(level * 2 + 1).join('\u00A0'); // Non-breaking space for indentation
				
				forums.forEach(function (forum) {
					if (parseInt(forum.parentid) == parentId && (parseInt(forum.boardid) == attributes.boardid || !forum.boardid)) {
						options.push({
							value: forum.forumid,
							label: prefix + (level > 0 ? '\u2014 ' : '') + forum.title,
						});
						var children = buildHierarchicalOptions(forums, forum.forumid, level + 1);
						if (children.length > 0) {
							options = options.concat(children);
						}
					}
				});
				return options;
			}
			
			var forumOptions = buildHierarchicalOptions(forums, 0, 0);
			
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
							label: __('Filter by forums', 'wpforo'),
							checked: attributes.forumids_filter,
							onChange: function (value) {
								var newAttrs = { forumids_filter: value };
								if (!value) {
									newAttrs.forumids = [];
								}
								setAttributes(newAttrs);
							},
						}),
						el(SelectControl, {
							label: __('Forums', 'wpforo'),
							multiple: true,
							disabled: !attributes.forumids_filter,
							value: (attributes.forumids || []).map(String),
							options: forumOptions,
							onChange: function (value) {
								setAttributes({ forumids: (value || []).map(Number) });
							},
						}),
						el(ToggleControl, {
							label: __('Autofilter by current forum', 'wpforo'),
							checked: attributes.current_forumid_filter,
							onChange: function (value) {
								setAttributes({ current_forumid_filter: value });
							},
						}),
						el(SelectControl, {
							label: __('Order by', 'wpforo'),
							value: attributes.orderby,
							options: [
								{ label: __('Created Date', 'wpforo'), value: 'created' },
								{ label: __('Modified Date', 'wpforo'), value: 'modified' },
							],
							onChange: function (value) {
								setAttributes({ orderby: value });
							},
						}),
						el(SelectControl, {
							label: __('Order', 'wpforo'),
							value: attributes.order,
							options: [
								{ label: __('DESC', 'wpforo'), value: 'DESC' },
								{ label: __('ASC', 'wpforo'), value: 'ASC' },
								{ label: __('Random', 'wpforo'), value: 'RAND' },
							],
							onChange: function (value) {
								setAttributes({ order: value });
							},
						}),
						el(NumberControl, {
							label: __('Limit Per Topic', 'wpforo'),
							value: attributes.limit_per_topic,
							min: 0,
							type: 'number',
							onChange: function (value) {
								setAttributes({ limit_per_topic: parseInt(value) || 0 });
							},
							help: __('set 0 to remove this limit', 'wpforo'),
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
						el(NumberControl, {
							label: __('Excerpt Length', 'wpforo'),
							value: attributes.excerpt_length,
							min: 0,
							type: 'number',
							onChange: function (value) {
								setAttributes({ excerpt_length: parseInt(value) || 0 });
							},
						}),
						el(ToggleControl, {
							label: __('Display with avatars', 'wpforo'),
							checked: attributes.display_avatar,
							onChange: function (value) {
								setAttributes({ display_avatar: value });
							},
						}),
						el(ToggleControl, {
							label: __('Exclude First Posts', 'wpforo'),
							checked: attributes.exclude_firstposts,
							onChange: function (value) {
								setAttributes({ exclude_firstposts: value });
							},
						}),
						el(ToggleControl, {
							label: __('Display Only Unread Posts', 'wpforo'),
							checked: attributes.display_only_unread,
							onChange: function (value) {
								setAttributes({ display_only_unread: value });
							},
						}),
						el(ToggleControl, {
							label: __('Display [new] indicator', 'wpforo'),
							checked: attributes.display_new_indicator,
							onChange: function (value) {
								setAttributes({ display_new_indicator: value });
							},
						}),
						el(NumberControl, {
							label: __('Auto Refresh Interval Seconds', 'wpforo'),
							value: attributes.refresh_interval,
							min: 0,
							type: 'number',
							onChange: function (value) {
								setAttributes({ refresh_interval: parseInt(value) || 0 });
							},
							help: __('Set 0 to disable autorefresh', 'wpforo'),
						}),
					),
				),
				el('div', blockProps,
					el('div', { className: 'wpf-block-placeholder' },
						el('span', { className: 'dashicons dashicons-format-chat' }),
						__('wpForo Recent Posts', 'wpforo'),
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
