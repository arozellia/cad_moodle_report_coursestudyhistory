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
 * Plugin version and other meta-data are defined here.
 *
 * @package     report_coursestudyhistory
 * @copyright   2019 Tia <tia@techiasolutions.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin = new \stdClass();
$plugin->component = 'report_coursestudyhistory';
$plugin->release = '0.1.0';
$plugin->version = 2019092800;
$plugin->requires = 2018120300;
$plugin->maturity = MATURITY_ALPHA;
$plugin->depenencies = [
	'mod_certificate' => ANY_VERSION,
	'mod_customcert' => ANY_VERSION
];
