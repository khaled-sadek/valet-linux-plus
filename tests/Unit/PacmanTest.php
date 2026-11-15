<?php

namespace Valet\Tests\Unit;

use DomainException;
use Mockery;
use Valet\CommandLine;
use Valet\Contracts\ServiceManager;
use Valet\PackageManagers\Pacman;
use Valet\Tests\TestCase;

class PacmanTest extends TestCase
{
    /**
     * @test
     */
    public function itUsesVersionedAURPackageNamesForPhp(): void
    {
        $pacman = new Pacman(
            Mockery::mock(CommandLine::class),
            Mockery::mock(ServiceManager::class)
        );

        $this->assertSame('php84-fpm', $pacman->getPhpFpmName('8.4'));
        $this->assertSame('php85-fpm', $pacman->getPhpFpmName('8.5'));
        $this->assertSame('/usr/bin/php84', $pacman->getPhpCliPath('8.4'));
        $this->assertSame('/usr/bin/php85', $pacman->getPhpCliPath('8.5'));
        $this->assertSame('php84-', $pacman->getPhpExtensionPrefix('8.4'));
    }

    /**
     * @test
     */
    public function itInstallsVersionedPhpPackagesWithAnAurHelperAsTheUser(): void
    {
        $cli = Mockery::mock(CommandLine::class);
        $pacman = new Pacman($cli, Mockery::mock(ServiceManager::class));

        $cli->shouldReceive('runAsUser')
            ->once()
            ->with('command -v yay || command -v paru')
            ->andReturn('/usr/bin/yay');
        $cli->shouldReceive('runAsUser')
            ->once()
            ->with(
                "'/usr/bin/yay' -S --noconfirm --needed 'php84-fpm' 'php84-cli'",
                Mockery::type(\Closure::class)
            )
            ->andReturn('');
        $cli->shouldNotReceive('run');

        $pacman->installOrFail('php84-fpm php84-cli');
    }

    /**
     * @test
     */
    public function itReportsWhenNoAurHelperIsAvailableForVersionedPhpPackages(): void
    {
        $cli = Mockery::mock(CommandLine::class);
        $pacman = new Pacman($cli, Mockery::mock(ServiceManager::class));

        $cli->shouldReceive('runAsUser')
            ->once()
            ->with('command -v yay || command -v paru')
            ->andReturn('');
        $cli->shouldNotReceive('run');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('An AUR helper (yay or paru) is required');

        $pacman->installOrFail('php84-fpm');
    }
}
