<?php

namespace Vierwd\SvgInliner\Tests;

use Exception;
use GlobIterator;
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;
use Vierwd\SvgInliner\SvgInliner;

class SvgInlinerTest extends TestCase {

	/**
	 * test if SVG gets transformed to SVG without short-close tags
	 *
	 * @test
	 * @return void
	 */
	public function testShortCloseTags() {
		$svgInliner = new SvgInliner([
			'excludeFromConcatenation' => true,
		]);

		$svgWithShortCloseTag = '<svg xmlns="http://www.w3.org/2000/svg"><g/></svg>';
		$expected = '<svg class="svg" role="img" aria-hidden="true"><g></g></svg>';
		$processedSvg = $svgInliner->renderSVG($svgWithShortCloseTag, ['identifier' => 'testcase']);
		$this->assertEquals($expected, $processedSvg);
	}

	/**
	 * test various files. Put test cases in Fixtures/Input and expected output in Fixtures/Expected
	 *
	 * @test
	 * @return void
	 */
	public function testVariousFiles() {
		foreach (new GlobIterator(__DIR__ . '/Fixtures/Input/*.svg') as $inputFile) {
			/** @var \SplFileInfo $inputFile */
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
	 * @return void
	 */
	public function testDuplicateIds() {
		if (!class_exists(Warning::class)) {
			// older PHPUnit
			$this->markTestSkipped('Warning can not be catched');
			return;
		}

		$svgInliner = new SvgInliner([
			'excludeFromConcatenation' => true,
		]);

		$svgWithId = '<svg xmlns="http://www.w3.org/2000/svg"><g id="test" /></svg>';
		$svgInliner->renderSVG($svgWithId, ['identifier' => 'testcase']);

		$this->expectWarning();
		$svgInliner->renderSVG($svgWithId, ['identifier' => 'testcase2']);
	}

	/**
	 * test if there is an exception when the same file is rendered twice
	 *
	 * @test
	 * @return void
	 */
	public function testDuplicateIdsWithSameFile() {
		if (!class_exists(Warning::class)) {
			// older PHPUnit
			$this->markTestSkipped('Warning can not be catched');
			return;
		}

		$svgInliner = new SvgInliner([
			'excludeFromConcatenation' => true,
		]);

		$svgWithId = '<svg xmlns="http://www.w3.org/2000/svg"><g id="test" /></svg>';
		$svgInliner->renderSVG($svgWithId, ['identifier' => 'testcase']);

		$this->expectWarning();
		$svgInliner->renderSVG($svgWithId, ['identifier' => 'testcase']);
	}

	/**
	 * test inclusion of SVG as external file (with use-tag)
	 *
	 * @test
	 * @return void
	 */
	public function testExternal() {
		$svgInliner = new SvgInliner([
			'excludeFromConcatenation' => true,
		]);

		$externalSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50"><g id="main"/></svg>';
		$expected = '<svg class="svg" viewBox="0 0 50 50" role="img" aria-hidden="true"><use xlink:href="/static/test.svg?db12e4dd#main"></use></svg>';
		$processedSvg = $svgInliner->renderSVG($externalSvg, [
			'identifier' => 'testcase',
			'external' => true,
			'url' => '/static/test.svg',
		]);
		$this->assertEquals($expected, $processedSvg);
	}

	/**
	 * test inclusion of SVG as external file (with use-tag)
	 *
	 * @test
	 * @return void
	 */
	public function testExternalWithCustomUrl() {
		$svgInliner = new SvgInliner([
			'excludeFromConcatenation' => true,
		]);

		$externalSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50"><g id="main"/><g id="second-main" /></svg>';
		$expected = '<svg class="svg" viewBox="0 0 50 50" role="img" aria-hidden="true"><use xlink:href="/static/test.svg?cache-buster#second-main"></use></svg>';
		$processedSvg = $svgInliner->renderSVG($externalSvg, [
			'identifier' => 'testcase',
			'external' => true,
			'url' => '/static/test.svg?cache-buster#second-main',
		]);
		$this->assertEquals($expected, $processedSvg);
	}

	/**
	 * test inclusion of SVG as external file (with use-tag)
	 *
	 * @test
	 * @return void
	 */
	public function testExternalWithoutID() {
		$svgInliner = new SvgInliner([
			'excludeFromConcatenation' => true,
		]);

		$externalSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50"><g /></svg>';
		// phpcs:ignore SlevomatCodingStandard.Exceptions.ReferenceThrowableOnly
		$this->expectException(Exception::class);
		$svgInliner->renderSVG($externalSvg, [
			'identifier' => 'testcase',
			'external' => true,
			'url' => '/static/test.svg',
		]);
	}

}
