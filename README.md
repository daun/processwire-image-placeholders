# ProcessWire Image Placeholders

A ProcessWire module to generate image placeholders for smoother lazyloading.

## Why use image placeholders?

Low-Quality Image Placeholders (LQIP) are used to improve the perceived performance of sites by
displaying a small, low-quality version of an image while the high-quality version is being loaded.
The LQIP technique is often used in combination with lazy loading.

## How does it work

This module will automatically generate an image placeholder for each image that is uploaded to
fields configured to use them. In your frontend templates, you can access the image placeholder as
a data URI string to display while the high-quality image is loading. See below for markup examples.

## Placeholder types

Currently, the module supports generating three types of image placeholders. The default is
`ThumbHash`.

- [BlurHash](https://blurha.sh/): the original format developed by Wolt
- [ThumbHash](https://evanw.github.io/thumbhash/): a newer format with better color rendering and alpha channel support
- ProcessWire: generate a tiny variation of the image and cache it

## Installation

Install the module from the root of your ProcessWire installation.

```sh
composer require daun/processwire-image-placeholders
```

Open the admin panel of your site and navigate to `Modules` → `Site` → `ImagePlaceholders` to finish installation.

## Configuration

You'll need to configure your image fields to generate image placeholders.

`Setup` → `Fields` → `[images]` → `Details` → `Image placeholders`

## Usage

Accessing an image's `lqip` property will return a data uri string of its placeholder. Using it as
a method allows setting a custom width and/or height of the placeholder.

```php
$page->image->lqip;           // data:image/png;base64,R0lGODlhEAAQAMQAA
$page->image->lqip(300);      // 300 x auto
$page->image->lqip(300, 200); // 300 x 200px
```

Depending on your lazyloading technique, you can either use this as image `src` or render it as a
separate image.

```php
<img src="<?= $page->image->lqip ?>" data-src="<?= $page->image->url ?>">
```

## Support

Please [open an issue](https://github.com/daun/processwire-image-placeholders/issues/new) for support.

## License

[MIT](./LICENCE)
