<?php

namespace Toybox\Core\Console;

use Composer\InstalledVersions;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Application;
use Toybox\Core\Console\Contracts\CommandProviderInterface;

final class CommandLoader
{
    public function registerCommands(Application $application): void
    {
        foreach (InstalledVersions::getInstalledPackages() as $packageName) {
            $installPath = InstalledVersions::getInstallPath($packageName);

            if ($installPath === null) {
                continue;
            }

            $composerFile = $installPath . '/composer.json';

            if (! is_file($composerFile)) {
                continue;
            }

            try {
                $composerConfig = json_decode(
                    file_get_contents($composerFile),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException $exception) {
                throw new RuntimeException(
                    sprintf(
                        'Could not read composer.json for package "%s".',
                        $packageName
                    ),
                    previous: $exception
                );
            }

            $providers = $composerConfig['extra']['toybox']['providers'] ?? [];

            foreach ($providers as $providerClass) {
                if (! class_exists($providerClass)) {
                    throw new RuntimeException(
                        sprintf(
                            'Console provider "%s" from package "%s" does not exist.',
                            $providerClass,
                            $packageName
                        )
                    );
                }

                $provider = new $providerClass();

                if (! $provider instanceof CommandProviderInterface) {
                    throw new RuntimeException(
                        sprintf(
                            'Console provider "%s" must implement %s.',
                            $providerClass,
                            CommandProviderInterface::class
                        )
                    );
                }

                foreach ($provider->commands() as $command) {
                    $application->add($command);
                }
            }
        }
    }
}
