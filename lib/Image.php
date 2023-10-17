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
}
