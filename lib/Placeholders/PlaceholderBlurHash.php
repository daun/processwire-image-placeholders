<?php

namespace Daun\Placeholders;

use Daun\Image;
use Daun\Placeholder;
use kornrunner\Blurhash\Blurhash;
use ProcessWire\Notice;
use ProcessWire\Pageimage;

class PlaceholderBlurHash extends Placeholder {
	public static string $name = 'blurhash';

	protected static int $maxThumbWidth = 100;
	protected static int $compX = 4;
	protected static int $compY = 3;

	public static function generatePlaceholder(Pageimage $image): string {
		$contents = Image::readImageContents($image->filename);
		$pixels = static::generatePixelMatrixFromImage($contents);
		if (!$pixels) return '';

		try {
			return Blurhash::encode($pixels, static::$compX, static::$compY);
		} catch (\Exception $e) {
			// $this->errors("Error encoding blurhash: {$e->getMessage()}", Notice::log);
			return '';
		}
	}

	public static function generateDataURI(string $hash, int $width = 0, int $height = 0): string {
		if (!$hash || $width <= 0 || $height <= 0) {
			return '';
		}

		$ratio =  $width / $height;
		$thumbWidth = floor(min(static::$maxThumbWidth, $width));
		$thumbHeight = floor($thumbWidth / $ratio);

		try {
			$pixels = Blurhash::decode($hash, $thumbWidth, $thumbHeight);
		} catch (\Exception $e) {
			// $this->errors("Error decoding blurhash: {$e->getMessage()}", Notice::log);
			$pixels = [];
		}

		$image = static::generateImageFromPixelMatrix($pixels, $thumbWidth, $thumbHeight);
		$data = base64_encode($image);
		return "data:image/png;base64,{$data}";
	}

	protected static function generatePixelMatrixFromImage(string $contents): array {
		if (!$contents) {
			return [];
		}

		$image = imagecreatefromstring($contents);
		$thumbWidth = min(static::$maxThumbWidth, imagesx($image));
		$image = imagescale($image, $thumbWidth, -1);
		$width = imagesx($image);
		$height = imagesy($image);

		$pixels = [];
		for ($y = 0; $y < $height; $y++) {
			$row = [];
			for ($x = 0; $x < $width; $x++) {
				$index = imagecolorat($image, $x, $y);
				$colors = imagecolorsforindex($image, $index);
				$r = max(0, min(255, $colors['red']));
				$g = max(0, min(255, $colors['green']));
				$b = max(0, min(255, $colors['blue']));
				$row[] = [$r, $g, $b];
			}
			$pixels[] = $row;
		}

		return [$width, $height, $pixels];
	}

	protected static function generateImageFromPixelMatrix(array $pixels, int $width, int $height): string {
		if (!$pixels || !count($pixels)) {
			return '';
		}

		$image = imagecreatetruecolor($width, $height);
		for ($y = 0; $y < $width; ++$y) {
			for ($x = 0; $x < $height; ++$x) {
				[$r, $g, $b] = $pixels[$y][$x];
				$r = max(0, min(255, $r));
				$g = max(0, min(255, $g));
				$b = max(0, min(255, $b));
				$allocate = imagecolorallocate($image, $r, $g, $b);
				imagesetpixel($image, $x, $y, $allocate);
			}
		}

		$image = imagescale($image, $width, -1);

		ob_start();
		imagepng($image);
		$contents = ob_get_contents();
		ob_end_clean();
		imagedestroy($image);

		return $contents;
	}
}
