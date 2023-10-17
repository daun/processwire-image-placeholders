<?php

namespace Daun\Placeholders;

use Daun\Placeholder;
use kornrunner\Blurhash\Blurhash;
use ProcessWire\Notice;
use ProcessWire\Pageimage;

class PlaceholderBlurHash extends Placeholder {
	public static string $name = 'blurhash';

	protected static int $compX = 4;
	protected static int $compY = 3;
	protected static int $thumbWidth = 200;


	public static function generatePlaceholder(Pageimage $image): string {
		$blurhash = '';

		$path = $image->filename;
		if (!file_exists($path) || is_dir($path) || !exif_imagetype($path)) {
			// $this->errors("Image file does not exist", Notice::log);
			return $blurhash;
		}

		$contents = file_get_contents($path);
		if (!$contents || empty($contents)) {
			// $this->errors("Image file is empty", Notice::log);
			return $blurhash;
		}

		$image = imagecreatefromstring($contents);
		$thumbWidth = min(static::$thumbWidth, imagesx($image));
		$image = imagescale($image, $thumbWidth, -1);
		$width = imagesx($image);
		$height = imagesy($image);

		$pixels = [];
		for ($y = 0; $y < $height; ++$y) {
			$row = [];
			for ($x = 0; $x < $width; ++$x) {
				$index = imagecolorat($image, $x, $y);
				$colors = imagecolorsforindex($image, $index);
				$r = max(0, min(255, $colors['red']));
				$g = max(0, min(255, $colors['green']));
				$b = max(0, min(255, $colors['blue']));
				$row[] = [$r, $g, $b];
			}
			$pixels[] = $row;
		}
		try {
			$blurhash = Blurhash::encode($pixels, static::$compX, static::$compY);
		} catch (\Exception $e) {
			// $this->errors("Error encoding blurhash: {$e->getMessage()}", Notice::log);
			return $blurhash;
		}

		return $blurhash;
	}

	public static function generateDataURI(string $hash, int $width = 0, int $height = 0): string {
		if (!$hash || $width <= 0 || $height <= 0) {
			return '';
		}

		$ratio =  $width / $height;
		$thumbWidth = floor(min(static::$thumbWidth, $width));
		$thumbHeight = floor($thumbWidth / $ratio);

		try {
			$pixels = Blurhash::decode($hash, $thumbWidth, $thumbHeight);
		} catch (\Exception $e) {
			// $this->errors("Error decoding blurhash: {$e->getMessage()}", Notice::log);
		}

		if (!$pixels) {
			return '';
		}

		$image = imagecreatetruecolor($thumbWidth, $thumbHeight);
		for ($y = 0; $y < $thumbHeight; ++$y) {
			for ($x = 0; $x < $thumbWidth; ++$x) {
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

		$data = base64_encode($contents);
		return "data:image/png;base64,{$data}";
	}
}
