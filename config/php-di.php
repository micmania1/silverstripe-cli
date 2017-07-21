<?php

use function DI\object;
use function DI\get;

use RandomLib\Factory as GeneratorFactory;
use RandomLib\Generator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Filesystem\Filesystem;
use Psr\Container\ContainerInterface;
use micmania1\SilverStripeCli\Application;
use micmania1\SilverStripeCli\Model\Project;
use micmania1\SilverStripeCli\EnvironmentInterface;
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
    'app.dbName' => 'database-shared',
    'app.root' => getcwd(),
    'app.fixtures' => function(ContainerInterface $container) {
        return $container->get('app.root') . DIRECTORY_SEPARATOR . 'fixtures';
    },
    'app.assets' => function(ContainerInterface $container) {
        return $container->get('app.root') . DIRECTORY_SEPARATOR . 'cli-assets';
    },
    'composer.bin' => function(ContainerInterface $container) {
        return implode(DIRECTORY_SEPARATOR, [
            $container->get('app.root'),
            'vendor',
            'bin',
            'composer',
        ]);
    },

    Generator::class => function() {
        return (new GeneratorFactory())
            ->getMediumStrengthGenerator();
    },

    Application::class => function(ContainerInterface $container) {
        $application = new Application(get(Generator::class));

        $application->setName($container->get('app.name'));
        $application->setVersion($container->get('app.version'));

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

    OutputInterface::class => function(ContainerInterface $container) {
        $output = $container->get(Output::class);

        $successStyle = new OutputFormatterStyle('white', 'green');
        $output->getFormatter()->setStyle('success', $successStyle);

        $warningStyle = new OutputFormatterStyle('yellow');
        $output->getFormatter()->setStyle('warning', $warningStyle);

        return $output;
    },

    InputInterface::class => function(ContainerInterface $container) {
        return $container->get(ArgvInput::class);
    },

    EnvironmentInterface::class => function(ContainerInterface $container) {
        return $container->get(Environment::class);
    },

    ProjectCreate::class => object()
        ->constructor(get(Filesystem::class), get('app.root')),

    Project::class => object()
        ->constructor(get('app.root')),

    MariaDbService::class => object()
        ->constructor(get('app.dbName'), get(Docker::class)),

    WebService::class => function(ContainerInterface $container) {
        $project = $container->get(Project::class);
        $name = $project->getName() . '-web';

        return new WebService($name, $container->get(Docker::class));
    },
];
