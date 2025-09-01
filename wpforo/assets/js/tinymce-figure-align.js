/* global tinymce */
// Minimal TinyMCE plugin to manage alignment of FIGURE elements across wpForo editors
// This provides the same alignment button behavior previously bundled in wpForo Advanced Attachments
// and applies to any <figure> selected in the editor (attachments, GIPHY, Tenor, etc.).

(function() {
    tinymce.PluginManager.add('wpfaeditimage', function (editor) {
        var toolbar;
        var each = tinymce.each;

        function isPlaceholder(node) {
            return !!(editor.dom.getAttrib(node, 'data-mce-placeholder') || editor.dom.getAttrib(node, 'data-mce-object'));
        }

        function isFigure(el) {
            return el && el.nodeName === 'FIGURE';
        }

        // Add alignment buttons similar to WP core naming
        each({
            alignleft: 'Align left',
            aligncenter: 'Align center',
            alignright: 'Align right',
            alignnone: 'No alignment'
        }, function (tooltip, name) {
            var direction = name.slice(5);

            editor.addButton('wp_img_' + name, {
                tooltip: tooltip,
                icon: 'dashicon dashicons-align-' + direction,
                cmd: ('alignnone' === name) ? 'wpAlignNone' : 'Justify' + direction.charAt(0).toUpperCase() + direction.slice(1),
                onPostRender: function () {
                    var self = this;
                    editor.on('NodeChange', function (event) {
                        if (!isFigure(event.element)) return;
                        var node = editor.dom.getParent(event.element, '.wp-caption') || event.element;
                        if ('alignnone' === name) {
                            self.active(!/\balign(left|center|right)\b/.test(node.className));
                        } else {
                            self.active(editor.dom.hasClass(node, name));
                        }
                    });
                }
            });
        });

        // Remove currently selected figure
        function removeSelectedFigure() {
            var node = editor.selection.getNode();
            var figure = editor.dom.getParent(node, 'figure') || (isFigure(node) ? node : null);
            if (!figure) return;

            // Place caret after the figure if possible
            var next = figure.nextSibling;
            var prev = figure.previousSibling;
            var parent = figure.parentNode;

            editor.dom.remove(figure);

            // Try to move selection to a sensible place
            if (next) {
                editor.selection.select(next, true);
                editor.selection.collapse(true);
            } else if (prev) {
                editor.selection.select(prev, true);
                editor.selection.collapse(false);
            } else if (parent) {
                editor.selection.select(parent, true);
                editor.selection.collapse(false);
            }

            editor.nodeChanged();
        }

        // Add delete button
        editor.addButton('wp_img_remove', {
            tooltip: 'Remove',
            icon: 'dashicon dashicons-no',
            onclick: removeSelectedFigure,
            onPostRender: function () {
                var self = this;
                editor.on('NodeChange', function (event) {
                    self.disabled(!isFigure(event.element));
                });
            }
        });

        // Build inline toolbar
        editor.once('preinit', function () {
            if (editor.wp && editor.wp._createToolbar) {
                toolbar = editor.wp._createToolbar([
                    'wp_img_alignleft',
                    'wp_img_aligncenter',
                    'wp_img_alignright',
                    'wp_img_alignnone',
                    'wp_img_remove'
                ]);
            }
        });

        // Show toolbar when FIGURE is selected
        editor.on('wptoolbar', function (event) {
            if (isFigure(event.element) && !isPlaceholder(event.element)) {
                event.toolbar = toolbar;
            }
        });

        // Commands for toggling classes on FIGURE
        function setAlign(align) {
            var node = editor.selection.getNode();
            var figure = editor.dom.getParent(node, 'figure') || (isFigure(node) ? node : null);
            if (!figure) return;
            editor.dom.removeClass(figure, 'alignleft');
            editor.dom.removeClass(figure, 'aligncenter');
            editor.dom.removeClass(figure, 'alignright');
            if (align) editor.dom.addClass(figure, 'align' + align);
            editor.nodeChanged();
        }

        editor.addCommand('JustifyLeft', function () { setAlign('left'); });
        editor.addCommand('JustifyCenter', function () { setAlign('center'); });
        editor.addCommand('JustifyRight', function () { setAlign('right'); });
        editor.addCommand('wpAlignNone', function () { setAlign(''); });
    });
})();
