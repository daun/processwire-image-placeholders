<?php namespace ProcessWire;

use Daun\Image;
use Daun\Placeholders\PlaceholderAverageColor;
use Daun\Placeholders\PlaceholderBlurHash;
use Daun\Placeholders\PlaceholderThumbHash;

// Register the private namespace used by this module
wire('classLoader')->addNamespace('Daun', __DIR__ . '/lib');

class ImagePlaceholders extends WireData implements Module
{
	protected int $defaultLqipWidth = 20;
	protected array $generators = [];

	public function init()
	{
		$this->generators = [
			PlaceholderThumbHash::class => $this->_('ThumbHash'),
			PlaceholderBlurHash::class => $this->_('BlurHash'),
			PlaceholderAverageColor::class => $this->_('Average color'),
			// PlaceholderDominantColor::class => $this->_('Dominant Color'),
		];

		// Add settings to image field config screen
		$this->addHookAfter('FieldtypeImage::getConfigInputfields', $this, 'addImageFieldSettings');

		// Generate placeholders for existing images on field save
		$this->addHookAfter('FieldtypeImage::savedField', $this, 'handleImageFieldtypeSave');

		// Generate placeholder on image upload
		$this->addHookAfter('FieldtypeImage::savePageField', $this, 'handleImageUpload');

		// Add `$image->lqip` property that returns the placeholder data uri
		$this->addHookProperty('Pageimage::lqip', function (HookEvent $event) {
			$event->return = $this->getPlaceholderDataUri($event->object);
		});

		// Add `$image->lqip($width, $height)` method that returns the placeholder in a given size
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

		if ($field->_clearing_placeholders_) return;

		$images = $page->get($field->name);
		$type = $this->getPlaceholderType($field);
		if ($type && $images->count() && !$page->hasStatus(Page::statusDeleted) && !$page->isNew()) {
			$image = $images->last(); // get the last added images (should be the last uploaded image)
			if ($this->isSupportedImageFormat($image)) {
				$this->generateAndSavePlaceholder($image);
			}
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
		return $field->imagePlaceholderType ?: '';
	}

	protected function getPlaceholder(Pageimage $image, bool $checks = false): array
	{
		$type = $image->filedata("image-placeholder-type");
		$data = $image->filedata("image-placeholder-data");
		if ($checks) {
			$created = $image->filedata("image-placeholder-created");
			$current = $created && $image->modified <= $created;
			$expected = $this->getPlaceholderType($image->field);
			if (!$current || $type !== $expected) {
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
		$image->filedata("image-placeholder-created", time());

		$of = $image->page->of(false);
		$image->page->save($image->field->name, ["quiet" => true, "noHooks" => true]);
		$image->page->of($of);
	}

	protected function clearPlaceholder(Pageimage $image): bool
	{
		$before = $image->filedata("image-placeholder-data");
		$image->filedata(false, "image-placeholder-type");
		$image->filedata(false, "image-placeholder-data");
		$image->filedata(false, "image-placeholder-created");
		$after = $image->filedata("image-placeholder-data");

		$of = $image->page->of(false);
		$image->page->save($image->field->name, ["quiet" => true, "noHooks" => true]);
		$image->page->of($of);

		return $before !== $after;
	}

	protected function generatePlaceholder(Pageimage $image): array
	{
		try {
			$type = $this->getPlaceholderType($image->field);
			$handler = $this->getPlaceholderGenerator($type);
			$placeholder = $handler::generatePlaceholder($image);
			return [$type, $placeholder];
		} catch (\Throwable $e) {
			if ($this->wire()->user->isSuperuser()) {
				$this->wire()->error("Error generating image placeholder: {$e->getMessage()}");
			}
			$this->wire()->log("Error generating image placeholder: {$e->getMessage()}: {$e->getTraceAsString()}");
		}

		return [null, null];
	}

	protected function getPlaceholderDataUri(Pageimage $image, int $width = 0, int $height = 0): string
	{
		[$type, $placeholder] = $this->getPlaceholder($image, false);
		if (!$placeholder) return '';

		$width = $width ?: $this->defaultLqipWidth;
		$height = $height ?: floor($width / ($image->width / $image->height));

		$key = $this->wire()->sanitizer->pageName("{$image->field->name}-{$type}-{$placeholder}", false, 255);

		try {
			return $this->wire()->cache->getFor('image-placeholders', $key, WireCache::expireMonthly, function () use ($type, $placeholder, $width, $height) {
				$handler = $this->getPlaceholderGenerator($type);
				return $handler::generateDataURI($placeholder, $width, $height);
			});
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

		$total = 0;
		$generated = 0;
		$pages = $this->wire()->pages->findMany("{$field->name}.count>0, check_access=0");
		foreach ($pages as $page) {
			$images = $page->getUnformatted($field->name);
			foreach ($images as $image) {
				$total++;
				if ($this->generateAndSavePlaceholder($image, $force)) {
					$generated++;
				}
			}
		}

		$this->message(sprintf($this->_('Generated %d placeholders of %d images in field `%s`'), $generated, $total, $field->name));
	}

	protected function removePlaceholdersForField(Field $field, bool $force = false): void
	{
		$total = 0;
		$removed = 0;
		$field->_clearing_placeholders_ = true;
		$pages = $this->wire()->pages->findMany("{$field->name}.count>0, check_access=0");
		foreach ($pages as $page) {
			$images = $page->getUnformatted($field->name);
			foreach ($images as $image) {
				$total++;
				if ($this->clearPlaceholder($image, $force)) {
					$removed++;
				}
			}
		}

		$this->wire()->cache->deleteFor('image-placeholders', "{$field->name}-*");

		$this->message(sprintf($this->_('Removed %d placeholders of %d images in field `%s`'), $removed, $total, $field->name));
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
		$fs->addClass('InputfieldIsOffset');
		// $inputfields->insertAfter($fs, $children->first());
		$inputfields->add($fs);

		// Placeholder type
		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->name = 'imagePlaceholderType';
		$f->label = $this->_('Placeholder type');
		$f->description = $this->_('Choose whether this field should generate low-quality image placeholders (LQIP) on upload.');
		$f->icon = 'toggle-on';
		$f->optionColumns = 1;
		$f->addOption('', $this->_('None'));
		foreach ($this->generators as $class => $label) {
			$f->addOption($class::$name, $label);
		}
		$f->value = $field->imagePlaceholderType;
		$fs->add($f);

		// Generate missing placeholders for existing images
		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'imagePlaceholdersGenerateMissing';
		$f->label = $this->_('Generate missing placeholders');
		$f->description = $this->_('Placeholders are only generated when uploading new images.') . ' '
			. $this->_('Check the box below and submit the form to batch-generate image placeholders for any existing images in this field.');
		$f->label2 = $this->_('Generate missing placeholders for existing images');
		$f->collapsed = true;
		$f->showIf = 'imagePlaceholderType!=""';
		$f->icon = 'question-circle-o';
		$f->value = 1;
		$f->checked = false;
		$fs->add($f);

		// Recreate all placeholders for existing images
		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'imagePlaceholdersGenerateAll';
		$f->label = $this->_('Recreate all placeholders');
		$f->description = $this->_('Check the box below and submit the form to re-generate all placeholders for any existing images in this field. Useful after changing the placeholder type.');
		$f->label2 = $this->_('Recreate all placeholders for existing images');
		$f->collapsed = true;
		// $f->showIf = 'imagePlaceholdersGenerateMissing=1';
		$f->showIf = 'imagePlaceholderType!=""';
		$f->icon = 'refresh';
		$f->value = 1;
		$f->checked = false;
		$fs->add($f);

		// Remove all placeholders for existing images
		/** @var InputfieldCheckbox $f */
		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'imagePlaceholdersRemoveAll';
		$f->label = $this->_('Remove all placeholders');
		$f->description = $this->_('Check the box below and submit the form to remove any generated placeholders for images in this field. Useful to clean up database space before uninstalling this module.');
		$f->label2 = $this->_('Remove all placeholders for existing images');
		$f->collapsed = true;
		$f->icon = 'trash-o';
		$f->value = 1;
		$f->checked = false;
		$fs->add($f);

		// imagePlaceholdersGenerateMissing
	}

	protected function handleImageFieldtypeSave(HookEvent $event)
	{
		/** @var FieldtypeImage $fieldtype */
		$field = $event->arguments(0);

		if ($field->imagePlaceholdersGenerateAll) {
			$this->createPlaceholdersForField($field, true);
		} else if ($field->imagePlaceholdersGenerateMissing) {
			$this->createPlaceholdersForField($field, false);
		} else if ($field->imagePlaceholdersRemoveAll) {
			$this->removePlaceholdersForField($field);
		}
	}

	protected function isSupportedImageFormat(Pageimage $image): bool
	{
		$format = Image::getImageType($image->filename);
		return in_array($format, $this->supportedImageFormats());
	}

	protected function supportedImageFormats() {
		return [\IMAGETYPE_GIF, \IMAGETYPE_JPEG, \IMAGETYPE_PNG];
	}
}
