<?php

namespace Kolydart\Laravel\App\Helpers;

/**
 * Create random elements during tests
 *
 * @author kolydart
 */
class RandomImageGenerator {

	/**
	 * Create random image for testing
	 * @param  string      $path prefer absolute path
	 * @param  int|integer $height
	 * @param  int|integer $width
	 * @return void
	 * @example Random::image("/dir/file.jpg")
	 */
	public static function image(string $path, int $height = 600, int $width = 800 ): void {

		// Get the file extension from the path
	    $extension = pathinfo($path, PATHINFO_EXTENSION);

		// Create a blank image
        $image = imagecreatetruecolor($width, $height);

		// Set the color that the pixels will be filled with
		$color = imagecolorallocate($image, rand(0,255), rand(0,255), rand(0,255));

		// Fill the image with the selected color
		for ($i = 0; $i < $width; $i++) {
		  for ($j = 0; $j < $height; $j++) {
		    imagesetpixel($image, $i, $j, $color);
		  }
		}

		// create path if not exists
		$dir = dirname($path);

		if (!file_exists($dir)) {
			@ $result = mkdir($dir, 0775, true);
			if (! $result) {
				throw new \Exception("Could not create directory");
			}
		}

	    // Check the file extension and create an empty image in the appropriate format
	    switch ($extension) {
	      case 'png':
	        $result = imagepng($image, $path);
	        break;

	      case 'jpeg':
	      case 'jpg':
	        $result = imagejpeg($image, $path);
	        break;

	      case 'gif':
	        $result = imagegif($image, $path);
	        break;

	      default:
	        // If the file extension is not recognized, throw an exception
	        throw new \Exception('Invalid file extension');
	    }

		// Clean up
		imagedestroy($image);

		// Check if the image was saved successfully
		if (!$result) {
		  throw new \Exception("Image was not created");
		}

	}

} 