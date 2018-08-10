<?php

namespace Vierwd\SvgInliner\Tests;

use PHPUnit\Framework\TestCase;

use Vierwd\SvgInliner\SvgInliner;

/**
 * @author Robert Vock <robert.vock@4wdmedia.de>
 */
class SvgInlinerTest extends TestCase {

	/**
	 * test if SVG gets transformed to SVG without short-close tags
	 *
	 * @test
	 */
	public function testShortCloseTags() {
		$svgInliner = new SvgInliner([
			'excludeFromConcatenation' => true,
		]);

		$svgWithShortCloseTag = '<svg xmlns="http://www.w3.org/2000/svg"><g/></svg>';
		$expected = '<svg class="svg testcase"><g></g></svg>';
		$processedSvg = $svgInliner->renderSVG($svgWithShortCloseTag, ['identifier' => 'testcase']);
		$this->assertEquals($expected, $processedSvg);
	}
}
