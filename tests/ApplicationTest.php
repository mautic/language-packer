<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers Application
 */
final class ApplicationTest extends TestCase
{
    /**
     * @dataProvider invalidTranslationsProvider
     */
    public function testInvalidTranslationCheck($file, $shouldFail = false)
    {   
        if($shouldFail) {
            $this->expectException(DomainException::class);
        }
        $app = new Mautic\Application();
        $this->assertNull($app->ensureFileValid($file));
    }
    public function invalidTranslationsProvider()
    {
        $files = glob(dirname(__FILE__).DIRECTORY_SEPARATOR."initests/invalidTranslations*.ini");
        $fails = array_map(function($v) {return [$v, true];}, $files);
        $files = glob(dirname(__FILE__).DIRECTORY_SEPARATOR."initests/validTranslations*.ini");
        $successes = array_map(function($v) {return [$v, false];}, $files);
        return array_merge($fails,$successes);
    }
    /**
     * @dataProvider escapeQuotesProvider
     */
    public function testEscapeQuotes($file, $escapedFile)
    {   
        $app = new Mautic\Application();
        $escaped = $app->escapeQuotes(file_get_contents($file));
        $this->assertEquals(file_get_contents($escapedFile), $escaped);
    }
    public function escapeQuotesProvider()
    {
        $files = glob(dirname(__FILE__).DIRECTORY_SEPARATOR."initests/quoteTest*-in.ini");
        $arr = array_map(function($v) {return [$v, str_replace('-in.','-out.',$v)];}, $files);
        return $arr;
    }
}
