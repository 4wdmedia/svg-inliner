<?php

namespace Vierwd\SvgInliner\Tests;

use Exception;
use GlobIterator;
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

	/**
	 * test various files. Put test cases in Fixtures/Input and expected output in Fixtures/Expected
	 *
	 * @test
	 */
	public function testVariousFiles() {
		foreach (new GlobIterator(__DIR__ . '/Fixtures/Input/*.svg') as $inputFile) {
			$inputFilename = $inputFile->getFilename();
			$outputFile = __DIR__ . '/Fixtures/Expected/' . $inputFilename;

			if (!file_exists($outputFile)) {
				$this->assertFileExists($outputFile, 'Expected fixture for Input svg does not exist');
				continue;
			}

			$svgInliner = new SvgInliner([
				'excludeFromConcatenation' => true,
			]);

			$output = $svgInliner->renderSVGFile($inputFile);
			$expected = file_get_contents($outputFile);
			$this->assertEquals($expected, $output);
		}
	}

	/**
	 * test if there is an exception when files with duplicate id-attributes are rendered
	 *
	 * @test
	 */
	public function testDuplicateIds() {
		$svgInliner = new SvgInliner([
			'excludeFromConcatenation' => true,
		]);

		if (method_exists($this, 'expectException')) {
			$this->expectException(Exception::class);
		} else {
			$this->setExpectedException(Exception::class);
		}

		$svgWithId = '<svg xmlns="http://www.w3.org/2000/svg"><g id="test"></svg>';
		$processedSvg = $svgInliner->renderSVG($svgWithId, ['identifier' => 'testcase']);
		$processedSvg = $svgInliner->renderSVG($svgWithId, ['identifier' => 'testcase2']);
	}
}
