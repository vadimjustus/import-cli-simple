<?php

/**
 * TechDivision\Import\Cli\Command\ImportCommandTrait
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * PHP version 5
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import-cli-simple
 * @link      http://www.techdivision.com
 */

namespace TechDivision\Import\Cli\Command;

use Rhumsaa\Uuid\Uuid;
use Psr\Log\LogLevel;
use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;
use JMS\Serializer\SerializerBuilder;
use TechDivision\Import\Cli\Simple;
use TechDivision\Import\Cli\Configuration;
use TechDivision\Import\Cli\Services\ImportProcessorFactory;
use TechDivision\Import\Cli\Services\RegistryProcessorFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use TechDivision\Import\Cli\Configuration\Database;

/**
 * The abstract import command implementation.
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import-cli-simple
 * @link      http://www.techdivision.com
 */
abstract class AbstractImportCommand extends Command
{

    /**
     * Configures the current command.
     *
     * @return void
     * @see \Symfony\Component\Console\Command\Command::configure()
     */
    protected function configure()
    {

        // initialize the command with the required/optional options
        $this->addArgument(
            InputArgumentKeys::OPERATION_NAME,
            InputArgument::OPTIONAL,
            'The operation that has to be used for the import, one of "add-update", "replace" or "delete"',
            InputArgumentKeys::OPERATION_NAME_ARG_REPLACE
        )
        ->addOption(
            InputOptionKeys::CONFIGURATION,
            null,
            InputOption::VALUE_REQUIRED,
            'Specify the pathname to the configuration file to use',
            sprintf('%s/techdivision-import.json', getcwd())
        )
        ->addOption(
            InputOptionKeys::INSTALLATION_DIR,
            null,
            InputOption::VALUE_REQUIRED,
            'The Magento installation directory to which the files has to be imported'
        )
        ->addOption(
            InputOptionKeys::SOURCE_DIR,
            null,
            InputOption::VALUE_REQUIRED,
            'The directory that has to be watched for new files'
        )
        ->addOption(
            InputOptionKeys::TARGET_DIR,
            null,
            InputOption::VALUE_REQUIRED,
            'The target directory with the files that has been imported'
        )
        ->addOption(
            InputOptionKeys::UTILITY_CLASS_NAME,
            null,
            InputOption::VALUE_REQUIRED,
            'The utility class name with the SQL statements'
        )
        ->addOption(
            InputOptionKeys::PREFIX,
            null,
            InputOption::VALUE_REQUIRED,
            'The prefix of the CSV source file(s) that has/have to be imported'
        )
        ->addOption(
            InputOptionKeys::MAGENTO_EDITION,
            null,
            InputOption::VALUE_REQUIRED,
            'The Magento edition to be used, either one of CE or EE'
        )
        ->addOption(
            InputOptionKeys::MAGENTO_VERSION,
            null,
            InputOption::VALUE_REQUIRED,
            'The Magento version to be used, e. g. 2.1.2'
        )
        ->addOption(
            InputOptionKeys::SOURCE_DATE_FORMAT,
            null,
            InputOption::VALUE_REQUIRED,
            'The date format used in the CSV file(s)'
        )
        ->addOption(
            InputOptionKeys::USE_DB_ID,
            null,
            InputOption::VALUE_REQUIRED,
            'The explicit database ID used for the actual import process'
        )
        ->addOption(
            InputOptionKeys::DB_PDO_DSN,
            null,
            InputOption::VALUE_REQUIRED,
            'The DSN used to connect to the Magento database where the data has to be imported, e. g. mysql:host=127.0.0.1;dbname=magento'
        )
        ->addOption(
            InputOptionKeys::DB_USERNAME,
            null,
            InputOption::VALUE_REQUIRED,
            'The username used to connect to the Magento database'
        )
        ->addOption(
            InputOptionKeys::DB_PASSWORD,
            null,
            InputOption::VALUE_REQUIRED,
            'The password used to connect to the Magento database'
        )
        ->addOption(
            InputOptionKeys::LOG_LEVEL,
            null,
            InputOption::VALUE_REQUIRED,
            'The log level to use'
        )
        ->addOption(
            InputOptionKeys::IGNORE_PID,
            null,
            InputOption::VALUE_REQUIRED,
            'Whether or not an existing PID should be ignored or not'
        )
        ->addOption(
            InputOptionKeys::DEBUG_MODE,
            null,
            InputOption::VALUE_REQUIRED,
            'Whether use the debug mode or not'
        )
        ->addOption(
            InputOptionKeys::PID_FILENAME,
            null,
            InputOption::VALUE_REQUIRED,
            'The explicit PID filename to use',
            sprintf('%s/%s', sys_get_temp_dir(), Configuration::PID_FILENAME)
        );
    }

