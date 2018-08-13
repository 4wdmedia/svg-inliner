<?php

namespace Vierwd\SvgInliner;

use DOMDocument;
use DOMXPath;
use Exception;

/**
 * @author Robert Vock <robert.vock@4wdmedia.de>
 */
class SvgInliner {

	/**
	 * @var DOMDocument
	 */
	protected $fullSvg;

	/**
	 * list of all identifiers of used SVGs.
	 * The identifier is the filename as lowercase without special characters.
	 */
	protected $usedSvgs = [];

	/**
	 * list of all id-attributes used in all SVGs
	 */
	protected $usedIDs = [];

	/**
	 * default options. available keys are excludeFromConcatenation, ignoreDuplicateIds, removeComments
	 * @var array
	 */
	protected $defaultOptions = [];

	public function __construct(array $defaultOptions = []) {
		$this->defaultOptions = $defaultOptions;

		$this->fullSvg = new DOMDocument;
		$svg = $this->fullSvg->createElementNs('http://www.w3.org/2000/svg', 'svg');
		$svg->setAttribute('hidden', 'hidden');
		$this->fullSvg->appendChild($svg);
	}

	/**
	 * get the rendered full SVG. This is an SVG containing many symbol-elements to reduce
	 * repetition of same SVG code
	 */
	public function renderFullSVG() {
		if (!$this->fullSvg->documentElement->childNodes->length) {
			return '';
		}

		return $this->fullSvg->saveXml($this->fullSvg->documentElement, LIBXML_NOEMPTYTAG);
	}

	public function renderSVGFile($fileName, array $options = []) {
		if (!file_exists($fileName)) {
			return '';
		}

		$options = $options + $this->defaultOptions;

		if (!isset($options['identifier'])) {
			$options['identifier'] = preg_replace('/\W+/u', '-', strtolower(pathinfo($fileName, PATHINFO_FILENAME)));
		}

		if (!isset($this->usedSvgs[$options['identifier']])) {
			$content = trim(file_get_contents($fileName));
			$symbol = $this->processSVG($content, $options);
		} else {
			$symbol = $this->usedSvgs[$options['identifier']];
		}

		return $this->renderSymbol($symbol, $options);
	}

	/**
	 * render a single SVG
	 *
	 * @param string $content SVG content
	 * @return array $options are identifier, width, height, class, excludeFromConcatenation, ignoreDuplicateIds, removeComments
	 * @throws Exception if the SVG is invalid
	 */
	public function renderSVG($content, array $options = []) {
		$options = $options + $this->defaultOptions;

		$identifier = isset($options['identifier']) ? (string)$options['identifier'] : md5($content);

		if (!isset($this->usedSvgs[$identifier])) {
			$symbol = $this->processSVG($content, $options);
		} else {
			$symbol = $this->usedSvgs[$identifier];
		}

		return $this->renderSymbol($symbol, $options);
	}

