<?php

namespace Daun\Placeholders;

use Daun\Placeholder;
use ProcessWire\Pageimage;

class PlaceholderNone extends Placeholder {
	public static string $name = '';

	public static function generatePlaceholder(Pageimage $image): string {
		return '';
	}

	public static function generateDataURI(string $value, int $width = 0, int $height = 0): string {
		return '';
	}
}
