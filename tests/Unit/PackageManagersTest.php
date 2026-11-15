<?php

namespace Valet\Tests\Unit;

use Closure;
use ConsoleComponents\Writer;
use DomainException;
use InvalidArgumentException;
use Mockery;
use Valet\CommandLine;
use Valet\Contracts\PackageManager;
use Valet\Contracts\ServiceManager;
use Valet\Filesystem;
use Valet\PackageManagers\Apt;
use Valet\PackageManagers\Dnf;
use Valet\PackageManagers\Eopkg;
use Valet\PackageManagers\PackageKit;
use Valet\PackageManagers\Pacman;
use Valet\PackageManagers\Yum;
use Valet\ServiceManagers\Systemd;
use Valet\Tests\TestCase;
use Valet\Valet;

use function Valet\resolve;
use function Valet\swap;

class PackageManagersTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string<PackageManager>, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string, 8: string}>
     */
    public function distroMetadataProvider(): array
    {
        return [
            'apt' => [
                Apt::class,
                'php8.3-fpm',
                'php8.4-fpm',
                '/usr/bin/php8.4',
                'php8.4-',
                '/usr/share/ca-certificates',
                'redis-server',
                'mysql-server',
                'mariadb-server',
            ],
            'dnf' => [
                Dnf::class,
                'php-fpm',
                'php8.4-fpm',
                '/usr/bin/php8.4',
                'php8.4-',
                '/usr/share/pki/ca-trust-source',
                'redis',
                'mysql-server',
                'mariadb-server',
            ],
            'pacman' => [
                Pacman::class,
                'php83-fpm',
                'php84-fpm',
                '/usr/bin/php84',
                'php84-',
                '/usr/share/ca-certificates',
                'redis',
                'mysql',
                'mariadb',
            ],
            'yum' => [
                Yum::class,
                'php8.3-fpm',
                'php8.4-fpm',
                '/usr/bin/php8.4',
                'php8.4-',
                '/usr/share/pki/ca-trust-source',
                'redis',
                'mysql-server',
                'mariadb-server',
            ],
            'packagekit' => [
                PackageKit::class,
                'php8.3-fpm',
                'php8.4-fpm',
                '/usr/bin/php8.4',
                'php8.4-',
                '/usr/share/pki/ca-trust-source',
                'redis-server',
                'mysql-server',
                'mariadb-server',
            ],
            'eopkg' => [
                Eopkg::class,
                'php8.3-fpm',
                'php8.4-fpm',
                '/usr/bin/php8.4',
                'php8.4-',
                '/usr/share/ca-certificates',
                'redis-server',
                'mysql-server',
                'mariadb-server',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider distroMetadataProvider
     * @param class-string<PackageManager> $managerClass
     */
    public function itProvidesExpectedMetadataAndPackageMappingsForEachDistro(
        string $managerClass,
        string $expectedPhp83Fpm,
        string $expectedPhp84Fpm,
        string $expectedPhp84Cli,
        string $expectedPhp84ExtPrefix,
        string $expectedCaPath,
        string $expectedRedisPackage,
        string $expectedMysqlPackage,
        string $expectedMariadbPackage
    ): void {
        $pm = new $managerClass(
            Mockery::mock(CommandLine::class),
            Mockery::mock(ServiceManager::class)
        );

        $this->assertSame($expectedPhp83Fpm, $pm->getPhpFpmName('8.3'));
        $this->assertSame($expectedPhp84Fpm, $pm->getPhpFpmName('8.4'));
        $this->assertSame($expectedPhp84Cli, $pm->getPhpCliPath('8.4'));
        $this->assertSame($expectedPhp84ExtPrefix, $pm->getPhpExtensionPrefix('8.4'));
        $this->assertSame($expectedCaPath, $pm->getCaCertificatesPath());
        $this->assertSame($expectedRedisPackage, $pm->packageName('redis'));
        $this->assertSame($expectedMysqlPackage, $pm->packageName('mysql'));
        $this->assertSame($expectedMariadbPackage, $pm->packageName('mariadb'));
    }

    /**
     * @return array<string, array{0: class-string<PackageManager>, 1: string, 2: string}>
     */
    public function availabilityCommandProvider(): array
    {
        return [
            'apt' => [Apt::class, 'which apt-get', '/usr/bin/apt-get'],
            'dnf' => [Dnf::class, 'which dnf', '/usr/bin/dnf'],
            'pacman' => [Pacman::class, 'which pacman', '/usr/bin/pacman'],
            'yum' => [Yum::class, 'which yum', '/usr/bin/yum'],
            'packagekit' => [PackageKit::class, 'which pkcon', '/usr/bin/pkcon'],
            'eopkg' => [Eopkg::class, 'which eopkg', '/usr/bin/eopkg'],
        ];
    }

    /**
     * @test
     * @dataProvider availabilityCommandProvider
     * @param class-string<PackageManager> $managerClass
     */
    public function itDetectsAvailabilityForEachPackageManager(
        string $managerClass,
        string $expectedWhichCommand,
        string $resolvedBinary
    ): void {
        $cli = Mockery::mock(CommandLine::class);
        $pm = new $managerClass($cli, Mockery::mock(ServiceManager::class));

        $cli->shouldReceive('run')
            ->once()
            ->with($expectedWhichCommand, Mockery::type(Closure::class))
            ->andReturn($resolvedBinary);
        $this->assertTrue($pm->isAvailable());

        $cli->shouldReceive('run')
            ->once()
            ->with($expectedWhichCommand, Mockery::type(Closure::class))
            ->andReturn('');
        $this->assertFalse($pm->isAvailable());

        $cli->shouldReceive('run')
            ->once()
            ->with($expectedWhichCommand, Mockery::type(Closure::class))
            ->andReturnUsing(function (string $command, callable $onError): string {
                $onError(1, 'not found');

                return '';
            });
        $this->assertFalse($pm->isAvailable());
    }

    /**
     * @return array<string, array{0: class-string<PackageManager>, 1: string, 2: string, 3: string}>
     */
    public function installCommandProvider(): array
    {
        return [
            'apt' => [
                Apt::class,
                "dpkg -l nginx | grep '^ii' | sed 's/\s\+/ /g' | cut -d' ' -f2",
                'apt-get install -y nginx',
                'Apt was unable to install [nginx].',
            ],
            'dnf' => [
                Dnf::class,
                "dnf list installed nginx | grep nginx | sed 's_  _\\t_g' | sed 's_\\._\\t_g' | cut -f 1",
                'dnf install -y nginx',
                'Dnf was unable to install [nginx].',
            ],
            'pacman' => [
                Pacman::class,
                'pacman -Qqs nginx',
                "pacman --noconfirm --needed -S 'nginx'",
                'Pacman was unable to install [nginx].',
            ],
            'yum' => [
                Yum::class,
                "yum list installed nginx | grep nginx | sed 's_  _\\t_g' | sed 's_\\._\\t_g' | cut -f 1",
                'yum install -y nginx',
                'Yum was unable to install [nginx].',
            ],
            'packagekit' => [
                PackageKit::class,
                "pkcon search nginx | grep '^In' | sed 's/\s\+/ /g' | cut -d' ' -f2 | sed 's/-[0-9].*//'",
                'pkcon install -y nginx',
                'PackageKit was unable to install [nginx].',
            ],
            'eopkg' => [
                Eopkg::class,
                "dpkg -l nginx | grep '^ii' | sed 's/\s\+/ /g' | cut -d' ' -f2",
                'eopkg install -y nginx',
                'Eopkg was unable to install [nginx].',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider installCommandProvider
     * @param class-string<PackageManager> $managerClass
     */
    public function itChecksInstalledPackagesAndInstallsWhenMissing(
        string $managerClass,
        string $expectedInstalledQuery,
        string $expectedInstallCommand,
        string $expectedFailureMessage
    ): void {
        Writer::fake();

        $cli = Mockery::mock(CommandLine::class);
        $pm = new $managerClass($cli, Mockery::mock(ServiceManager::class));

        $cli->shouldReceive('run')
            ->once()
            ->with($expectedInstalledQuery)
            ->andReturn('nginx');
        $this->assertTrue($pm->installed('nginx'));

        $cli->shouldReceive('run')
            ->once()
            ->with($expectedInstalledQuery)
            ->andReturn('');
        $cli->shouldReceive('run')
            ->once()
            ->with($expectedInstallCommand, Mockery::type(Closure::class))
            ->andReturn('');
        $pm->ensureInstalled('nginx');

        $cli->shouldReceive('run')
            ->once()
            ->with($expectedInstallCommand, Mockery::type(Closure::class))
            ->andReturnUsing(function (string $command, callable $onError): string {
                $onError(1, 'failed');

                return '';
            });

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage($expectedFailureMessage);

        $pm->installOrFail('nginx');
    }

    /**
     * @return array<string, array{0: class-string<PackageManager>}>
     */
    public function packageManagerClassesProvider(): array
    {
        return [
            'apt' => [Apt::class],
            'dnf' => [Dnf::class],
            'pacman' => [Pacman::class],
            'yum' => [Yum::class],
            'packagekit' => [PackageKit::class],
            'eopkg' => [Eopkg::class],
        ];
    }

    /**
     * @test
     * @dataProvider packageManagerClassesProvider
     * @param class-string<PackageManager> $managerClass
     */
    public function itThrowsWhenUnknownServicePackageIsRequested(string $managerClass): void
    {
        $pm = new $managerClass(
            Mockery::mock(CommandLine::class),
            Mockery::mock(ServiceManager::class)
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Package not found by unknown');

        $pm->packageName('unknown');
    }

    /**
     * @test
     * @dataProvider packageManagerClassesProvider
     * @param class-string<PackageManager> $expectedManagerClass
     */
    public function itSelectsExpectedPackageManagerDuringEnvironmentSetup(string $expectedManagerClass): void
    {
        $systemd = Mockery::mock(Systemd::class);
        $systemd->shouldReceive('isAvailable')->once()->andReturn(true);
        swap(Systemd::class, $systemd);

        $candidates = [
            Apt::class,
            Dnf::class,
            Pacman::class,
            Yum::class,
            PackageKit::class,
            Eopkg::class,
        ];

        $reachedTarget = false;
        foreach ($candidates as $candidate) {
            $mock = Mockery::mock($candidate);
            if ($candidate === $expectedManagerClass) {
                $mock->shouldReceive('isAvailable')->once()->andReturn(true);
                $reachedTarget = true;
            } elseif (!$reachedTarget) {
                $mock->shouldReceive('isAvailable')->once()->andReturn(false);
            }
            swap($candidate, $mock);
        }

        $valet = new Valet(new CommandLine(), new Filesystem());
        $valet->environmentSetup();

        $this->assertInstanceOf(Systemd::class, resolve(ServiceManager::class));
        $this->assertInstanceOf($expectedManagerClass, resolve(PackageManager::class));
    }

    /**
     * @test
     */
    public function itThrowsWhenNoPackageManagerIsAvailableDuringEnvironmentSetup(): void
    {
        $systemd = Mockery::mock(Systemd::class);
        $systemd->shouldReceive('isAvailable')->once()->andReturn(true);
        swap(Systemd::class, $systemd);

        foreach ([Apt::class, Dnf::class, Pacman::class, Yum::class, PackageKit::class, Eopkg::class] as $candidate) {
            $mock = Mockery::mock($candidate);
            $mock->shouldReceive('isAvailable')->once()->andReturn(false);
            swap($candidate, $mock);
        }

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No compatible package manager found.');

        $valet = new Valet(new CommandLine(), new Filesystem());
        $valet->environmentSetup();
    }
}
