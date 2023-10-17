<?php namespace ProcessWire;

use Daun\Placeholders\PlaceholderBlurHash;
use Daun\Placeholders\PlaceholderThumbHash;

// Register the private namespace used by this module
wire('classLoader')->addNamespace('Daun', __DIR__ . '/lib');

class ImagePlaceholders extends WireData implements Module
{
	static public function getModuleInfo()
	{
		return [
			'title' => 'Image Placeholders',
			'summary' => 'Generate low-quality image placeholders (LQIP) on upload',
			'href' => 'http://modules.processwire.com/modules/image-placeholders/',
			'version' => '0.1.0',
			'author' => 'daun',
			'singular' => true,
			'autoload' => true,
			'icon' => 'picture-o',
			'requires' => [
				'PHP>=7.0',
				'ProcessWire>=3.0.155',
				'FieldtypeImage'
			]
		];
	}

	protected int $defaultLqipWidth = 20;
	protected array $generators = [];

	public function init()
	{
		$this->generators = [
			// PlaceholderNone::class => $this->_('None'),
			PlaceholderThumbHash::class => $this->_('ThumbHash'),
			PlaceholderBlurHash::class => $this->_('BlurHash'),
			// PlaceholderAverageColor::class => $this->_('Average Color'),
			// PlaceholderDominantColor::class => $this->_('Dominant Color'),
			// PlaceholderProcessWire::class => $this->_('Image variant'),
			// PlaceholderSVG::class => $this->_('SVG'),
		];

		// On image upload, generate placeholder
		$this->addHookAfter('FieldtypeImage::savePageField', $this, 'handleImageFieldSave');
		// Add settings to image field config screen
		$this->addHookAfter('FieldtypeImage::getConfigInputfields', $this, 'addImageFieldSettings');
		// Add `Pageimage.lqip` property that returns the placeholder data uri
		$this->addHookProperty('Pageimage::lqip', function (HookEvent $event) {
			$event->return = $this->getPlaceholderDataUri($event->object);
		});
		// Add `Pageimage.lqip($width, $height)` method that returns the placeholder in a given size
		$this->addHookMethod('Pageimage::lqip', function (HookEvent $event) {
			$width = $event->arguments(0) ?: null;
			$height = $event->arguments(1) ?: null;
			$event->return = $this->getPlaceholderDataUri($event->object, $width, $height);
		});
	}

	public function handleImageFieldSave(HookEvent $event): void
	{
		$page = $event->arguments(0);
		$field = $event->arguments(1);
		$images = $page->get($field->name);
		$placeholderType = $this->getPlaceholderType($field);

		if (!$placeholderType || !$images->count() || $page->hasStatus(Page::statusDeleted)) {
			return;
		}

		$image = $images->last(); // get the last added images (should be the last uploaded image)
		[, $placeholder] = $this->getPlaceholder($image, true);
		if (!$placeholder) {
			[$type, $placeholder] = $this->generatePlaceholder($image);
			if ($placeholder) {
				$this->setPlaceholder($image, [$type, $placeholder]);
			}
		}
	}

	protected function getPlaceholderType(Field $field): string
	{
		return $field->generateLqip ?? '';
	}

	protected function getPlaceholder(Pageimage $image, bool $checks = false): array
	{
		$type = $image->filedata("image-placeholder-type");
		$data = $image->filedata("image-placeholder-data");
		if ($checks) {
			$expectedType = $this->getPlaceholderType($image->field);
			if ($type !== $expectedType) {
				$data = null;
			}
		}
		return [$type, $data];
	}

	protected function setPlaceholder(Pageimage $image, array $placeholder): void
	{
		[$type, $data] = $placeholder;
		$image->filedata("image-placeholder-type", $type);
		$image->filedata("image-placeholder-data", $data);
		$image->page->save($image->field->name, ["quiet" => true, "noHooks" => true]);
	}

	protected function generatePlaceholder(Pageimage $image): array
	{
		$type = $this->getPlaceholderType($image->field);
		$handler = $this->getPlaceholderGenerator($type);
		$placeholder = '';

		try {
			$placeholder = $handler::generatePlaceholder($image);
		} catch (\Throwable $e) {
			$this->wire()->error($e->getMessage());
		}

		return [$type, $placeholder];
	}

	protected function getPlaceholderDataUri(Pageimage $image, ?int $width = null, ?int $height = null): string
	{
		[$type, $placeholder] = $this->getPlaceholder($image, false);
		if (!$placeholder) {
			return '';
		}

		$handler = $this->getPlaceholderGenerator($type);
		$width = $width ?: $this->defaultLqipWidth;
		$height = $height ?: $width / ($image->width / $image->height);

		try {
			return $handler::generateDataURI($placeholder, $width, $height);
		} catch (\Throwable $e) {
			$this->wire()->error($e->getMessage());
			return '';
		}
	}

	protected function getPlaceholderGenerator(string $type): string
	{
		foreach ($this->generators as $class => $label) {
			if ($class::$name === $type) {
				return $class;
			}
		}
		return PlaceholderNone::class;
	}

	protected function addImageFieldSettings(HookEvent $event)
	{
		$modules = $this->wire()->modules;

		$inputfields = $event->return;
		$field = $event->arguments(0);
		// $children = $inputfields->get('children'); // Due there is no first() in InputfieldWrapper

		/** @var InputfieldFieldset $fs */
		$fs = $modules->get('InputfieldFieldset');
		$fs->name = '_files_fieldset_placeholders';
		$fs->label = $this->_('Image placeholders');
		$fs->icon = 'picture-o';
		// $inputfields->insertAfter($fs, $children->first());
		$inputfields->add($fs);

		// Create field for choosing placeholder type
		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->name = 'generateLqip';
		$f->label = $this->_('Placeholders');
		$f->description = $this->_('Choose whether this field should generate low-quality image placeholders (LQIP) on upload.');
		$f->icon = 'toggle-on';
		$f->optionColumns = 1;
		$f->addOption('', $this->_('None'));
		foreach ($this->generators as $class => $label) {
			$f->addOption($class::$name, $label);
		}
		$f->value = $field->generateLqip;
		$fs->add($f);
	}
}
