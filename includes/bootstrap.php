<?php
/**
 * @package   panopticon
 * @copyright Copyright (c)2023-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

use Akeeba\Panopticon\Application\BootstrapUtilities;

// I must include the file since the autoloader is not yet set up.
require_once APATH_ROOT . '/src/Application/BootstrapUtilities.php';

// Basic environment checks
BootstrapUtilities::assertMinimumPHPVersion();
BootstrapUtilities::assertNotHHVM();
BootstrapUtilities::assertComposerInstalled();

// Load the Composer autoloader
require_once APATH_ROOT . '/vendor/autoload.php';

// Set up error handling
BootstrapUtilities::applyExceptionsHandler();

// Apply debug-related user preferences before the application initialisation
BootstrapUtilities::applyErrorReportingToPHP();
BootstrapUtilities::applyDebugToConstant();
BootstrapUtilities::conditionallyForceBladeRecompilation();

// Apply network-related settings
BootstrapUtilities::applyLoadBalancerConfiguration();
BootstrapUtilities::applyCustomCAFile();

// Apply user-supplied code and miscellaneous files
BootstrapUtilities::loadUserCode();