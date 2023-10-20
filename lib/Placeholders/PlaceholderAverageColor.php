<?php

namespace Daun\Placeholders;

use Daun\Image;
use Daun\Placeholder;
use ProcessWire\Pageimage;

class PlaceholderAverageColor extends Placeholder {
	public static string $name = 'average-color';

	public static function generatePlaceholder(Pageimage $image): string {
		$contents = Image::readImageContents($image->filename);
		if ($contents) {
			try {
				$rgba = static::readAverageImageColor($contents);
				return implode('.', $rgba);
			} catch (\Exception $e) {
				throw new \Exception("Error encoding average color: {$e->getMessage()}");
			}
		}
		return '';
	}

	public static function generateDataURI(string $hash, int $width = 0, int $height = 0): string {
		if (!$hash || $width <= 0 || $height <= 0) {
			return static::$fallback;
		}

		$rgba = explode('.', $hash);
		if (count($rgba) < 3) {
			return static::$fallback;
		}
		[$r, $g, $b, $a] = $rgba;
		return Image::generateDataURIFromRGBA($r, $g, $b, $a);
	}


	protected static function readAverageImageColor(?string $contents): array {
		if (!$contents) return [];

		if (Image::supportsImagick()) {
			return static::readAverageImageColorUsingImagick($contents);
		} else {
			return static::readAverageImageColorUsingGD($contents);
		}
	}

	protected static function readAverageImageColorUsingGD(string $contents): array {
		$image = @imagecreatefromstring($contents);
		$image = imagescale($image, 1, 1);
		$rgba = imagecolorsforindex($image, imagecolorat($image, 0, 0));
		imagedestroy($image);
		return array_slice(array_values($rgba), 0, 4);
	}

	protected static function readAverageImageColorUsingImagick(string $contents): array {
		$image = new \Imagick();
		$image->readImageBlob($contents);
		$image->resizeImage(1, 1, \Imagick::FILTER_LANCZOS, 1);
		$pixel = $image->getImagePixelColor(0, 0);
		$rgba = $pixel->getColor(2);
		$image->destroy();
		return  array_slice(array_values($rgba), 0, 4);
	}
}
