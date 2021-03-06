<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Steps definitions for assign bulk upload plugin.
 *
 * @package   local_assignbulk
 * @category  test
 * @copyright 2013 Morgan Harris
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @codeCoverageIgnore
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Testwork\Tester\Result\TestResult;

// define('DEBUG',1);

/**
 * Steps definitions for assign bulk upload plugin.
 *
 * @package   local_assignbulk
 * @copyright 2013 Morgan Harris
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_assignbulk extends behat_base {

    /**
     * Allow debugging of behat tests by PAUSING on failure.
     * Uncomment the DEBUG define above to use it. This should really be a built in part of Behat IMO...
     * @AfterStep
     *
     * @param AfterStepScope $scope
     */
    public function wait_to_debug_in_browser_on_step_error(AfterStepScope $scope) {
        if (defined('DEBUG')) {
            if ($scope->getTestResult()->getResultCode() == TestResult::FAILED) {
                fwrite(STDOUT, PHP_EOL . "\x07PAUSING ON FAILURE - Press any key to continue" . PHP_EOL);
                fflush(STDOUT);
                $anything = fread(STDIN, 1);
            }
        }
    }

}
