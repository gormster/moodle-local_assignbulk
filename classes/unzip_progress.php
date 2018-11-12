<?php
// This file is part of moodle-local_assignbulk - https://github.com/gormster/moodle-local_assignbulk/
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
 * An uploader handles the work of uploading one lot of assignment submissions.
 *
 * @package     local_assignbulk
 * @copyright   2017 Morgan Harris <morgan.harris@unsw.edu.au>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assignbulk;

defined('MOODLE_INTERNAL') or die();

use file_progress;

class unzip_progress implements file_progress {

    private $progress;

    private $started;

    private $ended;

    private $description;

    private $parentunits;

    public function __construct(\core\progress\base $progress, $description, $parentunits = 1) {
        $this->progress = $progress;
        $this->started = false;
        $this->ended = false;
        $this->description = $description;
        $this->parentunits = $parentunits;
    }

    public function progress($progress = file_progress::INDETERMINATE, $max = file_progress::INDETERMINATE) {
        if ($max == file_progress::INDETERMINATE) {
            return;
        }

        if ($this->started === false) {
            $this->progress->start_progress($this->description, $max, $this->parentunits);
            $this->started = true;
        }

        if ($progress == file_progress::INDETERMINATE) {
            return;
        }

        $this->progress->progress($progress);

        if ($progress == $max) {
            $this->ended = true;
            $this->progress->end_progress();
        }
    }

    public function __destruct() {
        if (($this->started == true) && ($this->ended == false)) {
            $this->progress->end_progress();
        }
    }

}