	/**
	 * @param DOMDocument $symbol
	 * @param array $options
	 */
	protected function renderSymbol($symbol, array $options) {
		$identifier = $options['identifier'];
		$excludeFromConcatenation = !empty($options['excludeFromConcatenation']);
		$width = isset($options['width']) ? (int)$options['width'] : 0;
		$height = isset($options['height']) ? (int)$options['height'] : 0;
		$class = isset($options['class']) ? (string)$options['class'] : '';

		$document = new DOMDocument;
		$svg = $document->createElementNs('http://www.w3.org/2000/svg', 'svg');
		$document->appendChild($svg);

		if (!$excludeFromConcatenation) {
			$use = $document->createElement('use');
			$use->setAttribute('xlink:href', '#' . $identifier);
			$svg->appendChild($use);
		} else {
			$classes = explode(' ', 'svg ' . $identifier . ' ' . $class);
			$classes = array_map('trim', $classes);
			$classes = array_filter($classes);
			$classes = array_unique($classes);
			$svg->setAttribute('class', implode(' ', $classes));

			// use the element directly
			foreach ($symbol->childNodes as $child) {
				$child = $document->importNode($child, true);
				$svg->appendChild($child);
			}
		}

		if ($width || $symbol->hasAttribute('width')) {
			$svg->setAttribute('width', $width ?: $symbol->getAttribute('width'));
		}
		if ($height || $symbol->hasAttribute('height')) {
			$svg->setAttribute('height', $height ?: $symbol->getAttribute('height'));
		}
		if ($symbol->hasAttribute('viewBox')) {
			$svg->setAttribute('viewBox', $symbol->getAttribute('viewBox'));
		}
		if ($symbol->hasAttribute('preserveAspectRatio')) {
			$svg->setAttribute('preserveAspectRatio', $symbol->getAttribute('preserveAspectRatio'));
		}

		// make sure there are no short-tags
		$value = $document->saveXml($document->documentElement, LIBXML_NOEMPTYTAG);
		$svgWithNamespace = '<svg xmlns="http://www.w3.org/2000/svg"';
		if (substr($value, 0, strlen($svgWithNamespace)) === $svgWithNamespace) {
			$value = '<svg' . substr($value, strlen($svgWithNamespace));
		}

		return $value;
	}

	/**
	 * @param string $content
	 * @param array $options
	 * @throws Exception if the SVG is invalid
	 */
	protected function processSVG($content, array $options) {
		$identifier = $options['identifier'];

		$document = new DOMDocument;

		if (!@$document->loadXml($content)) {
			throw new Exception('Could not load SVG: ' . $identifier, 1533914743);
		}

		if (!empty($options['ignoreDuplicateIds'])) {
			$this->checkForDuplicateId($identifier, $document->documentElement);
		}

		// convert fill="transparent" to fill="none"
		$XPath = new DOMXPath($document);
		$transparentFill = $XPath->query('//*[@fill="transparent"]');
		foreach ($transparentFill as $node) {
			$node->setAttribute('fill', 'none');
		}

		// Remove comments within SVG
		$removeComments = isset($options['removeComments']) ? (bool)$options['removeComments'] : true;
		if ($removeComments) {
			$XPath = new DOMXPath($document);
			$comments = $XPath->query('//comment()');
			foreach ($comments as $comment) {
				$comment->parentNode->removeChild($comment);
			}
		}

		// always add the identifier as class name of the root element
		if ($document->documentElement->hasAttribute('class')) {
			$document->documentElement->setAttribute('class', $document->documentElement->getAttribute('class') . ' svg ' . $identifier);
		} else {
			$document->documentElement->setAttribute('class', 'svg ' . $identifier);
		}

		$symbol = $this->fullSvg->createElement('symbol');
		foreach ($document->documentElement->attributes as $name => $value) {
			// copy attributes of SVG element to symbol element
			if ($name !== 'xmlns') {
				$symbol->setAttribute($name, $value->nodeValue);
			}
		}
		$symbol->setAttribute('id', $identifier);
		foreach ($document->documentElement->childNodes as $child) {
			$child = $this->fullSvg->importNode($child, true);
			$symbol->appendChild($child);
		}

		$this->fullSvg->documentElement->appendChild($symbol);

		$this->usedSvgs[$identifier] = $symbol;

		return $symbol;
	}

	protected function checkForDuplicateId($identifier, $contextNode) {
		$XPath = new DOMXPath($contextNode->ownerDocument);
		$ids = $XPath->query('.//*[@id]/@id', $contextNode);
		foreach ($ids as $id) {
			if (isset($this->usedIDs[$id->nodeValue])) {
				throw new Exception('Duplicate ID within embedded SVG ' . $identifier . '. If this is intentional, add ignoreDuplicateIds=1', 1475853018);
			}

			$this->usedIDs[$id->nodeValue] = $identifier;
		}
	}
}
