<?php

namespace Daun;

use ProcessWire\Pageimage;

abstract class Placeholder {
	public static string $name = '';
	protected static string $fallback = "data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==";
	protected static int $thumbWidth = 200;

	/**
	 * Generate a placeholder string from a Pageimage object
	 *
	 * @param Pageimage $image The image to generate a placeholder for
	 * @return string The generated placeholder string
	 */
	abstract public static function generatePlaceholder(Pageimage $image): string;

	/**
	 * Generate a data URI from the placeholder string
	 *
	 * @param string $placeholder The placeholder string to generate a data URI for
	 * @return string The generated data URI
	 */
	abstract public static function generateDataURI(string $placeholder): string;
}
