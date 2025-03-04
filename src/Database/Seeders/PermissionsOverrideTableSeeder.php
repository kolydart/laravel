<?php

/** generic permissions for all projects */

namespace Kolydart\Laravel\Database\Seeders;

use App\Permission;
use Illuminate\Database\Seeder;

class PermissionsOverrideTableSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            [ 'id'    => 1001, 'title' => 'backend_access', ],
            [ 'id'    => 1002, 'title' => 'datatables_csv', ],
            [ 'id'    => 1003, 'title' => 'pulse_access', ],
        ];

        Permission::upsert($permissions,['id'],['title']);
    }
}
