<?php

namespace Kolydart\Laravel\Tests\App\View\Components;

use Kolydart\Laravel\App\View\Components\Signature;
use Kolydart\Laravel\Tests\TestCase;
use PHPUnit\Framework\Assert;

class SignatureTest extends TestCase
{
    public function test_signature_without_copyright()
    {
        // Act
        $component = new Signature();
        
        // Assert
        Assert::assertStringContainsString('developed by', $component->developedBy);
        Assert::assertFalse($component->copyright);
    }
    
    public function test_signature_with_copyright()
    {
        // Act
        $component = new Signature(true);
        
        // Assert
        Assert::assertEquals('developed by <a href="https://www.kolydart.gr">kolydart</a>', $component->developedBy);
        Assert::assertTrue($component->copyright);
    }
    
    public function test_signature_render_without_copyright()
    {
        // Act
        $component = new Signature();
        $renderedView = $component->render();
        
        // Assert
        Assert::assertStringContainsString('<div class="row d-flex', $renderedView);
        Assert::assertStringContainsString('<span class="mr-1 opacity-50">{!! $developedBy !!}</span>', $renderedView);
        Assert::assertStringNotContainsString('<strong>©', $renderedView);
    }
    
    public function test_signature_render_with_copyright()
    {
        // Act
        $component = new Signature(copyright: true);
        $renderedView = $component->render();
        
        // Assert
        Assert::assertStringContainsString('<div class="row d-flex', $renderedView);
        Assert::assertStringContainsString('<strong>&copy;&nbsp;', $renderedView);
        Assert::assertStringContainsString('<span class="mr-1 opacity-50">{!! $developedBy !!}</span>', $renderedView);
    }
} 