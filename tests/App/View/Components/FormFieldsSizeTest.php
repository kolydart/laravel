<?php

namespace Kolydart\Laravel\Tests\App\View\Components;

use Kolydart\Laravel\App\View\Components\FormFieldsSize;
use Kolydart\Laravel\Tests\TestCase;
use PHPUnit\Framework\Assert;

class FormFieldsSizeTest extends TestCase
{
    public function test_form_fields_size_with_default_class()
    {
        // Act
        $component = new FormFieldsSize();
        
        // Assert
        Assert::assertEquals('col-md-6', $component->class);
    }
    
    public function test_form_fields_size_with_custom_class()
    {
        // Act
        $component = new FormFieldsSize('col-md-4');
        
        // Assert
        Assert::assertEquals('col-md-4', $component->class);
    }
    
    public function test_form_fields_size_render_contains_expected_jquery()
    {
        // Act
        $component = new FormFieldsSize();
        $renderedView = $component->render();
        
        // Assert
        Assert::assertStringContainsString('jQuery(document).ready', $renderedView);
        Assert::assertStringContainsString('$("form").addClass("row mx-3")', $renderedView);
        Assert::assertStringContainsString('$("form > div.form-group").addClass("{{ $class }}")', $renderedView);
    }
} 