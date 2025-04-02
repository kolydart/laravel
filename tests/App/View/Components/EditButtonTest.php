<?php

namespace Kolydart\Laravel\Tests\App\View\Components;

use Kolydart\Laravel\App\View\Components\EditButton;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Request;
use Kolydart\Laravel\Tests\TestCase;
use PHPUnit\Framework\Assert;
use Mockery;
use Illuminate\Translation\Translator;

class EditButtonTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_edit_button_not_shown_when_gate_denies_permission()
    {
        // Arrange
        Gate::shouldReceive('denies')->once()->andReturn(true);
        
        // Act
        $component = new EditButton();
        
        // Assert
        Assert::assertFalse($component->show);
        Assert::assertEquals('', $component->render());
    }
    
    public function test_edit_button_uses_correct_translation()
    {
        // Arrange
        Gate::shouldReceive('denies')->once()->andReturn(false);
        Route::shouldReceive('getCurrentRoute->getActionMethod')->once()->andReturn('show');
        Route::shouldReceive('currentRouteName')->once()->andReturn('admin.posts.show');
        
        // Mock the request route controller
        $mockController = Mockery::mock(\stdClass::class);
        $mockController->shouldReceive('edit')->once()->andReturn(null);
        
        Request::shouldReceive('route->controller')->andReturn($mockController);
        Request::shouldReceive('route->parameters')->andReturn(['post' => 1]);
        
        // Test gw.edit translation
        Lang::shouldReceive('has')->with('gw.edit')->once()->andReturn(true);
        Lang::shouldReceive('has')->with('global.edit')->never();
        
        $translatorMock = Mockery::mock(Translator::class);
        $translatorMock->shouldReceive('get')->with('gw.edit', [], null)->andReturn('Edit Post');
        $this->app->instance('translator', $translatorMock);
        
        // Act
        $component = new EditButton();
        
        // Assert
        Assert::assertTrue($component->show);
        Assert::assertEquals('Edit Post', $component->text);
    }
    
    public function test_edit_button_uses_fallback_text()
    {
        // Arrange
        Gate::shouldReceive('denies')->once()->andReturn(false);
        Route::shouldReceive('getCurrentRoute->getActionMethod')->once()->andReturn('show');
        Route::shouldReceive('currentRouteName')->once()->andReturn('admin.posts.show');
        
        // Mock the request route controller
        $mockController = Mockery::mock(\stdClass::class);
        $mockController->shouldReceive('edit')->once()->andReturn(null);
        
        Request::shouldReceive('route->controller')->andReturn($mockController);
        Request::shouldReceive('route->parameters')->andReturn(['post' => 1]);
        
        // No translation available
        Lang::shouldReceive('has')->with('gw.edit')->once()->andReturn(false);
        Lang::shouldReceive('has')->with('global.edit')->once()->andReturn(false);
        
        // Act
        $component = new EditButton();
        
        // Assert
        Assert::assertTrue($component->show);
        Assert::assertEquals('Edit', $component->text);
    }
    
    public function test_edit_button_not_shown_when_not_in_show_method()
    {
        // Arrange
        Gate::shouldReceive('denies')->once()->andReturn(false);
        Route::shouldReceive('getCurrentRoute->getActionMethod')->once()->andReturn('index');
        
        // Act
        $component = new EditButton();
        
        // Assert
        Assert::assertFalse($component->show);
        Assert::assertEquals('', $component->render());
    }
} 