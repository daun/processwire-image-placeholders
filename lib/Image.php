<?php

namespace Daun;

class Image {
	static $imageTypes = [
		'gif' => \IMAGETYPE_GIF,
		'jpg' => \IMAGETYPE_JPEG,
		'jpeg' => \IMAGETYPE_JPEG,
		'png' => \IMAGETYPE_PNG
	];

	public static function getImageType(string $path) {
		if (!file_exists($path) || !is_readable($path) || is_dir($path) || !exif_imagetype($path)) {
			return null;
		}

		$type = null;
		if (function_exists('exif_imagetype')) {
			return exif_imagetype($path);
		}

		$info = @getimagesize($path);
		if (isset($info[2])) {
			return $info[2];
		}

		$extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
		if (static::$imageTypes[$extension]) {
			return static::$imageTypes[$extension];
		}

		return null;
	}

	public static function readImageContents(string $path) {
		if (!file_exists($path) || !is_readable($path) || is_dir($path) || !exif_imagetype($path)) {
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

	public static function generateDataURIFromRGBA(int $r, int $g, int $b, int $a): string {
		if (Image::supportsImagick()) {
			$alpha = $a / 255;
			$imagick = new \Imagick();
			$imagick->newImage(1, 1, new \ImagickPixel("rgba($r, $g, $b, $alpha)"));
			$imagick->setImageFormat('png');
			$contents = $imagick->getImageBlob();
			$imagick->clear();
			$imagick->destroy();
		} else {
			$image = imagecreatetruecolor(1, 1);
			imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));
			ob_start();
			imagepng($image);
			$contents = ob_get_contents();
			ob_end_clean();
			imagedestroy($image);
		}

		$data = base64_encode($contents);
		return "data:image/png;base64,{$data}";
	}

	public static function supportsImagick(): bool {
		return class_exists('\\Imagick');
	}
}
