<?php

namespace Kolydart\Laravel\App\Traits;


use App\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use Spatie\MediaLibrary\MediaCollections\Models\Media;


/**
 * Trait for auditing model changes
 * use the trait on the model you want to audit
 * 
 * @example
 * use \Kolydart\Laravel\App\Traits\Auditable;
 * 
 */
trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            self::audit('create', $model);
        });

        static::updated(function (Model $model) {

            $changes = $model->getChanges();
            unset($changes['two_factor_code']);
            unset($changes['two_factor_expires_at']);
            unset($changes['updated_at']);
            unset($changes['remember_token']);

            // do not proceed if array is empty
            if (empty($changes)) {

                return;

            }

            $model->attributes = array_merge($changes, ['id' => $model->id]);

            self::audit('update', $model);
        });

        static::deleted(function (Model $model) {
            self::audit('delete', $model);
        });

    }

    protected static function audit(string $description, Model $model, ?int $user_id = null)
    {

        if ($user_id === null) {

            $user_id = auth()->id() ?? null;

        }

        $properties = $model->toArray();

        // remove empty & irrelevant properties (created_at, updated_at, uuid)
        
        $properties = collect($properties)->reject(function ($value, $key) {
            return blank($value) 
                || $key == 'created_at' 
                || $key == 'updated_at'
                || $key == 'uuid'
                || $key == 'id'
                ;
        })->all();

        // remove properties nested array for related / medialibrary 
        collect($properties)->each(function($value,$key) use(&$properties){

            // do not remove "properties" key
            if($key == 'custom_properties'){
                
                return; 

            }

            // remove array keys
            if(is_array($value)){
                unset($properties[$key]);
            }

        });

        // set subject_type to $model::class (null if not exists)
        $subject_type = $model ? get_class($model) ?? null : null;

        // once per day: bitstream|view
        if($description == 'bitstream' || $description == 'view'){

            if(AuditLog::where([
                ['description', '=', $description],
                ['subject_id', '=', $model->id],
                ['subject_type', '=', $model::class],
                ['user_id', '=', $user_id ?? null],
                ['host', '=', request()->ip() ?? null],
                ])
                ->whereDate('created_at', date('Y-m-d'))
                ->exists()){

            return;

            }
            
        }

        ### clean properties ###
        
        // empty in view|login|bitstream[item]
        if(
            $description == 'view' 
            || $description == 'login'
            || ($description == 'bitstream' && $model::class != Media::class )

        ){

            $properties = [];

        }

        // for bitstream|view[media] keep only file_name
        if(($description == 'bitstream' || $description == 'view') && $model::class == Media::class){

            $properties = collect($properties)->only('file_name');
            
        }

        // Simple approach: Limit text fields to prevent "data too long" errors
        foreach ($properties as $key => $value) {
            if (is_string($value) && strlen($value) > 10000) {
                $properties[$key] = str($value)->limit(10000);
            }
        }

        try {
            // create record
            AuditLog::create([
                'description'  => $description,
                'subject_id'   => $model->id ?? null,
                'subject_type' => $subject_type,
                'user_id'      => $user_id ?? null,
                'properties'   => $properties,
                'host'         => request()->ip() ?? null,
            ]);
        } catch (\Exception $e) {
            // If we still get an error, try with empty properties
            if (strpos($e->getMessage(), 'Data too long') !== false) {
                AuditLog::create([
                    'description'  => $description,
                    'subject_id'   => $model->id ?? null,
                    'subject_type' => $subject_type,
                    'user_id'      => $user_id ?? null,
                    'properties'   => ['error' => 'Data too large to store'],
                    'host'         => request()->ip() ?? null,
                ]);
            }
        }
    }
}