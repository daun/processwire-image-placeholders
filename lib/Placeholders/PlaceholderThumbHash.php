<?php

namespace Daun\Placeholders;

use Daun\Image;
use Daun\Placeholder;
use Thumbhash\Thumbhash;
use ProcessWire\Notice;
use ProcessWire\Pageimage;

class PlaceholderThumbHash extends Placeholder {
	public static string $name = 'thumbhash';
	protected static int $maxThumbSize = 100;

	public static function generatePlaceholder(Pageimage $image): string {
		try {
			$contents = Image::readImageContents($image->filename);
			if (!$contents) return '';
			[$width, $height, $pixels] = static::generatePixelMatrixFromImage($contents);
			$hash = Thumbhash::RGBAToHash($width, $height, $pixels);
			return Thumbhash::convertHashToString($hash);
		} catch (\Exception $e) {
			throw new \Exception("Error encoding thumbhash: {$e->getMessage()}");
		}
	}

	public static function generateDataURI(string $hash, int $width = 0, int $height = 0): string {
		if (!$hash || $width <= 0 || $height <= 0) {
			return static::$fallback;
		}

		try {
			$hash = Thumbhash::convertStringToHash($hash);
			return Thumbhash::toDataURL($hash);
		} catch (\Exception $e) {
			throw new \Exception("Error decoding thumbhash: {$e->getMessage()}");
		}
	}

	protected static function generatePixelMatrixFromImage(?string $contents): array {
		if (!$contents) {
			return [];
		}

		return static::generatePixelMatrixFromImageUsingGD($contents);
	}

	protected static function generatePixelMatrixFromImageUsingGD(string $contents): array {
		$image = @imagecreatefromstring($contents);
		[$width, $height] = Image::contain(imagesx($image), imagesy($image), static::$maxThumbSize);
		$image = imagescale($image, $width, $height);

		$pixels = [];
		for ($y = 0; $y < $height; $y++) {
			for ($x = 0; $x < $width; $x++) {
				$color_index = imagecolorat($image, $x, $y);
				$color = imagecolorsforindex($image, $color_index);
				$alpha = 255 - ceil($color['alpha'] * (255 / 127)); // GD only supports 7-bit alpha channel
				$pixels[] = $color['red'];
				$pixels[] = $color['green'];
				$pixels[] = $color['blue'];
				$pixels[] = $alpha;
			}
		}

		return [$width, $height, $pixels];
	}
}
