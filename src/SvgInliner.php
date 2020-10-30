<?php

namespace Vierwd\SvgInliner;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Exception;

class SvgInliner {

	/**
	 * @var DOMDocument
	 */
	protected $fullSvg;

	/**
	 * list of all identifiers of used SVGs.
	 * The identifier is the filename as lowercase without special characters.
	 * @var array
	 */
	protected $usedSvgs = [];

	/**
	 * list of all id-attributes used in all SVGs
	 * @var array
	 */
	protected $usedIDs = [];

	/**
	 * default options. available keys are excludeFromConcatenation, ignoreDuplicateIds, removeComments
	 * @var array
	 */
	protected $defaultOptions = [];

	public function __construct(array $defaultOptions = []) {
		$this->defaultOptions = $defaultOptions;

		$this->fullSvg = new DOMDocument();
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

	/**
	 * render a single SVG file. If the file has the same identifier as another SVG, it will not be loaded from
	 * disk again
	 *
	 * @param string $fileName path to file
	 * @param array $options are identifier, width, height, class, excludeFromConcatenation, ignoreDuplicateIds, removeComments
	 */
	public function renderSVGFile($fileName, array $options = []) {
		if (!file_exists($fileName)) {
			return '';
		}

		$options = $this->processOptions($options);

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
	 * @param array $options are identifier, width, height, class, excludeFromConcatenation, ignoreDuplicateIds, removeComments
	 */
	public function renderSVG($content, array $options = []) {
		$options = $this->processOptions($options);

		$identifier = isset($options['identifier']) ? (string)$options['identifier'] : md5($content);

		if (!isset($this->usedSvgs[$identifier])) {
			$symbol = $this->processSVG($content, $options);
		} else {
			$symbol = $this->usedSvgs[$identifier];
		}

		return $this->renderSymbol($symbol, $options);
	}

	protected function processOptions(array $options = []) {
		$options = $options + $this->defaultOptions;

		// ensure some options are booleans
		$options['external'] = !empty($options['external']);
		$options['excludeFromConcatenation'] = !empty($options['excludeFromConcatenation']);
		$options['ignoreDuplicateIds'] = !empty($options['ignoreDuplicateIds']) || $options['external'];
		$options['removeComments'] = isset($options['removeComments']) ? (bool)$options['removeComments'] : true;

		return $options;
	}

	/**
	 * @param DOMNode $symbol
	 * @param array $options
	 */
	protected function renderSymbol(DOMNode $symbol, array $options) {
		$identifier = $options['identifier'];

		if (!$options['ignoreDuplicateIds']) {
			$this->checkForDuplicateId($identifier, $symbol);
		}

		if (!$options['excludeFromConcatenation'] && !$options['external']) {
			$this->fullSvg->documentElement->appendChild($symbol);
		}

		$document = new DOMDocument();
		$svg = $document->createElementNs('http://www.w3.org/2000/svg', 'svg');
		$document->appendChild($svg);

		if ($options['external']) {
			if (!isset($options['url'])) {
				throw new \Exception('No URL option set. When using the `external` option, you need to supply an URL option as well.', 1556090908);
			}
			$url = $options['url'];
			$urlParts = parse_url($url);
			if (empty($urlParts['fragment'])) {
				// get the fragment
				$XPath = new DOMXPath($symbol->ownerDocument);
				$ids = $XPath->query('./*[@id]/@id', $symbol);
				if (!$ids->length) {
					throw new \Exception('No fragment set. When using the `external` option, either provide a URL fragment or set an ID within the SVG', 1556092343);
				}
				$urlParts['fragment'] = (string)$ids->item(0)->nodeValue;
			}
			if (empty($urlParts['query'])) {
				// generate a cache buster
				$urlParts['query'] = substr(md5($symbol->ownerDocument->saveXML($symbol)), 0, 8);
			}
			$url = $this->buildUrl($urlParts);
			$use = $document->createElement('use');
			$use->setAttribute('xlink:href', $url);
			$svg->appendChild($use);
		} else if (!$options['excludeFromConcatenation']) {
			$use = $document->createElement('use');
			$use->setAttribute('xlink:href', '#' . $identifier);
			$svg->appendChild($use);
		} else {
			// use the element directly
			foreach ($symbol->childNodes as $child) {
				$child = $document->importNode($child, true);
				$svg->appendChild($child);
			}

			foreach ($symbol->attributes as $name => $value) {
				// copy attributes of SVG element to symbol element
				if ($name !== 'xmlns' && $name !== 'id') {
					$svg->setAttribute($name, $value->nodeValue);
				}
			}
		}

		$this->setAttributes($symbol, $svg, $options);

		// make sure there are no short-tags
		$value = $document->saveXml($document->documentElement, LIBXML_NOEMPTYTAG);
		$svgWithNamespace = '<svg xmlns="http://www.w3.org/2000/svg"';
		if (substr($value, 0, strlen($svgWithNamespace)) === $svgWithNamespace) {
			$value = '<svg' . substr($value, strlen($svgWithNamespace));
		}

		return $value;
	}

	/**
	 * copy or set attributes from one SVG onto another
	 */
	protected function setAttributes(DOMNode $from, DOMNode $to, array $options) {
		$width = isset($options['width']) ? (int)$options['width'] : 0;
		$height = isset($options['height']) ? (int)$options['height'] : 0;
		$class = isset($options['class']) ? (string)$options['class'] : '';

		$classes = explode(' ', $class . ' ' . $from->getAttribute('class'));
		$classes = array_map('trim', $classes);
		$classes = array_filter($classes);
		$classes = array_unique($classes);
		$to->setAttribute('class', implode(' ', $classes));

		if ($width || $from->hasAttribute('width')) {
			$to->setAttribute('width', $width ?: $from->getAttribute('width'));
		}
		if ($height || $from->hasAttribute('height')) {
			$to->setAttribute('height', $height ?: $from->getAttribute('height'));
		}
		if ($from->hasAttribute('viewBox')) {
			$to->setAttribute('viewBox', $from->getAttribute('viewBox'));
		}
		if ($from->hasAttribute('preserveAspectRatio')) {
			$to->setAttribute('preserveAspectRatio', $from->getAttribute('preserveAspectRatio'));
		}
	}

	/**
	 * @param string $content
	 * @param array $options
	 */
	protected function processSVG($content, array $options) {
		$identifier = $options['identifier'];

		$document = new DOMDocument();

		if (!@$document->loadXml($content)) {
			throw new Exception('Could not load SVG: ' . $identifier, 1533914743);
		}

		// convert fill="transparent" to fill="none"
		$XPath = new DOMXPath($document);
		$transparentFill = $XPath->query('//*[@fill="transparent"]');
		foreach ($transparentFill as $node) {
			$node->setAttribute('fill', 'none');
		}

		// Remove comments within SVG
		if ($options['removeComments']) {
			$XPath = new DOMXPath($document);
			$comments = $XPath->query('//comment()');
			foreach ($comments as $comment) {
				$comment->parentNode->removeChild($comment);
			}
		}

		// always add "svg" class to the root element
		if ($document->documentElement->hasAttribute('class')) {
			$document->documentElement->setAttribute('class', $document->documentElement->getAttribute('class') . ' svg');
		} else {
			$document->documentElement->setAttribute('class', 'svg');
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

		$this->usedSvgs[$identifier] = $symbol;

		return $symbol;
	}

	protected function checkForDuplicateId($identifier, DOMNode $contextNode) {
		$XPath = new DOMXPath($contextNode->ownerDocument);
		$ids = $XPath->query('.//*[@id]/@id', $contextNode);
		foreach ($ids as $id) {
			if (isset($this->usedIDs[$id->nodeValue])) {
				trigger_error('Duplicate ID within embedded SVG ' . $identifier . '. If this is intentional, add ignoreDuplicateIds=1', E_USER_WARNING);
			}

			$this->usedIDs[$id->nodeValue] = $identifier;
		}
	}

	protected function buildUrl(array $urlParts) {
		// https://www.php.net/manual/en/function.parse-url.php#106731
		$scheme   = isset($urlParts['scheme']) ? $urlParts['scheme'] . '://' : '';
		$host     = isset($urlParts['host']) ? $urlParts['host'] : '';
		$port     = isset($urlParts['port']) ? ':' . $urlParts['port'] : '';
		$user     = isset($urlParts['user']) ? $urlParts['user'] : '';
		$pass     = isset($urlParts['pass']) ? ':' . $urlParts['pass'] : '';
		$pass     = $user || $pass ? "$pass@" : '';
		$path     = isset($urlParts['path']) ? $urlParts['path'] : '';
		$query    = isset($urlParts['query']) ? '?' . $urlParts['query'] : '';
		$fragment = isset($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '';
		return "$scheme$user$pass$host$port$path$query$fragment";
	}
}
