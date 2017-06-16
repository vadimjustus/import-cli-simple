<?php

/**
 * TechDivision\Import\Cli\Utils\DependencyInjectionKeys
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

namespace TechDivision\Import\Cli\Utils;

/**
 * A utility class for the DI service keys.
 *
 * @author    Tim Wagner <t.wagner@techdivision.com>
 * @copyright 2016 TechDivision GmbH <info@techdivision.com>
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @link      https://github.com/techdivision/import-cli-simple
 * @link      http://www.techdivision.com
 */
class DependencyInjectionKeys extends \TechDivision\Import\App\Utils\DependencyInjectionKeys
{

    /**
     * The key for the input instance.
     *
     * @var string
     */
    const INPUT = 'input';

    /**
     * The key for the output instance.
     *
     * @var string
     */
    const OUTPUT = 'output';

    /**
     * The key for the application instance.
     *
     * @var string
     */
    const APPLICATION = 'import_cli_simple.application';

    /**
     * The key for the simple configuration instance.
     *
     * @var string
     */
    const CONFIGURATION_SIMPLE = 'configuration.simple';

    /**
     * The key for the vendor directory.
     *
     * @var string
     */
    const CONFIGURATION_VENDOR_DIR = 'import_cli_simple.configuration.vendor.dir';
}
