<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ARCH-01 guard: business logic (Service layer) must not depend on PDO,
 * session, or PHP superglobals; persistence stays behind repository interfaces.
 */
final class ArchitectureTest extends TestCase
{
    private const SUPERGLOBALS = ['$_SESSION', '$_SERVER', '$_POST', '$_GET', '$_COOKIE', '$_FILES', '$_REQUEST', '$_ENV', '$GLOBALS'];

    /**
     * @return array<string,list<string>> file => forbidden references found in code (comments ignored)
     */
    private function violations(string $directory, bool $forbidSessionAndGlobals = true): array
    {
        $found = [];
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            foreach (\PhpToken::tokenize((string) file_get_contents($file)) as $token) {
                $text = $token->text;
                $isPdo = $token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && preg_match('/(^|\\\\)(PDO|PDOStatement)$/', $text) === 1;
                $isCore = $token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && str_contains($text, 'App\\Core\\');
                $isGlobal = $token->is(T_VARIABLE) && in_array($text, self::SUPERGLOBALS, true);
                if ($isPdo || ($forbidSessionAndGlobals && ($isCore || $isGlobal))) {
                    $found[basename($file)][] = $text;
                }
            }
        }

        return $found;
    }

    public function testServicesDoNotDependOnPdoSessionOrSuperglobals(): void
    {
        $this->assertSame([], $this->violations(dirname(__DIR__, 2) . '/app/Service'));
    }

    public function testGuardActuallyDetectsForbiddenReferences(): void
    {
        $dir = sys_get_temp_dir() . '/arch-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/Bad.php', "<?php\nuse PDO;\nuse App\\Core\\Session;\n// \$_SESSION in a comment is fine\nfinal class Bad { public function f(PDO \$p) { return \$_POST; } }\n");
        file_put_contents($dir . '/Good.php', "<?php\nfinal class Good { public function f(): int { return 1; } }\n");

        $found = $this->violations($dir);

        $this->assertArrayHasKey('Bad.php', $found);
        $this->assertArrayNotHasKey('Good.php', $found);
        $this->assertContains('$_POST', $found['Bad.php']);
        $this->assertNotContains('$_SESSION', $found['Bad.php']);
        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    }

    public function testRepositoryInterfacesDoNotExposePdo(): void
    {
        $found = [];
        foreach (glob(dirname(__DIR__, 2) . '/app/Repository/*Interface.php') ?: [] as $file) {
            if (preg_match('/\bPDO\b/', (string) preg_replace('#/\*.*?\*/|//[^\n]*#s', '', (string) file_get_contents($file))) === 1) {
                $found[] = basename($file);
            }
        }

        $this->assertSame([], $found);
    }
}
