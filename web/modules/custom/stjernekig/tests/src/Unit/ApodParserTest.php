<?php

declare(strict_types=1);

namespace Drupal\Tests\stjernekig\Unit;

use Drupal\stjernekig\Client\ApodEntry;
use Drupal\stjernekig\Client\ApodParser;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\stjernekig\Client\ApodParser
 * @group stjernekig
 */
final class ApodParserTest extends UnitTestCase {

  /**
   * The parser under test.
   */
  private ApodParser $parser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->parser = new ApodParser();
  }

  /**
   * @covers ::fromRestItem
   */
  public function testImageEntry(): void {
    $entry = $this->parser->fromRestItem($this->fixture('image-2024-12-25'));

    $this->assertSame('2024-12-25', $entry->date);
    $this->assertSame('Diamond Dust Sky Eye', $entry->title);
    $this->assertSame(ApodEntry::MEDIA_IMAGE, $entry->mediaType);
    $this->assertSame('https://assets.science.nasa.gov/content/dam/science/cds/apod/apod/2024/december/IceHalos_Fous_960.jpg', $entry->imageUrl);
    $this->assertNull($entry->videoUrl);
    $this->assertSame('Image Credit & Copyright', $entry->creditLabel);
    $this->assertSame('Jaroslav Fous', $entry->creditText());
    $this->assertTrue($entry->isCopyrighted());
    $this->assertStringStartsWith('Robert Nemiroff', $entry->authors);
    $this->assertSame('https://apod.nasa.gov/apod/ap241225.html', $entry->legacyUrl);
    $this->assertSame('https://science.nasa.gov/image-article/apod-2024-december-25-diamond-dust-sky-eye/', $entry->sourceUrl);
    $this->assertSame(1232319, $entry->remoteId);

    $this->assertStringStartsWith('Why is there a huge eye in the sky?', $entry->explanationHtml);
    $this->assertStringNotContainsString('Explanation:', $entry->explanationHtml);
    $this->assertStringNotContainsString('Tomorrow', $entry->explanationHtml);
    $this->assertStringNotContainsString('<div', $entry->explanationHtml);
    $this->assertStringNotContainsString('target=', $entry->explanationHtml);
    // Historic apod.nasa.gov links point into this archive instead.
    $this->assertStringContainsString(
      '<a href="/apod/2023-12-20" rel="noopener">Sun</a>',
      $entry->explanationHtml,
    );
    $this->assertStringContainsString('href="https://en.wikipedia.org/wiki/Diamond_dust"', $entry->explanationHtml);
  }

  /**
   * @covers ::fromRestItem
   */
  public function testVideoEntry(): void {
    $entry = $this->parser->fromRestItem($this->fixture('video-2026-10-18'));

    $this->assertSame('2026-10-18', $entry->date);
    $this->assertSame(ApodEntry::MEDIA_VIDEO, $entry->mediaType);
    $this->assertSame('https://www.youtube-nocookie.com/embed/DzrCEm1ZBRY', $entry->videoUrl);
    $this->assertSame('Credit', $entry->creditLabel);
    $this->assertNotSame('', $entry->creditText());
    // Post-move entries have no migration URL; derive the classic one.
    $this->assertSame('https://apod.nasa.gov/apod/ap261018.html', $entry->legacyUrl);
  }

  /**
   * @covers ::fromRestItem
   */
  public function testEarliestEntryUsesHeroFigure(): void {
    $entry = $this->parser->fromRestItem($this->fixture('image-1995-06-16'));

    $this->assertSame('1995-06-16', $entry->date);
    $this->assertSame('Neutron Star Earth', $entry->title);
    $this->assertSame('https://assets.science.nasa.gov/content/dam/science/cds/apod/apod/1995/june/e_lens.gif', $entry->imageUrl);
    $this->assertSame('', $entry->creditLabel);
    $this->assertFalse($entry->isCopyrighted());
    $this->assertNotSame('', $entry->explanationHtml);
  }

  /**
   * @covers ::parseTitle
   * @dataProvider titleProvider
   */
  public function testParseTitle(string $raw, string $expected): void {
    $this->assertSame($expected, $this->parser->parseTitle($raw));
  }

  /**
   * Titles as NASA renders them.
   *
   * @return array<string, array{string, string}>
   *   Raw title and expected result.
   */
  public static function titleProvider(): array {
    return [
      'en dash entity' => ['APOD: 2024 December 25 &#8211; Diamond Dust Sky Eye', 'Diamond Dust Sky Eye'],
      'plain hyphen' => ['APOD: 1995 June 16 - Neutron Star Earth', 'Neutron Star Earth'],
      'em dash' => ['APOD: 2026 October 1 — Comet', 'Comet'],
      'no prefix' => ['Just a title', 'Just a title'],
      'title with its own dash' => [
        'APOD: 2020 May 5 &#8211; M31: The Andromeda Galaxy &#8211; A Closer Look',
        'M31: The Andromeda Galaxy – A Closer Look',
      ],
    ];
  }

  /**
   * @covers ::rewriteLink
   * @dataProvider linkProvider
   */
  public function testRewriteLink(string $href, string $expected): void {
    $this->assertSame($expected, $this->parser->rewriteLink($href));
  }

  /**
   * Links as they appear in explanations.
   *
   * @return array<string, array{string, string}>
   *   Href and expected rewrite.
   */
  public static function linkProvider(): array {
    return [
      'relative classic' => ['ap231220.html', '/apod/2023-12-20'],
      'absolute classic' => ['https://apod.nasa.gov/apod/ap950616.html', '/apod/1995-06-16'],
      'century boundary' => ['ap000101.html', '/apod/2000-01-01'],
      'classic front page' => ['https://apod.nasa.gov/apod/astropix.html', '/apod'],
      'science.nasa.gov relative' => ['/jupiter/', 'https://science.nasa.gov/jupiter/'],
      'external untouched' => ['https://en.wikipedia.org/wiki/Fog', 'https://en.wikipedia.org/wiki/Fog'],
    ];
  }

  /**
   * @covers ::fromRestItem
   */
  public function testRejectsItemWithoutDate(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->parser->fromRestItem(['title' => ['rendered' => 'x'], 'content' => ['rendered' => '']]);
  }

  /**
   * @covers \Drupal\stjernekig\Client\ApodEntry::explanationText
   */
  public function testExplanationTextTruncatesOnWordBoundary(): void {
    $entry = $this->parser->fromRestItem($this->fixture('image-2024-12-25'));
    $short = $entry->explanationText(60);
    $this->assertLessThanOrEqual(61, mb_strlen($short));
    $this->assertStringEndsWith('…', $short);
    $this->assertStringNotContainsString('<', $short);
  }

  /**
   * Load a trimmed real payload from the fixtures directory.
   *
   * @return array<string, mixed>
   *   Decoded JSON.
   */
  private function fixture(string $name): array {
    $json = file_get_contents(__DIR__ . "/fixtures/$name.json");
    $this->assertNotFalse($json);
    $data = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
    $this->assertIsArray($data);
    return $data;
  }

}
