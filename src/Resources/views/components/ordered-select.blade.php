{{--
    Ordered Select Component

    A reusable Blade component for creating Select2 dropdowns that preserve selection order.

    @param string $name - The name attribute for the select field
    @param string $id - The id attribute for the select field (defaults to $name)
    @param array $options - Array of options [value => label]
    @param array $selected - Array of selected values in order
    @param bool $multiple - Whether to allow multiple selections (default: true)
    @param string $placeholder - Placeholder text
    @param bool $required - Whether the field is required
    @param string $class - Additional CSS classes
    @param array $attributes - Additional HTML attributes
--}}

@props([
    'name',
    'id' => null,
    'options' => [],
    'selected' => [],
    'multiple' => true,
    'placeholder' => 'Please select...',
    'required' => false,
    'class' => '',
    'attributes' => []
])

@php
    $id = $id ?? $name;
    $selectClass = 'form-control select2 ordered-select ' . $class;
    $selectName = $multiple ? $name . '[]' : $name;

    // Prepare selected options in order
    $selectedOptions = [];
    if (!empty($selected)) {
        foreach ($selected as $value) {
            if (isset($options[$value])) {
                $selectedOptions[$value] = $options[$value];
                unset($options[$value]);
            }
        }
    }

    // Merge selected options first, then remaining options
    $orderedOptions = $selectedOptions + $options;
@endphp

<select
    name="{{ $selectName }}"
    id="{{ $id }}"
    class="{{ $selectClass }}"
    @if($multiple) multiple @endif
    @if($required) required @endif
    @foreach($attributes as $attr => $value)
        {{ $attr }}="{{ $value }}"
    @endforeach
>
    @if(!$multiple && !$required)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach($orderedOptions as $value => $label)
        <option
            value="{{ $value }}"
            @if(in_array($value, $selected)) selected @endif
        >
            {{ $label }}
        </option>
    @endforeach
</select>

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize ordered select for this specific element
        OrderedSelect.init('#{{ $id }}');
    });
</script>
@endpush
