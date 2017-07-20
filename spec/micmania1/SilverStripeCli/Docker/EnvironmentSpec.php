<?php

namespace spec\micmania1\SilverStripeCli\Docker;

use micmania1\SilverStripeCli\Docker\Environment;
use micmania1\SilverStripeCli\Docker\MariaDbService;
use micmania1\SilverStripeCli\Docker\WebService;
use micmania1\SilverStripeCli\Model\Project;
use micmania1\SilverStripeCli\Console\OutputInterface;
use RandomLib\Generator;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class EnvironmentSpec extends ObjectBehavior
{
    protected $project;
    protected $generator;
    protected $dbService;
    protected $webService;

    function let(
        Project $project,
        Generator $generator,
        MariaDbService $dbService,
        WebService $webService
    ) {
        $this->project = $project;
        $this->generator = $generator;
        $this->dbService = $dbService;
        $this->webService = $webService;

        $this->beConstructedWith($project, $generator, $dbService, $webService);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(Environment::class);
    }

    /**
     * When the database service fails to build, the env build should fail
     */
    function it_fails_build_when_dbservice_build_fails(OutputInterface $output)
    {
        $this->dbService->containerExists()->willReturn(false);
        $this->dbService->build($output, Argument::any())
            ->shouldBeCalled()
            ->willReturn(false);

        $this->build($output)->shouldReturn(false);
    }

    /**
     * When the web service fails to build, the env build should fail
     */
    function it_fails_build_when_webservice_build_fails(OutputInterface $output)
    {
        $this->dbService->containerExists()->willReturn(true);

        $this->webService->containerExists()->willReturn(false);
        $this->webService->build($output, Argument::any())
            ->shouldBeCalled()
            ->willReturn(false);

        $this->build($output)->shouldReturn(false);
    }

    /**
     * When both services build, the environment build should succeed
     */
    function it_should_build(OutputInterface $output)
    {
        $this->dbService->containerExists()->willReturn(false);
        $this->dbService->build($output, Argument::any())
            ->shouldBeCalled()
            ->willReturn(true);

        $this->webService->containerExists()->willReturn(false);
        $this->webService->build($output, Argument::any())
            ->shouldBeCalled()
            ->willReturn(true);

        $this->build($output)->shouldReturn(true);
    }

    /**
     * When db is not running, but web is it should return false
     */
    function it_should_display_notrunning_when_db_stopped(OutputInterface $output)
    {
        $this->dbService->isRunning($output)
            ->shouldBeCalled()
            ->willReturn(false);

        $this->webService->isRunning($output)
            ->shouldBeCalled()
            ->willReturn(true);

        $output->writeStatus(Argument::any(), 'STOPPED', 'warning')
            ->shouldBeCalled();

        $output->emptyLine()->shouldBeCalled();

        $this->status($output)->shouldReturn(false);
    }

    /**
     * When web is not running, but db is it should return false
     */
    function it_should_display_notrunning_when_web_stopped(OutputInterface $output)
    {
        $this->dbService->isRunning($output)
            ->shouldBeCalled()
            ->willReturn(true);

        $this->webService->isRunning($output)
            ->shouldBeCalled()
            ->willReturn(false);

        $output->writeStatus(Argument::any(), Argument::any(), 'warning')
            ->shouldBeCalled();

        $output->emptyLine()->shouldBeCalled();

        $this->status($output)->shouldReturn(false);
    }

    /**
     * It should display running when all services are running and should
     * display website details and database details
     */
    function it_should_display_running(OutputInterface $output)
    {
        $this->dbService->isRunning($output)
            ->willReturn(true);

        $this->webService->isRunning($output)
            ->willReturn(true);

        $webVars = [
            'SSCLI_HOST_PORT' => 8000,
            'SS_DATABASE_NAME' => 'dbName',
            'SS_DATABASE_USERNAME' => 'user',
            'SS_DATABASE_PASSWORD' => 'password',
        ];
        $this->webService->getEnvVars()
            ->willReturn($webVars);

        $dbVars = ['SSCLI_HOST_PORT' => 9000];
        $this->dbService->getEnvVars()
            ->willReturn($dbVars);

        $dotEnv = [
            'SS_DEFAULT_ADMIN_USERNAME' => 'admin',
            'SS_DEFAULT_ADMIN_PASSWORD' => 'password',
        ];
        $this->project->getDotEnv()
            ->willReturn($dotEnv);

        $this->status($output)->shouldReturn(true);

        $output->writeStatus(Argument::any(), Argument::any(), 'success')
            ->shouldHaveBeenCalled();

        $vars = [
            'SS_DATABASE_NAME' => $webVars['SS_DATABASE_NAME'],
            'SS_DATABASE_USERNAME' => $webVars['SS_DATABASE_USERNAME'],
            'SS_DATABASE_PASSWORD' => $webVars['SS_DATABASE_PASSWORD'],
            'DB_HOSTNAME' => '127.0.0.1',
            'DB_PORT' => $dbVars['SSCLI_HOST_PORT'],
            'WEB_PORT' => $webVars['SSCLI_HOST_PORT'],
            'SS_DEFAULT_ADMIN_USERNAME' => 'admin',
            'SS_DEFAULT_ADMIN_PASSWORD' => 'password',
        ];
        $output->displayEnvironmentDetails($vars)
            ->shouldHaveBeenCalled();
    }

    function it_should_start(OutputInterface $output)
    {
        // Both services are stopped
        $this->dbService->isRunning($output)
            ->willReturn(false);
        $this->webService->isRunning($output)
            ->willReturn(false);

        // The db service will stop
        $this->dbService->start($output)
            ->willReturn(true);

        // Mock db vars
        $dbVars = [
            'MYSQL_ROOT_PASSWORD' => 'rootpass',
            'SSCLI_HOST_PORT' => '9000',
        ];
        $this->dbService->getEnvVars()
            ->willReturn($dbVars);

        // Mock web vars
        $webVars = [
            'SS_DATABASE_NAME' => 'dbName',
            'SS_DATABASE_USERNAME' => 'root',
            'SS_DATABASE_PASSWORD' => 'rootpass',
        ];
        $this->webService->getEnvVars()
            ->willReturn($webVars);

        // Mock db IP
        $this->dbService->getIp()
            ->willReturn('172.1.0.100');

        // Ensure the db exists
        $this->dbService->ensureDatabaseExists(Argument::type('array'))
            ->shouldBeCalled();

        // The web service will start
        $this->webService->start($output, Argument::type('array'))
            ->willReturn(true);

        $this->start($output)
            ->shouldReturn(true);

        $output->writeStatus(Argument::any(), Argument::any(), 'success')
            ->shouldHaveBeenCalled();
    }

    function it_should_not_start_when_dbservice_fails(OutputInterface $output)
    {
        // Both services are stopped
        $this->dbService->isRunning($output)
            ->willReturn(false);
        $this->webService->isRunning($output)
            ->willReturn(false);

        // The db service will stop
        $this->dbService->start($output)->willReturn(false);

        $this->start($output)
            ->shouldReturn(false);

        $output->writeStatus(Argument::any(), Argument::any(), 'error')
            ->shouldHaveBeenCalled();
    }

    function it_should_not_start_when_webservice_fails(OutputInterface $output)
    {
        // Both services are stopped
        $this->dbService->isRunning($output)
            ->willReturn(true);
        $this->webService->isRunning($output)
            ->willReturn(false);

        // Mock db vars
        $dbVars = [
            'MYSQL_ROOT_PASSWORD' => 'rootpass',
            'SSCLI_HOST_PORT' => '9000',
        ];
        $this->dbService->getEnvVars()
            ->willReturn($dbVars);

        // Mock web vars
        $webVars = [
            'SS_DATABASE_NAME' => 'dbName',
            'SS_DATABASE_USERNAME' => 'root',
            'SS_DATABASE_PASSWORD' => 'rootpass',
        ];
        $this->webService->getEnvVars()
            ->willReturn($webVars);

        // Mock db IP
        $this->dbService->getIp()
            ->willReturn('172.1.0.100');

        // Ensure the db exists
        $this->dbService->ensureDatabaseExists(Argument::type('array'))
            ->shouldBeCalled();

        // The web service will start
        $this->webService->start($output, Argument::type('array'))
            ->willReturn(false);

        $this->start($output)->shouldReturn(false);

        $output->writeStatus(Argument::any(), Argument::any(), 'error')
            ->shouldHaveBeenCalled();
    }

    function it_should_stop(OutputInterface $output)
    {
        $this->dbService->stop($output)->willReturn(true);
        $this->webService->stop($output)->willReturn(true);

        $this->stop($output)->shouldReturn(true);

        $output->writeStatus(Argument::any(), Argument::any(), 'info')
            ->shouldHaveBeenCalled();
    }

    function it_should_not_stop_when_services_still_running(OutputInterface $output)
    {
        $this->dbService->stop($output)->willReturn(false);
        $this->webService->stop($output)->willReturn(false);

        $this->stop($output)->shouldReturn(false);

        $output->writeStatus(Argument::any(), Argument::any(), 'error')
            ->shouldHaveBeenCalled();
    }
}
