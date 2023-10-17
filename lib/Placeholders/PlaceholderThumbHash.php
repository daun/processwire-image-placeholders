<?php

namespace Daun\Placeholders;

use Daun\Image;
use Daun\Placeholder;
use Thumbhash\Thumbhash;
use ProcessWire\Notice;
use ProcessWire\Pageimage;

use function Thumbhash\extract_size_and_pixels_with_gd;
use function Thumbhash\extract_size_and_pixels_with_imagick;
use function Thumbhash\extract_size_and_pixels_with_imagick_pixel_iterator;

class PlaceholderThumbHash extends Placeholder {
	public static string $name = 'thumbhash';

	protected static int $maxThumbWidth = 100;

	public static function generatePlaceholder(Pageimage $image): string {
		try {
			$contents = Image::readImageContents($image->filename);
			[$width, $height, $pixels] = static::extractImageData($contents);
			$hash = Thumbhash::RGBAToHash($width, $height, $pixels);
			return Thumbhash::convertHashToString($hash);
		} catch (\Exception $e) {
			// $this->errors("Error encoding thumbhash: {$e->getMessage()}", Notice::log);
			return '';
		}
	}

	public static function generateDataURI(string $hash, int $width = 0, int $height = 0): string {
		if (!$hash || $width <= 0 || $height <= 0) {
			return '';
		}

		try {
			$hash = Thumbhash::convertStringToHash($hash);
			return Thumbhash::toDataURL($hash);
		} catch (\Exception $e) {
			// $this->errors("Error decoding thumbhash: {$e->getMessage()}", Notice::log);
			return '';
		}
	}

	protected static function extractImageData(string $contents): array {
		try {
			return extract_size_and_pixels_with_imagick_pixel_iterator($contents);
		} catch (\Throwable $th) {
			try {
				return extract_size_and_pixels_with_imagick($contents);
			} catch (\Throwable $th) {
				try {
					return extract_size_and_pixels_with_gd($contents);
				} catch (\Throwable $th) {
					return [0, 0, []];
				}
			}
		}
	}
}
