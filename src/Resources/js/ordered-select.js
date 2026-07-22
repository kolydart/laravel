/**
 * Ordered Select Component
 *
 * Provides functionality to preserve selection order in Select2 multi-select dropdowns.
 * When users select items, they will appear in the order they were selected, not alphabetical order.
 *
 * Optionally the already-selected tags can be reordered by dragging them, when
 * SortableJS is loaded and the select carries the `data-drag-reorder` attribute.
 *
 * @author Kolydart
 * @version 1.1.0
 */

class OrderedSelect {
    /**
     * Initialize ordered select functionality for a given selector.
     *
     * @param {string} selector - CSS selector for the select element(s)
     * @param {Object} options - Configuration options
     */
    static init(selector, options = {}) {
        const config = {
            preserveOrder: true,
            dragReorder: null,
            onSelect: null,
            onUnselect: null,
            onReorder: null,
            ...options
        };

        $(selector).each(function() {
            const $select = $(this);

            if (config.preserveOrder) {
                OrderedSelect.preserveSelectionOrder($select, config);
            }

            const dragReorder = config.dragReorder === null
                ? $select.is('[data-drag-reorder]')
                : config.dragReorder;

            if (dragReorder) {
                OrderedSelect.enableDragReorder($select, config);
            }
        });
    }

    /**
     * Allow the selected Select2 tags to be reordered by dragging them.
     *
     * The tag order is mirrored onto the underlying <option> elements on drop, so
     * the submitted `name[]` order — and therefore the persisted pivot order —
     * matches what the user sees.
     *
     * Degrades silently when SortableJS is absent: the selection-order behaviour
     * stays untouched.
     *
     * @param {jQuery} $select - The select element
     * @param {Object} config - Configuration options
     */
    static enableDragReorder($select, config = {}) {
        if (typeof Sortable === 'undefined' || !$select.length) {
            return;
        }

        // Select2 renders its container as the next sibling of the original
        // select; it may not exist yet if select2() has not run, so retry briefly.
        const choicesList = $select.next('.select2-container')
            .find('.select2-selection__rendered')
            .get(0);

        if (!choicesList) {
            const attempt = (config._dragReorderAttempt || 0) + 1;

            if (attempt <= 20) {
                setTimeout(function() {
                    OrderedSelect.enableDragReorder($select, { ...config, _dragReorderAttempt: attempt });
                }, 50);
            }

            return;
        }

        // A second Sortable on the same list would fire onEnd twice per drop.
        if (typeof Sortable.get === 'function' && Sortable.get(choicesList)) {
            return;
        }

        OrderedSelect.injectDragReorderStyle();
        $(choicesList).addClass('kolydart-drag-reorder');

        Sortable.create(choicesList, {
            draggable: '.select2-selection__choice',
            onMove: function(event) {
                // Keep the search field pinned at the end of the tag list.
                return !$(event.related).hasClass('select2-selection__search');
            },
            onEnd: function() {
                $(choicesList).children('.select2-selection__choice').each(function() {
                    const data = $(this).data('data');

                    if (data) {
                        $select.append($select.find('option[value="' + data.id + '"]'));
                    }
                });

                if (typeof config.onReorder === 'function') {
                    config.onReorder.call($select.get(0));
                }
            }
        });
    }

    /**
     * Preserve selection order for a Select2 element.
     *
     * @param {jQuery} $select - The select element
     * @param {Object} config - Configuration options
     */
    static preserveSelectionOrder($select, config) {
        // Handle new selections
        $select.on('select2:select', function (e) {
            const element = e.params.data.element;
            const $element = $(element);

            // Move the selected option to the end to preserve selection order
            $element.detach();
            $(this).append($element);
            $(this).trigger('change');

            // Call custom callback if provided
            if (typeof config.onSelect === 'function') {
                config.onSelect.call(this, e);
            }
        });

        // Handle unselections
        $select.on('select2:unselect', function (e) {
            // When unselecting, we don't need to do anything special
            // The option will remain in its current position in the DOM

            // Call custom callback if provided
            if (typeof config.onUnselect === 'function') {
                config.onUnselect.call(this, e);
            }
        });
    }

    /**
     * Inject the drag cursor rule once, so hosts do not need to publish a stylesheet.
     */
    static injectDragReorderStyle() {
        if (document.getElementById('kolydart-drag-reorder-style')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'kolydart-drag-reorder-style';
        style.textContent = '.kolydart-drag-reorder .select2-selection__choice { cursor: move; }';
        document.head.appendChild(style);
    }

    /**
     * Get the selected values in their selection order.
     *
     * @param {string|jQuery} selector - CSS selector or jQuery object for the select element
     * @return {Array} Array of selected values in order
     */
    static getOrderedValues(selector) {
        const $select = $(selector);
        return $select.val() || [];
    }

    /**
     * Set selected values in a specific order.
     *
     * @param {string|jQuery} selector - CSS selector or jQuery object for the select element
     * @param {Array} values - Array of values to select in order
     */
    static setOrderedValues(selector, values) {
        const $select = $(selector);

        // Clear current selection
        $select.val(null).trigger('change');

        // Select values in the specified order
        values.forEach(value => {
            const $option = $select.find(`option[value="${value}"]`);
            if ($option.length) {
                $option.prop('selected', true);
                $option.detach();
                $select.append($option);
            }
        });

        $select.trigger('change');
    }

    /**
     * Initialize ordered select for all elements with the 'ordered-select' class
     * or the 'data-drag-reorder' attribute.
     *
     * This is a convenience method for automatic initialization.
     */
    static autoInit() {
        $(document).ready(function() {
            OrderedSelect.init('.ordered-select, [data-drag-reorder]');
        });
    }
}

// Auto-initialize if jQuery and Select2 are available
if (typeof $ !== 'undefined' && $.fn.select2) {
    OrderedSelect.autoInit();
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OrderedSelect;
}

// AMD support
if (typeof define === 'function' && define.amd) {
    define([], function() {
        return OrderedSelect;
    });
}
