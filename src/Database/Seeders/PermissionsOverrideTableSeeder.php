<?php

/** generic permissions for all projects */

namespace Kolydart\Laravel\Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionsOverrideTableSeeder extends Seeder
{
    /**
     * Seed the generic permissions.
     *
     * Matched on `title`, not on `id`. Upserting by id retitled whatever the
     * consumer app already had at 1001-1003, while leaving its permission_role
     * rows in place — silently granting every role that held the old permission
     * whatever this seeder renamed it to. The ids below are therefore only used
     * when the row has to be created and the id is still free.
     */
    public function run()
    {
        $permissions = [
            [ 'id'    => 1001, 'title' => 'backend_access', ],
            [ 'id'    => 1002, 'title' => 'datatables_csv', ],
            [ 'id'    => 1003, 'title' => 'pulse_access', ],
        ];

        $Permission = class_exists('App\Models\Permission') ? 'App\Models\Permission' : 'App\Permission';

        foreach ($permissions as $permission) {
            if ($Permission::where('title', $permission['title'])->exists()) {
                continue;
            }

            // Only claim the canonical id when nothing else owns it.
            if ($Permission::whereKey($permission['id'])->exists()) {
                unset($permission['id']);
            }

            // forceFill, not create(): 'id' is rarely in the model's $fillable and
            // mass assignment would drop it, losing the canonical id.
            (new $Permission)->forceFill($permission)->save();
        }
    }
}
