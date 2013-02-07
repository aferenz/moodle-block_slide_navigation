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
 * Contains administration settings options.
 *
 * @package    block_custom_navigation_menu
 * @copyright  2013 ULCC
 * @webseite
 * @license
 */

defined('MOODLE_INTERNAL') || die;

/*//courses limit
$options = array('all'=>get_string('configall', 'block_slide_navigation'),
                 '10'=>get_string('configten', 'block_slide_navigation'),
                 '20'=>get_string('configtwenty', 'block_slide_navigation'));

$settings->add(new admin_setting_configselect(
    'slide_navigation/courselimit',
    get_string('courselimit', 'block_slide_navigation'),
    get_string('desccourselimit', 'block_slide_navigation'),
    '20', $options));
*/
//display categories/subcategories with their courses
$options = array('courses'=>get_string('configcourses', 'block_slide_navigation'),
                 'catandcourses'=>get_string('configcatandcourses', 'block_slide_navigation'));

$settings->add(new admin_setting_configselect(
    'slide_navigation/coursenavitems',
    get_string('coursenavitems', 'block_slide_navigation'),
    get_string('desccoursenavitems', 'block_slide_navigation'),
    'courses', $options));

//display course short/long name
$options = array('short'=>get_string('configshort', 'block_slide_navigation'),
                 'long'=>get_string('configlong', 'block_slide_navigation'));

$settings->add(new admin_setting_configselect(
    'slide_navigation/courseformat',
    get_string('courseformat', 'block_slide_navigation'),
    get_string('desccourseformat', 'block_slide_navigation'),
    'short', $options));


//hide all courses except for the current course
$options = array('no'=>get_string('configno', 'block_slide_navigation'),
                 'yes'=>get_string('configyes', 'block_slide_navigation'));

$settings->add(new admin_setting_configselect(
    'slide_navigation/hidecourses',
    get_string('hidecourses', 'block_slide_navigation'),
    get_string('deschidecourses', 'block_slide_navigation'),
    'no', $options));