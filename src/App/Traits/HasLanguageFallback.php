<?php
namespace Kolydart\Laravel\App\Traits;

use Illuminate\Support\Facades\App;

/**
 * HasLanguageFallback Trait
 *
 * This trait provides intelligent multilingual support for Eloquent models by creating
 * fallback properties (title_fallback, description_fallback, name_fallback) with locale awareness.
 * Each property uses a cascading language fallback strategy based on the current application locale:
 *
 * - If app locale is 'el' (Greek): Try base field first, then field_en, and finally field_alt
 * - If app locale is 'en' (English): Try field_en first, then field_alt, and finally the base field
 * - For other locales: Try base field first, then field_en, and finally field_alt
 *
 * Example usage:
 * - $model->title_fallback will try $model->title → $model->title_en → $model->title_alt if locale is Greek
 * - $model->description_fallback will try $model->description_en → $model->description_alt → $model->description if locale is English
 * - $model->name_fallback will try $model->name → $model->name_en → $model->name_alt if locale is Greek
 * - Cascades through the fallback chain, returning the first non-null value found
 *
 * Can be used in any model field with fallback logic:
 * - $model->getFieldWithFallback('field') will try $model->field → $model->field_en → $model->field_alt if locale is Greek
 *
 * Environment considerations:
 * - Works in both web and console environments
 * - In console, defaults to Greek locale ('el') if locale detection fails
 *
 * Benefits:
 * - Implementation transparency: Provides access to multilingual content without needing constant translation availability checks
 * - Code reusability: As a trait, can be applied to multiple models without code duplication
 * - UI consistency: Ensures content is always displayed in UI if it exists in at least one language
 * - View simplification: Eliminates need for complex conditions in blade templates
 * - Encapsulation: Hides the fallback mechanism complexity from the rest of the code
 *
 * Limitations:
 * - Limited flexibility: Only works with title, description, and name fields specifically
 * - Implicit logic: May hide unexpected behavior, such as displaying English content instead of empty values
 */
trait HasLanguageFallback
{
    /**
     * Get the current locale with fallback to 'el'
     *
     * @return string
     */
    protected function getCurrentLocale()
    {
        try {
            return App::getLocale();
        } catch (\Throwable $e) {
            // In case of any exception, fallback to 'el' for simplicity and stability
            return 'el';
        }
    }

    /**
     * Get a field value with language fallback logic applied
     *
     * @param string $field Base field name
     * @return mixed
     */
    public function getFallback($field)
    {
        $locale = $this->getCurrentLocale();

        try {
            if ($locale === 'en') {
                // For English locale, try in this order: field_en, field_alt, field
                $fieldOrder = [
                    "{$field}_en",
                    "{$field}_alt",
                    $field
                ];
            } else {
                // For Greek or other locales, try in this order: field, field_en, field_alt
                $fieldOrder = [
                    $field,
                    "{$field}_en",
                    "{$field}_alt"
                ];
            }

            // Return the first non-null value found
            foreach ($fieldOrder as $attemptField) {
                if (isset($this->$attemptField) && $this->$attemptField !== null) {
                    return $this->$attemptField;
                }
            }

            // If we got here, all values were null
            return null;
        } catch (\Throwable $e) {
            // If any error occurs while accessing attributes, return null
            return null;
        }
    }

    /**
     * Get the title with language fallback
     *
     * @return mixed
     */
    public function getTitleFallbackAttribute()
    {
        return $this->getFallback('title');
    }

    /**
     * Get the description with language fallback
     *
     * @return mixed
     */
    public function getDescriptionFallbackAttribute()
    {
        return $this->getFallback('description');
    }

    /**
     * Get the name with language fallback
     *
     * @return mixed
     */
    public function getNameFallbackAttribute()
    {
        return $this->getFallback('name');
    }

    /**
     * Get the first secondary value for a field (one that is not the fallback value)
     *
     * @param string $field Base field name
     * @return mixed
     */
    public function getSecondary($field)
    {
        $locale = $this->getCurrentLocale();
        $fallbackValue = $this->getFallback($field);

        try {
            // Define field order based on locale
            if ($locale === 'en') {
                // For English locale, field order is: field_en (fallback) → field_alt → field
                $fieldOrder = [
                    "{$field}_alt",
                    $field
                ];
            } else {
                // For Greek/other locales, field order is: field (fallback) → field_en → field_alt
                $fieldOrder = [
                    "{$field}_en",
                    "{$field}_alt"
                ];
            }

            // Find the first non-null value that is not the fallback value
            foreach ($fieldOrder as $attemptField) {
                if (isset($this->$attemptField) && $this->$attemptField !== null && $this->$attemptField !== $fallbackValue) {
                    return $this->$attemptField;
                }
            }

            // If no secondary value is found
            return null;
        } catch (\Throwable $e) {
            // If any error occurs while accessing attributes, return null
            return null;
        }
    }

    /**
     * Get the first title secondary value (that is not the fallback value)
     *
     * @return mixed
     */
    public function getTitleSecondaryAttribute()
    {
        return $this->getSecondary('title');
    }

    /**
     * Get the first description secondary value (that is not the fallback value)
     *
     * @return mixed
     */
    public function getDescriptionSecondaryAttribute()
    {
        return $this->getSecondary('description');
    }

    /**
     * Get the first name secondary value (that is not the fallback value)
     *
     * @return mixed
     */
    public function getNameSecondaryAttribute()
    {
        return $this->getSecondary('name');
    }
}