    /**
     * Factory implementation to create a new initialized configuration instance.
     *
     * If command line options are specified, they will always override the
     * values found in the configuration file.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The Symfony console input instance
     *
     * @return \TechDivision\Import\Cli\Configuration The configuration instance
     * @throws \Exception Is thrown, if the specified configuration file doesn't exist
     */
    protected function configurationFactory(InputInterface $input)
    {

        // load the configuration filename we want to use
        $filename = $input->getOption(InputOptionKeys::CONFIGURATION);

        // load the JSON data
        if (!$jsonData = file_get_contents($filename)) {
            throw new \Exception(sprintf('Can\'t load configuration file %s', $filename));
        }

        // initialize the JMS serializer and load the configuration
        $serializer = SerializerBuilder::create()->build();
        /** @var \TechDivision\Import\Cli\Configuration $instance */
        $instance = $serializer->deserialize($jsonData, 'TechDivision\Import\Cli\Configuration', 'json');

        // query whether or not an operation name has been specified as command line
        // option, if yes override the value from the configuration file
        if ($input->hasArgument(InputArgumentKeys::OPERATION_NAME)) {
            $instance->setOperationName($input->getArgument(InputArgumentKeys::OPERATION_NAME));
        }

        // query whether or not a Magento installation directory has been specified as command line
        // option, if yes override the value from the configuration file
        if ($installationDir = $input->getOption(InputOptionKeys::INSTALLATION_DIR)) {
            $instance->setInstallationDir($installationDir);
        }

        // query whether or not a directory for the source files has been specified as command line
        // option, if yes override the value from the configuration file
        if ($sourceDir = $input->getOption(InputOptionKeys::SOURCE_DIR)) {
            $instance->setSourceDir($sourceDir);
        }

        // query whether or not a directory containing the imported files has been specified as command line
        // option, if yes override the value from the configuration file
        if ($targetDir = $input->getOption(InputOptionKeys::TARGET_DIR)) {
            $instance->setTargetDir($targetDir);
        }

        // query whether or not a source date format has been specified as command
        // line  option, if yes override the value from the configuration file
        if ($sourceDateFormat = $input->getOption(InputOptionKeys::SOURCE_DATE_FORMAT)) {
            $instance->setSourceDateFormat($sourceDateFormat);
        }

        // query whether or not a Magento edition has been specified as command line
        // option, if yes override the value from the configuration file
        if ($magentoEdition = $input->getOption(InputOptionKeys::MAGENTO_EDITION)) {
            $instance->setMagentoEdition($magentoEdition);
        }

        // query whether or not a Magento version has been specified as command line
        // option, if yes override the value from the configuration file
        if ($magentoVersion = $input->getOption(InputOptionKeys::MAGENTO_VERSION)) {
            $instance->setMagentoVersion($magentoVersion);
        }

        // query whether or not a DB ID has been specified as command line
        // option, if yes override the value from the configuration file
        if ($useDbId = $input->getOption(InputOptionKeys::USE_DB_ID)) {
            $instance->setUseDbId($useDbId);
        } else {
            // query whether or not a PDO DSN has been specified as command line
            // option, if yes override the value from the configuration file
            if ($dsn = $input->getOption(InputOptionKeys::DB_PDO_DSN)) {
                // first REMOVE all other database configurations
                $instance->clearDatabases();

                // initialize a new database configuration
                $database = new Database();
                $database->setId(Uuid::uuid4()->__toString());
                $database->setDefault(true);
                $database->setDsn($dsn);

                // query whether or not a DB username has been specified as command line
                // option, if yes override the value from the configuration file
                if ($username = $input->getOption(InputOptionKeys::DB_USERNAME)) {
                    $database->setUsername($username);
                }

                // query whether or not a DB password has been specified as command line
                // option, if yes override the value from the configuration file
                if ($password = $input->getOption(InputOptionKeys::DB_PASSWORD)) {
                    $database->setPassword($password);
                }

                // add the database configuration
                $instance->addDatabase($database);
            }
        }

        // query whether or not the debug mode has been specified as command line
        // option, if yes override the value from the configuration file
        if ($debugMode = $input->getOption(InputOptionKeys::DEBUG_MODE)) {
            $instance->setDebugMode($instance->mapBoolean($debugMode));
        }

        // query whether or not the ignore PID flag has been specified as command line
        // option, if yes override the value from the configuration file
        if ($ignorePid = $input->getOption(InputOptionKeys::IGNORE_PID)) {
            $instance->setIgnorePid($instance->mapBoolean($ignorePid));
        }

        // query whether or not the log level has been specified as command line
        // option, if yes override the value from the configuration file
        if ($logLevel = $input->getOption(InputOptionKeys::LOG_LEVEL)) {
            $instance->setLogLevel($logLevel);
        }

        // query whether or not a PID filename has been specified as command line
        // option, if yes override the value from the configuration file
        if ($pidFilename = $input->getOption(InputOptionKeys::PID_FILENAME)) {
            $instance->setPidFilename($pidFilename);
        }

        // extend the plugins with the main configuration instance
        /** @var \TechDivision\Import\Cli\Configuration\Subject $subject */
        foreach ($instance->getPlugins() as $plugin) {
            // set the configuration instance on the plugin
            $plugin->setConfiguration($instance);

            // query whether or not the plugin has subjects configured
            if ($subjects = $plugin->getSubjects()) {
                // extend the plugin's subjects with the main configuration instance
                /** @var \TechDivision\Import\Cli\Configuration\Subject $subject */
                foreach ($subjects as $subject) {
                    // set the configuration instance on the subject
                    $subject->setConfiguration($instance);
                }
            }
        }

        // query whether or not the debug mode is enabled and log level
        // has NOT been overwritten with a commandline option
        if ($instance->isDebugMode() && !$input->getOption(InputOptionKeys::LOG_LEVEL)) {
            // set debug log level, if log level has NOT been overwritten on command line
            $instance->setLogLevel(LogLevel::DEBUG);
        }

        // return the initialized configuration instance
        return $instance;
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input  An InputInterface instance
     * @param \Symfony\Component\Console\Output\OutputInterface $output An OutputInterface instance
     *
     * @return null|int null or 0 if everything went fine, or an error code
     * @throws \LogicException When this abstract method is not implemented
     * @see \Symfony\Component\Console\Command\Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        // register the JMS Serializer annotations
        \Doctrine\Common\Annotations\AnnotationRegistry::registerAutoloadNamespace(
            'JMS\Serializer\Annotation',
            dirname(__DIR__).'/../vendor/jms/serializer/src'
        );

        // load the specified configuration
        $configuration = $this->configurationFactory($input);

        // initialize the PDO connection
        $dsn = $configuration->getDatabase()->getDsn();
        $username = $configuration->getDatabase()->getUsername();
        $password = $configuration->getDatabase()->getPassword();
        $connection = new \PDO($dsn, $username, $password);
        $connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // initialize the system logger
        $systemLogger = new Logger('techdivision/import');
        $systemLogger->pushHandler(
            new ErrorLogHandler(
                ErrorLogHandler::OPERATING_SYSTEM,
                $configuration->getLogLevel()
            )
        );

        // initialize the registry/import processor
        $registryProcessor = RegistryProcessorFactory::factory($connection, $configuration);
        $importProcessor = ImportProcessorFactory::factory($connection, $configuration);

        // initialize the importer
        $importer = new Simple(
            $systemLogger,
            $registryProcessor,
            $importProcessor,
            $configuration,
            $input,
            $output
        );

        // start the process
        $importer->process();
    }
}
