<?php

declare(strict_types=1);

namespace GutenbergDowngrade\Tests\Unit;

use GutenbergDowngrade\Runtime;
use GutenbergDowngrade\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Runtime::class)]
final class RuntimeTest extends UnitTestCase
{
    /**
     * @return iterable<string, array{bool, bool, ?string, list<string>, bool, bool, bool}>
     */
    public static function decisions(): iterable
    {
        $bypass = Runtime::DEFAULT_BYPASS_PAGES;

        yield 'post editor'                  => [false, true, 'post.php', $bypass, false, false, true];
        yield 'dashboard'                    => [false, true, 'index.php', $bypass, false, false, true];
        yield 'admin-ajax'                   => [false, true, 'admin-ajax.php', $bypass, false, true, true];
        yield 'REST request'                 => [false, false, 'index.php', $bypass, true, false, true];
        yield 'front end'                    => [false, false, 'index.php', $bypass, false, false, false];
        yield 'cron / CLI (no pagenow)'      => [false, false, null, $bypass, false, false, false];
        yield 'site editor'                  => [false, true, 'site-editor.php', $bypass, false, false, true];
        yield 'font library is bypassed'     => [false, true, 'font-library.php', $bypass, false, false, false];
        yield 'connectors are bypassed'      => [false, true, 'options-connectors.php', $bypass, false, false, false];
        yield 'bypass list is configurable'  => [false, true, 'font-library.php', ['widgets.php'], false, false, true];
        yield 'bypass only applies in admin' => [false, false, 'font-library.php', $bypass, true, false, true];
        yield 'kill switch wins'             => [true, true, 'post.php', $bypass, true, true, false];
    }

    /**
     * @param list<string> $bypassPages
     */
    #[DataProvider('decisions')]
    public function testDecide(
        bool $disabled,
        bool $isAdmin,
        ?string $pagenow,
        array $bypassPages,
        bool $isRest,
        bool $doingAjax,
        bool $expected
    ): void {
        self::assertSame($expected, Runtime::decide($disabled, $isAdmin, $pagenow, $bypassPages, $isRest, $doingAjax));
    }

    /**
     * @return iterable<string, array{string, string, string, bool}>
     */
    public static function restPaths(): iterable
    {
        yield 'root install'            => ['/wp-json/wp/v2/posts', '/', 'wp-json', true];
        yield 'bare prefix'             => ['/wp-json', '/', 'wp-json', true];
        yield 'trailing slash prefix'   => ['/wp-json/', '/', 'wp-json', true];
        yield 'subdirectory install'    => ['/blog/wp-json/wp/v2/posts', '/blog/', 'wp-json', true];
        yield 'custom prefix'           => ['/api/wp/v2/posts', '/', 'api', true];
        yield 'front page'              => ['/', '/', 'wp-json', false];
        yield 'a post'                  => ['/hello-world/', '/', 'wp-json', false];
        yield 'prefix as a word prefix' => ['/wp-jsonish/', '/', 'wp-json', false];
        yield 'wrong subdirectory'      => ['/other/wp-json/', '/blog/', 'wp-json', false];
    }

    #[DataProvider('restPaths')]
    public function testPathIsRest(string $requestPath, string $homePath, string $prefix, bool $expected): void
    {
        self::assertSame($expected, Runtime::pathIsRest($requestPath, $homePath, $prefix));
    }

    public function testDefaultBypassPagesAreThe7xOnlyScreens(): void
    {
        self::assertSame(['font-library.php', 'options-connectors.php'], Runtime::DEFAULT_BYPASS_PAGES);
    }
}
