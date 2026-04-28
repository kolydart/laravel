<?php

namespace Kolydart\Laravel\App\Traits;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

trait Impersonatable
{
    public function impersonate(Request $request): RedirectResponse
    {
        abort_if(Gate::denies('user_impersonate'), 403);

        $sessionKey  = config('kolydart.impersonate.session_key', 'impersonating_admin_id');
        $adminRoleId = config('kolydart.impersonate.admin_role_id', 1);

        // Guard: initiator must be an admin (defence-in-depth beyond Gate)
        abort_if(
            !auth()->user() || !auth()->user()->roles()->where('id', $adminRoleId)->exists(),
            403,
            'Only admins can impersonate'
        );

        // Guard: no nested impersonation
        abort_if(session()->has($sessionKey), 409, 'Already impersonating');

        $user = auth()->user()->newQuery()->findOrFail($request->route('user'));

        // Guard: self-impersonation
        abort_if($user->id === auth()->id(), 403);

        // Guard: cannot impersonate another admin
        abort_if(
            $user->roles()->where('id', $adminRoleId)->exists(),
            403,
            'Cannot impersonate an admin'
        );

        $adminId = auth()->id();

        session([$sessionKey => [
            'admin_id'   => $adminId,
            'started_at' => now()->timestamp,
        ]]);

        Auth::login($user);

        $this->auditImpersonation('impersonation_started', $adminId, $user->id);

        return redirect()->route('admin.home');
    }

    public function leaveImpersonation(): RedirectResponse
    {
        $sessionKey  = config('kolydart.impersonate.session_key', 'impersonating_admin_id');
        $adminRoleId = config('kolydart.impersonate.admin_role_id', 1);

        $stored = session($sessionKey);
        abort_if(!$stored, 403);

        // BC: support legacy scalar session value
        $adminId = is_array($stored) ? ($stored['admin_id'] ?? null) : $stored;
        abort_if(!$adminId, 403);

        $admin = auth()->user()->newQuery()->find($adminId);

        $targetId = auth()->id();
        session()->forget($sessionKey);

        // Guard: admin may have lost their role in the meantime
        if (!$admin || !$admin->roles()->where('id', $adminRoleId)->exists()) {
            Auth::logout();
            return redirect()->route('login');
        }

        Auth::login($admin);
        $this->auditImpersonation('impersonation_ended', $adminId, $targetId);

        return redirect()->route('admin.users.index');
    }

    protected function auditImpersonation(string $description, int $adminId, int $targetId): void
    {
        $class = class_exists('App\Models\AuditLog') ? 'App\Models\AuditLog'
               : (class_exists('App\AuditLog') ? 'App\AuditLog' : null);

        if (!$class) {
            return;
        }

        $class::create([
            'description'  => $description,
            'subject_id'   => $targetId,
            'subject_type' => config('auth.providers.users.model', 'App\Models\User'),
            'user_id'      => $adminId,
            'properties'   => ['ip' => request()->ip(), 'ua' => request()->userAgent()],
            'host'         => request()->getHost(),
        ]);
    }
}
