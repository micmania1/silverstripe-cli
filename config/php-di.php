<?php

use RandomLib\Factory as GeneratorFactory;
use RandomLib\Generator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Psr\Container\ContainerInterface;

use micmania1\SilverStripeCli\Application;
use micmania1\SilverStripeCli\Model\Project;
use micmania1\SilverStripeCli\Console\Output;
use micmania1\SilverStripeCli\Console\OutputInterface;
use micmania1\SilverStripeCli\Commands\EnvUp;
use micmania1\SilverStripeCli\Commands\EnvDown;
use micmania1\SilverStripeCli\Commands\EnvStatus;
use micmania1\SilverStripeCli\Commands\GenerateController;
use micmania1\SilverStripeCli\Commands\GenerateModule;
use micmania1\SilverStripeCli\Commands\GenerateTheme;
use micmania1\SilverStripeCli\Commands\GeneratePage;
use micmania1\SilverStripeCli\Commands\HelpCommand;
use micmania1\SilverStripeCli\Commands\ProjectCreate;

// Docker
use Docker\Docker;
use micmania1\SilverStripeCli\Docker\Environment;
use micmania1\SilverStripeCli\Docker\MariaDbService;
use micmania1\SilverStripeCli\Docker\WebService;

$composerBin = implode(DIRECTORY_SEPARATOR, [
    getcwd(),
    'vendor',
    'bin',
    'composer',
]);

$fixturesDir = implode(DIRECTORY_SEPARATOR, [
    dirname(__DIR__),
    'fixtures',
]);

$assetsDir = implode(DIRECTORY_SEPARATOR, [
    dirname(__DIR__),
    'cli-assets'
]);

return [
    'app.name' => 'SilverStripe Cli',
    'app.version' => '0.1-experimental',
    'app.root' => getcwd(),
    'app.fixtures' => $fixturesDir,
    'app.assets' => $assetsDir,
    'composer.bin' => $composerBin,

    Generator::class => function () {
        return (new GeneratorFactory())
            ->getMediumStrengthGenerator();
    },

    Application::class => function (ContainerInterface $container) {
        $application = new Application(DI\get(Generator::class));

        $application->setName(DI\get('app.name'));
        $application->setVersion(DI\get('app.version'));

        // Add all commands
        $application->add($container->get(ProjectCreate::class));
        $application->add($container->get(HelpCommand::class));

        $application->add($container->get(EnvUp::class));
        $application->add($container->get(EnvDown::class));
        $application->add($container->get(EnvStatus::class));

        $application->add($container->get(GenerateController::class));
        $application->add($container->get(GenerateModule::class));
        $application->add($container->get(GeneratePage::class));
        $application->add($container->get(GenerateTheme::class));

        // Set default command
        $application->setDefaultCommand('silverstripe');

        return $application;
    },

    OutputInterface::class => function (ContainerInterface $container) {
        $output = $container->get(Output::class);

        $successStyle = new OutputFormatterStyle('white', 'green');
        $output->getFormatter()->setStyle('success', $successStyle);

        $warningStyle = new OutputFormatterStyle('yellow');
        $output->getFormatter()->setStyle('warning', $warningStyle);

        return $output;
    },

    InputInterface::class => function (ContainerInterface $container) {
        return $container->get(ArgvInput::class);
    },

    Project::class => function (ContainerInterface $container) {
        return new Project($container->get('app.root'));
    },

    Docker::class => DI\object(),

    MariaDbService::class => function (ContainerInterface $container) {
        return new MariaDbService('database-shared', $container->get(Docker::class));
    },

    WebService::class => function (ContainerInterface $container) {
        $project = $container->get(Project::class);
        $name = $project->getName() . '-web';
        return new WebService($name, $container->get(Docker::class));
    }

];
