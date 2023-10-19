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
		$this->addHookAfter('FieldtypeImage::savePageField', $this, 'handleImageUpload');

		// Add settings to image field config screen
		$this->addHookAfter('FieldtypeImage::getConfigInputfields', $this, 'addImageFieldSettings');

		// Generate palceholders for existing images on field save
		$this->addHookAfter('FieldtypeImage::savedField', $this, 'handleImageFieldtypeSave');

		// Add `Pageimage.lqip` property that returns the placeholder data uri
		$this->addHookProperty('Pageimage::lqip', function (HookEvent $event) {
			$event->return = $this->getPlaceholderDataUri($event->object);
		});

		// Add `Pageimage.lqip($width, $height)` method that returns the placeholder in a given size
		$this->addHookMethod('Pageimage::lqip', function (HookEvent $event) {
			$width = (int) $event->arguments(0) ?: 0;
			$height = (int) $event->arguments(1) ?: 0;
			$event->return = $this->getPlaceholderDataUri($event->object, $width, $height);
		});
	}

	public function handleImageUpload(HookEvent $event): void
	{
		$page = $event->arguments(0);
		$field = $event->arguments(1);
		$images = $page->get($field->name);
		$type = $this->getPlaceholderType($field);
		if ($type && $images->count() && !$page->hasStatus(Page::statusDeleted)) {
			$image = $images->last(); // get the last added images (should be the last uploaded image)
			$this->generateAndSavePlaceholder($image);
		}
	}

	public function generateAndSavePlaceholder(Pageimage $image, bool $force = false): bool
	{
		[, $placeholder] = $this->getPlaceholder($image, true);
		if (!$placeholder || $force) {
			[$type, $placeholder] = $this->generatePlaceholder($image);
			if ($placeholder) {
				$this->setPlaceholder($image, [$type, $placeholder]);
				return true;
			}
		}
		return false;
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
			if ($this->wire()->user->isSuperuser()) {
				$this->wire()->error("Error generating image placeholder: {$e->getMessage()}");
			}
			$this->wire()->log("Error generating image placeholder: {$e->getMessage()}: {$e->getTraceAsString()}");
		}

		return [$type, $placeholder];
	}

	protected function getPlaceholderDataUri(Pageimage $image, int $width = 0, int $height = 0): string
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

	protected function createPlaceholdersForField(Field $field, bool $force = false): void
	{
		if (!$this->getPlaceholderType($field)) return;

		if ($force) {
			$this->message(sprintf($this->_('Re-generating image placeholders in field `%s`'), $field->name));
		} else {
			$this->message(sprintf($this->_('Generating missing image placeholders in field `%s`'), $field->name));
		}

		$count = 0;
		$total = 0;
		$pages = $this->wire()->pages->findMany("{$field->name}.count>0, check_access=0");
		foreach ($pages as $page) {
			$images = $page->getUnformatted($field->name);
			$total += $images->count();
			foreach ($images as $image) {
				if ($this->generateAndSavePlaceholder($image, $force)) {
					$count++;
				}
			}
		}

		$this->message(sprintf($this->_('Generated %d placeholders of %d images in field `%s`'), $count, $total, $field->name));
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

		// Placeholder type
		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->name = 'generateLqip';
		$f->label = $this->_('Placeholder type');
		$f->description = $this->_('Choose whether this field should generate low-quality image placeholders (LQIP) on upload.');
		$f->icon = 'toggle-on';
		$f->optionColumns = 1;
		$f->addOption('', $this->_('None'));
		foreach ($this->generators as $class => $label) {
			$f->addOption($class::$name, $label);
		}
		$f->value = $field->generateLqip;
		$fs->add($f);

		// Generate missing placeholders for existing images
		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'generateLqipForExisting';
		$f->label = $this->_('Generate missing placeholders');
		$f->description = $this->_('Placeholders are only generated when uploading new images.') . ' '
			. $this->_('Check the box below and submit the form to batch-generate image placeholders for any existing images in this field.');
		$f->label2 = $this->_('Generate missing placeholders for existing images');
		$f->collapsed = true;
		$f->showIf = 'generateLqip!=""';
		$f->icon = 'question-circle-o';
		$f->value = 1;
		$f->checked = false;
		$fs->add($f);

		// Re-generate all placeholders for existing images
		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'generateLqipForAll';
		$f->label = $this->_('Re-generate all placeholders');
		$f->description = $this->_('Check the box below and submit the form to re-generate all placeholders for any existing images in this field. Useful after changing the placeholder type.');
		$f->label2 = $this->_('Re-generate all placeholders for existing images');
		$f->collapsed = true;
		// $f->showIf = 'generateLqipForExisting=1';
		$f->showIf = 'generateLqip!=""';
		$f->icon = 'refresh';
		$f->value = 1;
		$f->checked = false;
		$fs->add($f);

		// generateLqipForExisting
	}

	protected function handleImageFieldtypeSave(HookEvent $event)
	{
		/** @var FieldtypeImage $fieldtype */
		$field = $event->arguments(0);

		if ($field->generateLqipForAll) {
			$this->createPlaceholdersForField($field, true);
		} else if ($field->generateLqipForExisting) {
			$this->createPlaceholdersForField($field, false);
		}
	}
}
