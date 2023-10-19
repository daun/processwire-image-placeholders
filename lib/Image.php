<?php

namespace Daun;

class Image {
	public static function readImageContents(string $path) {
		if (!file_exists($path) || is_dir($path) || !exif_imagetype($path)) {
			// $this->errors("Image file does not exist", Notice::log);
			return null;
		}

		$contents = file_get_contents($path);
		if (!$contents || empty($contents)) {
			// $this->errors("Image file is empty", Notice::log);
			return null;
		}

		return $contents;
	}

	public static function contain(int $width, int $height, int $max): array {
		$ratio = $width / $height;
		if ($width >= $height) {
			$width = $max;
			$height = floor($max / $ratio);
		} else {
			$width = floor($max * $ratio);
			$height = $max;
		}
		return [$width, $height];
	}

	public static function generateDataURIFromRGB(int $r, int $g, int $b): string {
		$image = imagecreatetruecolor(1, 1);
		imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));

		ob_start();
		imagepng($image);
		$contents = ob_get_contents();
		ob_end_clean();
		imagedestroy($image);

		$data = base64_encode($contents);
		return "data:image/png;base64,{$data}";
	}

	public static function supportsImagick(): bool {
		return class_exists('\\Imagick');
	}
}
