<?php namespace ProcessWire;

class ImagePlaceholdersConfig extends ModuleConfig
{
	/**
	 * {@inheritdoc}
	 */
	public function getDefaults(): array
	{
		return [
			'placeholder_generation_disabled' => 0,
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getInputfields(): InputfieldWrapper
	{
		$inputfields = parent::getInputfields();

		$inputfields->add([
		  'type' => 'InputfieldCheckbox',
		  'name' => 'placeholder_generation_disabled',
		  'label' => __('Disable Placeholder Generation'),
		  'columnWidth' => 100,
		  'defaultValue' => $this->getDefaults()['placeholder_generation_disabled'],
		  'description' => __('Globally disable placeholder image generation for all fields. Existing placeholder images will not be affected.'),
		  'checkedValue' => 1,
		  'uncheckedValue' => 0
		]);

		return $inputfields;
	}
}
