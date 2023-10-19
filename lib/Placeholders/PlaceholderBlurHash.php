<?php

namespace Daun\Placeholders;

use Daun\Image;
use Daun\Placeholder;
use kornrunner\Blurhash\Blurhash;
use ProcessWire\Notice;
use ProcessWire\Pageimage;

class PlaceholderBlurHash extends Placeholder {
	public static string $name = 'blurhash';
	protected static int $compX = 4;
	protected static int $compY = 3;
	protected static int $maxInputSize = 200;
	protected static int $calcSize = 200;

	public static function generatePlaceholder(Pageimage $image): string {
		$contents = Image::readImageContents($image->filename);
		$pixels = static::generatePixelMatrixFromImage($contents);
		if (!count($pixels)) return '';

		try {
			return Blurhash::encode($pixels, static::$compX, static::$compY);
		} catch (\Exception $e) {
			throw new \Exception("Error encoding blurhash: {$e->getMessage()}");
			return '';
		}
	}

	public static function generateDataURI(string $hash, int $width = 0, int $height = 0): string {
		if (!$hash || $width <= 0 || $height <= 0) {
			return static::$fallback;
		}

		[$calcWidth, $calcHeight] = Image::contain($width, $height, static::$calcSize);

		try {
			$pixels = Blurhash::decode($hash, $calcWidth, $calcHeight);
		} catch (\Exception $e) {
			throw new \Exception("Error decoding blurhash: {$e->getMessage()}");
			$pixels = [];
		}

		$image = static::generateImageFromPixelMatrix($pixels, $width, $height);
		$data = base64_encode($image);
		return "data:image/png;base64,{$data}";
	}

	protected static function generatePixelMatrixFromImage(string $contents): array {
		if (!$contents) {
			return [];
		}

		$image = imagecreatefromstring($contents);
		[$width, $height] = Image::contain(imagesx($image), imagesy($image), static::$maxInputSize);
		$image = imagescale($image, $width, $height);

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

		return $pixels;
	}

	protected static function generateImageFromPixelMatrix(array $pixels, int $width, int $height): string {
		if (!$pixels || !count($pixels)) {
			return '';
		}

		[$calcWidth, $calcHeight] = Image::contain($width, $height, static::$calcSize);
		$image = imagecreatetruecolor($calcWidth, $calcHeight);
		for ($y = 0; $y < $calcHeight; $y++) {
			for ($x = 0; $x < $calcWidth; $x++) {
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
