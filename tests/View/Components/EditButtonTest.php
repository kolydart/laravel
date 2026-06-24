<?php

namespace Kolydart\Laravel\Tests\View\Components;

use Kolydart\Laravel\View\Components\EditButton;
use Kolydart\Laravel\Tests\TestCase;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use PHPUnit\Framework\Assert;
use Mockery;

/**
 * Lightweight stand-in for the current route. Exposes the public `controller`
 * property and `getActionMethod()` the same way Illuminate\Routing\Route does.
 */
class FakeRoute
{
    public $controller;
    private string $actionMethod;

    public function __construct(string $actionMethod, $controller)
    {
        $this->actionMethod = $actionMethod;
        $this->controller = $controller;
    }

    public function getActionMethod(): string
    {
        return $this->actionMethod;
    }
}

class FakeControllerWithEdit
{
    public function show() {}
    public function edit() {}
}

class FakeControllerWithoutEdit
{
    public function show() {}
}

class EditButtonTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Bind mocked facade services into the container, then build the component
     * so its constructor exercises the real branching logic.
     */
    private function makeComponent(array $options = []): EditButton
    {
        $options = array_merge([
            'hasCurrentRoute' => true,
            'actionMethod'    => 'show',
            'controller'      => new FakeControllerWithEdit(),
            'gateDenies'      => false,
            'routeHas'        => true,
        ], $options);

        $route = new FakeRoute($options['actionMethod'], $options['controller']);

        $router = Mockery::mock();
        $router->shouldReceive('getCurrentRoute')
            ->andReturn($options['hasCurrentRoute'] ? $route : null);
        $router->shouldReceive('currentRouteName')->andReturn('admin.agents.show');
        $router->shouldReceive('has')->andReturn($options['routeHas']);
        $this->app->instance('router', $router);

        $request = Mockery::mock();
        $request->shouldReceive('route')->andReturn($route);
        $request->shouldReceive('segments')->andReturn(['admin', 'agents', '5']);
        $request->shouldReceive('segment')->andReturn('5');
        $this->app->instance('request', $request);

        $gate = Mockery::mock(GateContract::class);
        $gate->shouldReceive('denies')->andReturn($options['gateDenies']);
        $this->app->instance(GateContract::class, $gate);

        $translator = Mockery::mock();
        $translator->shouldReceive('has')->with('gw.edit')->andReturn(true);
        $translator->shouldReceive('has')->andReturn(false);
        $translator->shouldReceive('get')->andReturn('Edit');
        $this->app->instance('translator', $translator);

        $url = Mockery::mock();
        $url->shouldReceive('route')->andReturn('http://localhost/admin/agents/5/edit');
        $this->app->instance('url', $url);

        // Global helpers (request(), route(), trans()) resolve via the static
        // container instance rather than the facade application.
        Container::setInstance($this->app);

        return new EditButton();
    }

    public function test_shows_edit_button_when_all_conditions_met()
    {
        $component = $this->makeComponent();

        Assert::assertTrue($component->show);
        Assert::assertEquals('Edit', $component->text);
        Assert::assertEquals('http://localhost/admin/agents/5/edit', $component->url);
    }

    /**
     * The guard added to EditButton: a controller may keep its edit() method
     * while the edit route is intentionally unregistered (read-only resource).
     */
    public function test_hidden_when_edit_route_not_registered()
    {
        $component = $this->makeComponent(['routeHas' => false]);

        Assert::assertFalse($component->show);
        Assert::assertNull($component->url);
    }

    public function test_hidden_when_no_current_route()
    {
        $component = $this->makeComponent(['hasCurrentRoute' => false]);

        Assert::assertFalse($component->show);
    }

    public function test_hidden_when_action_is_not_show()
    {
        $component = $this->makeComponent(['actionMethod' => 'index']);

        Assert::assertFalse($component->show);
    }

    public function test_hidden_when_controller_has_no_edit_method()
    {
        $component = $this->makeComponent(['controller' => new FakeControllerWithoutEdit()]);

        Assert::assertFalse($component->show);
    }

    public function test_hidden_when_gate_denies_permission()
    {
        $component = $this->makeComponent(['gateDenies' => true]);

        Assert::assertFalse($component->show);
    }

    public function test_render_returns_empty_when_not_showing()
    {
        $component = $this->makeComponent(['routeHas' => false]);

        Assert::assertEquals('', $component->render());
    }

    public function test_render_outputs_link_when_showing()
    {
        $component = $this->makeComponent();
        $rendered = $component->render();

        Assert::assertStringContainsString('btn btn-warning', $rendered);
        Assert::assertStringContainsString('id="gw-edit-button"', $rendered);
        Assert::assertStringContainsString('fa fa-edit', $rendered);
    }
}
