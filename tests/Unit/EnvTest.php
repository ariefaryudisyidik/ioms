<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Env;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class EnvTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'env');
        $this->resetLoadedFlag();
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        $this->resetLoadedFlag();
        foreach (['ENVT_PLAIN', 'ENVT_DQ', 'ENVT_SQ', 'ENVT_SPACED', 'ENVT_MIXED', 'ENVT_EXISTING', 'ENVT_BOOL'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    private function resetLoadedFlag(): void
    {
        $flag = new ReflectionProperty(Env::class, 'loaded');
        $flag->setValue(null, false);
    }

    public function testParsesPlainQuotedAndSpacedValuesAndSkipsNoise(): void
    {
        file_put_contents($this->file, implode("\n", [
            '# a comment',
            '',
            'not-a-pair',
            '=novalue-name',
            'ENVT_PLAIN=abc',
            'ENVT_DQ="double quoted"',
            "ENVT_SQ='single quoted'",
            '  ENVT_SPACED  =  padded  ',
            'ENVT_MIXED="unbalanced\'',
        ]));

        Env::load($this->file);

        $this->assertSame('abc', getenv('ENVT_PLAIN'));
        $this->assertSame('double quoted', $_ENV['ENVT_DQ']);
        $this->assertSame('single quoted', $_SERVER['ENVT_SQ']);
        $this->assertSame('padded', getenv('ENVT_SPACED'));
        $this->assertSame('"unbalanced\'', getenv('ENVT_MIXED'));
    }

    public function testExistingEnvironmentVariableIsNotOverwritten(): void
    {
        putenv('ENVT_EXISTING=original');
        file_put_contents($this->file, 'ENVT_EXISTING=fromfile');

        Env::load($this->file);

        $this->assertSame('original', getenv('ENVT_EXISTING'));
    }

    public function testMissingFileIsIgnoredAndLoadOnlyRunsOnce(): void
    {
        Env::load($this->file . '.does-not-exist');
        file_put_contents($this->file, 'ENVT_PLAIN=late');
        Env::load($this->file);

        $this->assertFalse(getenv('ENVT_PLAIN'));
    }

    /**
     * @dataProvider typedValues
     */
    public function testGetCastsKeywordValues(string $raw, mixed $expected): void
    {
        $_ENV['ENVT_BOOL'] = $raw;

        $this->assertSame($expected, Env::get('ENVT_BOOL'));
    }

    public static function typedValues(): array
    {
        return [
            'true' => ['true', true],
            'false' => ['(false)', false],
            'null' => ['null', null],
            'empty' => ['empty', ''],
            'plain' => ['hello', 'hello'],
        ];
    }

    public function testGetReturnsDefaultWhenKeyIsMissing(): void
    {
        $this->assertSame('fallback', Env::get('ENVT_DOES_NOT_EXIST', 'fallback'));
    }
}
