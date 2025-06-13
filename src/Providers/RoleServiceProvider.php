<?php

namespace Kolydart\Laravel\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Role Service Provider
 *
 * Provides Laravel Gate-based role checking system for the application.
 * This service provider automatically registers gates for each role defined
 * in the database and provides a unified interface for role-based authorization.
 *
 * Benefits:
 * - Leverages Laravel's Gate system for consistency
 * - Automatically discovers roles from database
 * - Provides better performance and maintainability
 * - Offers intuitive syntax for role checking
 * - Maintains type safety and prevents errors
 *
 * Usage Examples:
 *
 * In Controllers:
 *   if (auth()->user()->can('admin')) {
 *       // User is an admin (lowercase gate)
 *   }
 *
 *   if (auth()->user()->can('Admin')) {
 *       // User is an admin (original case gate)
 *   }
 *
 *   if (auth()->user()->can('role', ['Admin', 'Manager'])) {
 *       // User has either Admin or Manager role
 *   }
 *
 * In Blade Templates:
 *   @can('admin')
 *       <!-- Admin content (lowercase gate) -->
 *   @endcan
 *
 *   @can('Admin')
 *       <!-- Admin content (original case gate) -->
 *   @endcan
 *
 *   @can('role', ['Admin', 'Manager'])
 *       <!-- Admin or Manager content -->
 *   @endcan
 *
 * In Middleware:
 *   public function handle($request, Closure $next)
 *   {
 *       if (!auth()->user()->can('admin')) {
 *           abort(403);
 *       }
 *       return $next($request);
 *   }
 *
 * In Routes:
 *   Route::middleware(['auth', 'can:admin'])->group(function () {
 *       // Admin routes
 *   });
 *
 * @package Kolydart\Laravel\Providers
 * @author Digital Archive Management System
 * @version 1.0
 */
class RoleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Safety checks before attempting to register gates
        if (!$this->canRegisterGates()) {
            return;
        }

        // Dynamically register gates for each role in the database
        try {
            $roles = $this->getRoles();

            foreach ($roles as $role) {
                // Create multiple gate variations for flexibility
                $gateVariations = [
                    $role->title,  // Original case (e.g., "Admin")
                    Str::lower($role->title),  // Lowercase (e.g., "admin")
                    str_replace(' ', '_', Str::lower($role->title))  // Lowercase with underscores (e.g., "student_publisher")
                ];

                // Remove duplicates
                $gateVariations = array_unique($gateVariations);

                // Register a gate for each variation
                foreach ($gateVariations as $gateName) {
                    Gate::define($gateName, function ($user) use ($role) {
                        return $user->roles->contains('id', $role->id);
                    });
                }
            }
        } catch (\Exception $e) {
            // Log error but don't break the application
            if (config('app.debug')) {
                logger()->warning('RoleServiceProvider: Could not register role gates', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Always register the role helper gate (with safety checks)
        Gate::define('role', function ($user, $roles) {
            if (!$this->canCheckRoles()) {
                return false;
            }

            $roles = (array)$roles;

            try {
                $roleModel = $this->getRoleModel();

                // Simple case-insensitive role matching
                $roleIds = $roleModel::whereIn('title', $roles)
                              ->orWhere(function($query) use ($roles) {
                                  foreach ($roles as $role) {
                                      $query->orWhereRaw('LOWER(title) = ?', [strtolower($role)]);
                                  }
                              })
                              ->pluck('id')
                              ->toArray();

                return $user->roles->whereIn('id', $roleIds)->isNotEmpty();
            } catch (\Exception $e) {
                return false;
            }
        });
    }

    /**
     * Get the Role model class
     */
    protected function getRoleModel(): string
    {
        if (class_exists('App\Role')) {
            return 'App\Role';
        } elseif (class_exists('App\Models\Role')) {
            return 'App\Models\Role';
        }

        throw new \Exception('Role model not found. Expected App\Role or App\Models\Role');
    }

    /**
     * Check if we can safely register gates
     */
    protected function canRegisterGates(): bool
    {
        try {
            // Check if database connection exists
            DB::connection()->getDatabaseName();

            // Check if roles table exists
            if (!Schema::hasTable('roles')) {
                return false;
            }

            // Check if Role model exists (either location)
            $roleModel = $this->getRoleModel();
            if (!class_exists($roleModel)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if we can safely check roles
     */
    protected function canCheckRoles(): bool
    {
        return $this->canRegisterGates();
    }

    /**
     * Get roles with caching for performance
     */
    protected function getRoles()
    {
        $roleModel = $this->getRoleModel();

        // Cache roles for 1 hour to improve performance
        return cache()->remember('role_service_provider_roles', 3600, function () use ($roleModel) {
            return $roleModel::all();
        });
    }
}
