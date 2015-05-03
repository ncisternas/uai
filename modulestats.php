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
 *
*
* @package    local
* @subpackage uai
* @copyright  2015 Jorge Villalon
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

//Página para buscar el curso de un alumno.

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once ($CFG->libdir . '/tablelib.php');
require_once 'locallib.php';

global $PAGE, $CFG, $OUTPUT, $DB, $USER;

$categoryid = required_param('id', PARAM_INT);

if(!$category = $DB->get_record('course_categories', array('id'=>$categoryid))) {
    print_error('Invalid category id');
}

$url = new moodle_url('/local/uai/modulestats.php', array('id'=>$category->id));

$context = context_coursecat::instance($category->id);

require_login();

$perpage = 20;

$sql = "
    SELECT c.id, 
        c.shortname, 
        c.fullname, 
        m.name, 
        COUNT(distinct m.id) AS modules
    FROM {course_modules} AS cm
    INNER JOIN {modules} AS m ON (m.id = cm.module)
    INNER JOIN {course} AS c ON (c.id = cm.course)
    INNER JOIN {course_categories} AS cc ON (cc.id = c.category)
    WHERE cc.path like '%/$categoryid/%' or  cc.id = :categoryid
    GROUP BY c.category, c.id, m.id
    ORDER BY c.category, c.id, m.name";

$modulestats = $DB->get_recordset_sql($sql, array('categoryid'=>$categoryid));

$PAGE->set_category_by_id($categoryid);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('coursecategory');

$title = 'Estadísticas de cursos';
$PAGE->set_title($title);

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

$table = new flexible_table('modulestats');

$headers = array();
$headers[] = 'Fullname';
$headers[] = 'Module';
$headers[] = 'Usage';
$table->define_headers($headers);

$columns = array();
$columns[] = 'fullname';
$columns[] = 'module';
$columns[] = 'usage';
$table->define_columns($columns);

// Define a base url
$table->define_baseurl($url);

// The sortable and non sortable columns
$table->sortable ( true, 'fullname', SORT_ASC );
$table->pageable ( true );

// We get the count for the data
$numcursos = count($modulestats);

// Set the page size
$table->pagesize ( $perpage, $numcursos);

// Setup the table
$table->setup ();

foreach($modulestats as $stat) {
    $data = array();
    $data[] = $stat->fullname;
    $data[] = $stat->name;
    $data[] = $stat->modules;
    $table->add_data($data);
}

$table->show_download_buttons_at(array(TABLE_P_TOP));
$table->print_html();

echo $OUTPUT->footer();