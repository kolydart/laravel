{{--
    Table Hide Empty Rows Component

    A reusable Blade component that hides table rows with empty cells using jQuery.
    This component excludes PowerGrid tables and tables within tab panes by default.

    @param string $excludedTables - CSS selector for tables to exclude (default: '.power-grid-table')
    @param string $excludedContainers - CSS selector for containers to exclude (default: 'div.tab-pane')

    Usage:
    <x-kolydart::table-hide-empty-rows />
    <x-kolydart::table-hide-empty-rows excludedTables=".power-grid-table, .my-table" />
--}}

@props([
    'excludedTables' => '.power-grid-table',
    'excludedContainers' => 'div.tab-pane',
])

<script>
    jQuery(document).ready(function($) {
        $("table:not({{ $excludedTables }}) tr td").each(function() {
            // Check if the table is NOT inside an excluded container
            if ($(this).closest('table').closest('{{ $excludedContainers }}').length === 0) {
                if ($(this).html().match(/^\s*$/) !== null) {
                    $(this).parent().hide();
                }
            }
        });
    });
</script>
