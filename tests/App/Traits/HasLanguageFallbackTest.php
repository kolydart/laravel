<?php

namespace Kolydart\Laravel\Tests\App\Traits;

use Kolydart\Laravel\Tests\TestCase;
use Kolydart\Laravel\App\Traits\HasLanguageFallback;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class HasLanguageFallbackTest extends TestCase
{
    /** @test */
    public function it_exists()
    {
        $this->assertTrue(trait_exists(HasLanguageFallback::class));
    }

    /** @test */
    public function it_provides_title_fallback()
    {
        $model = new class extends Model {
            use HasLanguageFallback;
            
            protected $fillable = ['title', 'title_en'];
        };
        
        // Test when both title and title_en are null
        $this->assertNull($model->title_fallback);
        
        // Test when only title_en is set
        $model->title_en = 'English Title';
        $this->assertEquals('English Title', $model->title_fallback);
        
        // Test when both title and title_en are set (title takes precedence)
        $model->title = 'Main Title';
        $this->assertEquals('Main Title', $model->title_fallback);
    }
    
    /** @test */
    public function it_provides_description_fallback()
    {
        $model = new class extends Model {
            use HasLanguageFallback;
            
            protected $fillable = ['description', 'description_en'];
        };
        
        // Test when both description and description_en are null
        $this->assertNull($model->description_fallback);
        
        // Test when only description_en is set
        $model->description_en = 'English Description';
        $this->assertEquals('English Description', $model->description_fallback);
        
        // Test when both description and description_en are set (description takes precedence)
        $model->description = 'Main Description';
        $this->assertEquals('Main Description', $model->description_fallback);
    }
} 