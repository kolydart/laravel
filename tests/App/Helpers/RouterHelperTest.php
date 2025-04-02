<?php

namespace Kolydart\Laravel\Tests\App\Helpers;

use Kolydart\Laravel\App\Helpers\RouterHelper;
use Kolydart\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Assert;
use Mockery;

class RouterHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_permission_title_with_explicit_route()
    {
        // Act
        $result = RouterHelper::getPermissionTitle('edit', 'admin.agents.show');
        
        // Assert
        Assert::assertEquals('agent_edit', $result);
    }

    public function test_get_permission_title_with_custom_method_and_explicit_route()
    {
        // Act
        $result = RouterHelper::getPermissionTitle('delete', 'admin.agents.show');
        
        // Assert
        Assert::assertEquals('agent_delete', $result);
    }

    public function test_get_permission_title_with_custom_route()
    {
        // Act
        $result = RouterHelper::getPermissionTitle('edit', 'admin.users.show');
        
        // Assert
        Assert::assertEquals('user_edit', $result);
    }

    public function test_get_permission_title_with_hyphenated_route()
    {
        // Act
        $result = RouterHelper::getPermissionTitle('create', 'admin.content-pages.index');
        
        // Assert
        Assert::assertEquals('content_page_create', $result);
    }

    public function test_replace_method_in_router_name_with_explicit_route()
    {
        // Act
        $result = RouterHelper::replaceMethodInRouterName('edit', 'admin.agents.show');
        
        // Assert
        Assert::assertEquals('admin.agents.edit', $result);
    }

    public function test_replace_method_in_router_name_with_custom_method_and_explicit_route()
    {
        // Act
        $result = RouterHelper::replaceMethodInRouterName('delete', 'admin.agents.show');
        
        // Assert
        Assert::assertEquals('admin.agents.delete', $result);
    }

    public function test_replace_method_in_router_name_with_different_route()
    {
        // Act
        $result = RouterHelper::replaceMethodInRouterName('edit', 'admin.users.show');
        
        // Assert
        Assert::assertEquals('admin.users.edit', $result);
    }
} 