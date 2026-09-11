<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Tests\Unit;

use GutenbergDowngrade\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use GutenbergDowngrade\Translations;

#[CoversClass(Translations::class)]
final class TranslationsTest extends UnitTestCase
{
    public function testPointsAtTheFileCoreWouldHaveUsedForThePackage(): void
    {
        if (!defined('WP_LANG_DIR')) {
            define('WP_LANG_DIR', '/srv/wp-content/languages');
        }

        $expected = '/srv/wp-content/languages/de_DE-' . md5('wp-includes/js/dist/edit-post.min.js') . '.json';

        self::assertSame($expected, Translations::corePackTranslationFile('wp-edit-post', 'de_DE'));
    }

    public function testOtherTextDomainsPassThrough(): void
    {
        self::assertSame('/x.json', Translations::filter('/x.json', 'wp-edit-post', 'my-plugin'));
        self::assertFalse(Translations::filter(false, 'my-script', 'default'));
    }
}
