<?php

namespace Kolydart\Laravel\View\Components;

use Illuminate\View\Component;

/**
 * Class OrderedSelect
 *
 * Blade component for creating Select2 dropdowns that preserve selection order.
 *
 * @package Kolydart\Laravel\View\Components
 */
class OrderedSelect extends Component
{
    public string $name;
    public ?string $id;
    public array $options;
    public array $selected;
    public bool $multiple;
    public string $placeholder;
    public bool $required;
    public string $class;

    /**
     * Create a new component instance.
     *
     * @param string $name
     * @param string|null $id
     * @param array $options
     * @param array $selected
     * @param bool $multiple
     * @param string $placeholder
     * @param bool $required
     * @param string $class
     */
    public function __construct(
        string $name,
        ?string $id = null,
        array $options = [],
        array $selected = [],
        bool $multiple = true,
        string $placeholder = 'Please select...',
        bool $required = false,
        string $class = ''
    ) {
        $this->name = $name;
        $this->id = $id ?? $name;
        $this->options = $options;
        $this->selected = $selected;
        $this->multiple = $multiple;
        $this->placeholder = $placeholder;
        $this->required = $required;
        $this->class = $class;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('kolydart::components.ordered-select');
    }
}
