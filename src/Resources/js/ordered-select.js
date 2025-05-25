/**
 * Ordered Select Component
 *
 * Provides functionality to preserve selection order in Select2 multi-select dropdowns.
 * When users select items, they will appear in the order they were selected, not alphabetical order.
 *
 * @author Kolydart
 * @version 1.0.0
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
            onSelect: null,
            onUnselect: null,
            ...options
        };

        $(selector).each(function() {
            const $select = $(this);

            if (config.preserveOrder) {
                OrderedSelect.preserveSelectionOrder($select, config);
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
     * Initialize ordered select for all elements with the 'ordered-select' class.
     * This is a convenience method for automatic initialization.
     */
    static autoInit() {
        $(document).ready(function() {
            OrderedSelect.init('.ordered-select');
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
