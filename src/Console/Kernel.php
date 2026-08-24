<?php

namespace Toybox\Core\Console;

use Exception;
use Symfony\Component\Console\Application;
use Toybox\Core\Console\Commands\ExportBlockCommand;
use Toybox\Core\Console\Commands\ImagesConvertCommand;
use Toybox\Core\Console\Commands\InspireCommand;
use Toybox\Core\Console\Commands\MakeBlockCommand;
use Toybox\Core\Console\Commands\MakePatternCommand;
use Toybox\Core\Console\Commands\MakePostTypeCommand;
use Toybox\Core\Console\Commands\MakeShortcodeCommand;
use Toybox\Core\Theme;

// Directory separator string
const DS = DIRECTORY_SEPARATOR;

// Set the Toybox core directory
const TOYBOX_CORE = __DIR__ . DS . ".." . DS;

class Kernel
{
    /**
     * @var Application $application The application.
     */
    private Application $application;

    /**
     * Runs the console application.
     *
     * @return void
     * @throws Exception
     */
    public function run(): void
    {
        // Set up the application, and register the commands
        $this->bootstrap();
        $this->registerCommands();

        // Run
        $this->application->run();
    }

    /**
     * Sets the application up.
     *
     * @return void
     */
    private function bootstrap(): void
    {
        // Create the application
        $this->application = new Application("Toybox", Theme::VERSION);
    }

    /**
     * Registers the console commands.
     *
     * @return void
     */
    private function registerCommands(): void
    {
        $this->application->add(new ExportBlockCommand());
        $this->application->add(new ImagesConvertCommand());
        $this->application->add(new InspireCommand());
        $this->application->add(new MakeBlockCommand());
        $this->application->add(new MakeShortcodeCommand());
        $this->application->add(new MakePatternCommand());

        // Load other commands
        new CommandLoader()->registerCommands($this->application);
    }
}
