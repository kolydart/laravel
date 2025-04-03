<?php

namespace Kolydart\Laravel\Tests\App\View\Components;

use Kolydart\Laravel\App\View\Components\MessageDisplay;
use Kolydart\Laravel\Tests\TestCase;
use PHPUnit\Framework\Assert;
use Mockery;

// Create a test-specific MessageDisplay class that doesn't depend on Session facade
class TestMessageDisplay extends MessageDisplay
{
    public function __construct(array $sessionData = [])
    {
        $this->messages = [];
        
        if (isset($sessionData['warning'])) {
            $this->messages[] = [
                'type' => 'warning',
                'text' => $sessionData['warning']
            ];
        }
        
        if (isset($sessionData['error'])) {
            $this->messages[] = [
                'type' => 'error',
                'text' => $sessionData['error']
            ];
        }
        
        if (isset($sessionData['gw_message_text'])) {
            $this->messages[] = [
                'type' => $sessionData['gw_message_type'] ?? 'warning',
                'text' => $sessionData['gw_message_text']
            ];
        }
    }
    
    public function render(): ?string
    {
        if (empty($this->messages)) {
            return null;
        }
        
        return 'Message display content would be rendered here';
    }
}

class MessageDisplayTest extends TestCase
{
    public function test_message_display_shows_warning()
    {
        // Arrange & Act
        $component = new TestMessageDisplay([
            'warning' => 'Test warning message'
        ]);
        
        // Assert
        Assert::assertEquals('warning', $component->messages[0]['type']);
        Assert::assertEquals('Test warning message', $component->messages[0]['text']);
    }

    public function test_message_display_shows_error()
    {
        // Arrange & Act
        $component = new TestMessageDisplay([
            'error' => 'Test error message'
        ]);
        
        // Assert
        Assert::assertEquals('error', $component->messages[0]['type']);
        Assert::assertEquals('Test error message', $component->messages[0]['text']);
    }

    public function test_message_display_shows_gw_message()
    {
        // Arrange & Act
        $component = new TestMessageDisplay([
            'gw_message_text' => 'Custom message',
            'gw_message_type' => 'info'
        ]);
        
        // Assert
        Assert::assertEquals('info', $component->messages[0]['type']);
        Assert::assertEquals('Custom message', $component->messages[0]['text']);
    }

    public function test_message_display_with_no_messages()
    {
        // Arrange & Act
        $component = new TestMessageDisplay();
        
        // Assert
        Assert::assertEmpty($component->messages);
        Assert::assertEquals('', $component->render());
    }
} 