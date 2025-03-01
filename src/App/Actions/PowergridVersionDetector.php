<?php

namespace Kolydart\Laravel\App\Actions;

class PowergridVersionDetector
{
    /**
     * Detect the PowerGrid version
     * 
     * @return string 'v2', 'v3', or 'unknown'
     */
    public static function detect(): string
    {
        // Check for PowerGridComponent class
        if (class_exists('PowerComponents\LivewirePowerGrid\PowerGridComponent')) {
            // Check for v3-specific methods or classes
            if (class_exists('PowerComponents\LivewirePowerGrid\PowerGridFields')) {
                return 'v3';
            }
            
            // Check for v2-specific methods or classes
            if (class_exists('PowerComponents\LivewirePowerGrid\PowerGridEloquent')) {
                return 'v2';
            }
        }
        
        return 'unknown';
    }
} 