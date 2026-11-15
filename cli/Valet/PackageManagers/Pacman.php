<?php

namespace Valet\PackageManagers;

use ConsoleComponents\Writer;
use DomainException;
use Valet\CommandLine;
use Valet\Contracts\PackageManager;
use Valet\Contracts\ServiceManager;

class Pacman implements PackageManager
{
    /**
     * @var CommandLine
     */
    public $cli;
    /**
     * @var ServiceManager
     */
    public $serviceManager;
    private const PACKAGES = [
        'redis' => 'redis',
        'mysql' => 'mysql',
        'mariadb' => 'mariadb',
    ];

    /**
     * Create a new Apt instance.
     */
    public function __construct(CommandLine $cli, ServiceManager $serviceManager)
    {
        $this->cli = $cli;
        $this->serviceManager = $serviceManager;
    }

    /**
     * Get array of installed packages.
     */
    public function packages(string $package): array
    {
        $query = "pacman -Qqs {$package}";

        return explode(PHP_EOL, $this->cli->run($query));
    }

    /**
     * Determine if the given package is installed.
     */
    public function installed(string $package): bool
    {
        return in_array($package, $this->packages($package));
    }

    /**
     * Ensure that the given package is installed.
     */
    public function ensureInstalled(string $package): void
    {
        if (!$this->installed($package)) {
            $this->installOrFail($package);
        }
    }

    /**
     * Install the given package and throw an exception on failure.
     */
    public function installOrFail(string $package): void
    {
        Writer::twoColumnDetail($package, 'Installing');

        $targets = preg_split('/\s+/', trim($package), -1, PREG_SPLIT_NO_EMPTY);
        $packageArguments = implode(' ', array_map('escapeshellarg', $targets ?: []));

        if ($this->isVersionedPhpPackage($package)) {
            $helper = trim($this->cli->runAsUser('command -v yay || command -v paru'));

            if ($helper === '') {
                throw new DomainException(
                    'An AUR helper (yay or paru) is required to install ['.$package.'].'
                );
            }

            $this->cli->runAsUser(
                escapeshellarg($helper).' -S --noconfirm --needed '.$packageArguments,
                function ($exitCode, $errorOutput) use ($package) {
                    Writer::error(\sprintf('%s: %s', $exitCode, $errorOutput));

                    throw new DomainException('AUR helper was unable to install ['.$package.'].');
                }
            );

            return;
        }

        $this->cli->run(
            'pacman --noconfirm --needed -S '.$packageArguments,
            function ($exitCode, $errorOutput) use ($package) {
                Writer::error(\sprintf('%s: %s', $exitCode, $errorOutput));

                throw new DomainException('Pacman was unable to install ['.$package.'].');
            }
        );
    }

    /**
     * Configure package manager on valet install.
     */
    public function setup(): void
    {
        // Nothing to do
    }

    /**
     * Determine if package manager is available on the system.
     */
    public function isAvailable(): bool
    {
        try {
            $output = $this->cli->run('which pacman', function () {
                throw new DomainException('Pacman not available');
            });

            return $output != '';
        } catch (DomainException $e) {
            return false;
        }
    }

    /**
     * Determine php fpm package name.
     */
    public function getPhpFpmName(string $version): string
    {
        $version = preg_replace('~[^\d]~', '', $version);

        return 'php'.$version.'-fpm';
    }

    /**
     * Determine php cli executable path.
     */
    public function getPhpCliPath(string $version): string
    {
        $version = preg_replace('~[^\d]~', '', $version);

        return '/usr/bin/php'.$version;
    }

    /**
     * Get the `ca-certificates` directory
     */
    public function getCaCertificatesPath(): string
    {
        return '/usr/share/ca-certificates';
    }

    /**
     * Determine php extension pattern.
     */
    public function getPhpExtensionPrefix(string $version): string
    {
        $version = preg_replace('~[^\d]~', '', $version);
        $pattern = 'php{VERSION_WITHOUT_DOT}-';
        return str_replace('{VERSION_WITHOUT_DOT}', $version, $pattern);
    }

    /**
     * Restart dnsmasq in Ubuntu.
     */
    public function restartNetworkManager(): void
    {
        $this->serviceManager->restart('NetworkManager');
    }

    /**
     * Get package name by service.
     */
    public function packageName(string $name): string
    {
        if (isset(self::PACKAGES[$name])) {
            return self::PACKAGES[$name];
        }
        throw new \InvalidArgumentException(\sprintf('Package not found by %s', $name));
    }

    private function isVersionedPhpPackage(string $package): bool
    {
        $targets = preg_split('/\s+/', trim($package), -1, PREG_SPLIT_NO_EMPTY);

        if (!$targets) {
            return false;
        }

        foreach ($targets as $target) {
            if (!preg_match('/^php\d{2,3}-/', $target)) {
                return false;
            }
        }

        return true;
    }
}
