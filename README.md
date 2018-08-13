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
