<?php

namespace Phorever\Console\Command;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Config\Definition\Processor;
use Phorever\Configuration;

use Monolog\Logger;
use Phorever\Monolog\Handler\ConsoleHandler;
use Monolog\Handler\StreamHandler;
use Phorever\Monolog\Formatter\ConsoleFormatter;
use Phorever\Monolog\Formatter\FileFormatter;

abstract class ConfigBasedCommand extends BaseCommand
{
    /**
     * @var array
     */
    protected $config;


    /**
     * @var Logger
     */
    protected $logger;

    const DEFAULT_CONFIG_FILES = "./phorever.(json|yml)";
    /**
     * {@inheritdoc}
     */
    public function __construct($name = null)
    {
        parent::__construct($name);

        $this->addOption(
            'config',
            'c',
            InputOption::VALUE_REQUIRED,
            'Configuration file listing processes and roles',
            self::DEFAULT_CONFIG_FILES
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        ////
        // CONFIGURATION
        ////

        $file = $this->getDefaultConfigFile($input->getOption('config'));

        $processor = new Processor();
        $raw_config = $this->loadConfig($file);
        $this->config = $processor->processConfiguration(new Configuration(), array($raw_config));

        ////
        // SYSTEM
        ////

        date_default_timezone_set($this->config['timezone']);

        ////
        // LOGGER
        ////

        $level = false;
        if ($input->getOption('verbose'))
            $level = Logger::DEBUG;

        $this->logger = new Logger('phorever');

        if ($input->hasOption('daemon') && $input->getOption('daemon')) {
            if (!file_exists($this->config['logging']['directory'])) {
                if (!mkdir($this->config['logging']['directory'], 0777, true)) {
                    throw new \Exception("Unable to create logging directory");
                }
            }

            $this->logger->pushHandler($handler = new StreamHandler($this->config['logging']['directory'] . 'phorever.log', $level ?: Logger::INFO));
            $handler->setFormatter(new FileFormatter());
        } else {
            $this->logger->pushHandler($stdoutHandler = new ConsoleHandler($output, $level ?: Logger::INFO));
            $this->logger->pushHandler($stderrHandler = new ConsoleHandler($output->getErrorOutput(), Logger::ERROR, false));

            $stderrHandler->setFormatter(new ConsoleFormatter());
            $stdoutHandler->setFormatter(new ConsoleFormatter());
        }
    }

    private function getDefaultConfigFile($file)
    {
        if ($file == self::DEFAULT_CONFIG_FILES) {
            if ($json_file = realpath('./phorever.json')) {
                $file = $json_file;
            }
            if ($yml_file = realpath('./phorever.yml')) {
                $file = $yml_file;
            }
        }

        if ($file = realpath($file)) {
            chdir(dirname($file));
        } else {
            throw new \Exception(sprintf("Could not find configuration file at '%s'", $file));
        }
        return $file;
    }

    private function loadConfig($file)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        if (in_array($ext, array('yml', 'yaml'))) {
            $yaml = new \Symfony\Component\Yaml\Parser();
            return $yaml->parse(file_get_contents($file));
        } else {
            return json_decode(file_get_contents($file), true);
        }
    }

    /**
     * @return \Monolog\Logger
     */
    protected function getLogger() {
        return $this->logger;
    }
}
