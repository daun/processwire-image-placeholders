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
				return static::readAverageImageColor($contents);
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

		[$r, $g, $b] = explode('.', $hash);
		return Image::generateDataURIFromRGB($r, $g, $b);
	}

	protected static function readAverageImageColor(?string $contents): string {
		if (!$contents) {
			return '';
		}

		$image = imagecreatefromstring($contents);
		$image = imagescale($image, 1, 1);
		$rgba = imagecolorsforindex($image, imagecolorat($image, 0, 0));
		imagedestroy($image);

		$channels = array_slice(array_values($rgba), 0, 4);

		return implode('.', $channels);
	}
}
