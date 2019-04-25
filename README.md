# svg-inliner
Utility functions to inline SVGs in PHP Projects

```
composer require 'vierwd/svg-inliner'
```

This library can be used to inline SVGs in PHP project. It processes the SVGs and changes some of the content:

- `fill="transparent"` should be `fill="none"`
- Short-close tags can be problematic in IE 9. That's why `<path d="..." />` becomes `<path d="..."></path>`.
- Comments are removed
- class-attributes are added on SVG-element with the filename as the class-name

It's also possible to combine SVGs and only output `<use>` tags, which reference an SVG. This makes sense, if one SVG is used multiple times. But you loose the ability to style each tag individually.

## Usage

```php
$svgInliner = new SvgInliner($defaultOptions);

// render a single SVG file and output a <use>-tag
$output = $svgInliner->renderSVGFile($pathToSvgFile, $options);

// render all SVGs which are references by <use>-tags
$allSVGs = $svgInliner->renderFullSVG();

// render a single SVG and output directly
$output = $svgInliner->renderSVGFile($pathToSvgFile, ['excludeFromConcatenation' => true]);
```

## Options

- `excludeFromConcatenation`: output the SVG directly instead of the `<use>`-tag. Available as default option in constructor. Defaults to false.
- `ignoreDuplicateIds`: Do not throw an exception when two SVGs use the same id-attributes. Available as default option in constructor. Defaults to false.
- `removeComments`: Remove all XML-comments. Available as default option in constructor. Defaults to true.

- `identifier`: Identifier for the SVG. If the same identifier is used, the file will not be loaded again, but used from cache. Defaults to the lowercase filename (with special chars transformed to dash.)
- `width`: Width attribute for SVG. Defaults to none and will leave attribute as-is.
- `height`: Height attribute for SVG. Defaults to none and will leave attribute as-is.
- `class`: Additional classes for SVG-tag. By default `.svg` and `.svg-identifier` are added.
- `external`: Embed the SVG as external `<use>`-tag.
- `url`: URL for the `external` SVG

## External SVGs

It is possible to include SVGs as links to external SVG files:

```php
$svgInliner = new SvgInliner($defaultOptions);

// render a single SVG file and output a <use>-tag with an external file
$output = $svgInliner->renderSVGFile($pathToSvgFile, [
	'external' => true,
	'url' => '/static/test.svg',
]);
```

**Output:**

```xml
<svg width="50" height="50" viewBox="0 0 50 50">
	<use xlink:href="/static/test.svg?912ec803#main" width="50" height="50" />
</svg>
```

This allows to style *some* parts of the SVG using CSS, but allows for the SVG to still be cachable. You can use `fill` and `stroke` for all parts of the SVG which do *not* have fill or stroke set. You can use `color` to set the color which is used as `currentColor` within the SVG.

**Attention:**

- IE 11 does not support external SVGs in `<use>`-tags. You need to use a polyfill like [svgxuse](https://github.com/Keyamoon/svgxuse), if you need IE 11 support.
- The SVG needs a tag with an `id` attribute. If the `url` option does not contain a `#hash`, SvgInliner automatically uses the first ID it finds within the SVG. If no ID is found, an exception is thrown.
