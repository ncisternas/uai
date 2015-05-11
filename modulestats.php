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
        COUNT(distinct cm.id) AS modules
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
echo $OUTPUT->heading($category->name);
echo $OUTPUT->heading($title, 4);

$table = new flexible_table('modulestats');

$headers = array();
$headers[] = 'Courses';
$headers[] = 'Modules';
$headers[] = 'Usage';
$table->define_headers($headers);

$columns = array();
$columns[] = 'courses';
$columns[] = 'modules';
$columns[] = 'usage';
$table->define_columns($columns);

// Define a base url
$table->define_baseurl($url);

// The sortable and non sortable columns
$table->pageable ( true );

// We get the count for the data
$numcursos = count($modulestats);

// Set the page size
$table->pagesize ( $perpage, $numcursos);

$table->show_download_buttons_at(array(TABLE_P_TOP));

// Setup the table
$table->setup ();

$permodulestats = array();
$courses = array();
$modules = array();
foreach($modulestats as $stat) {
    $courses[$stat->id] = 1;
    $modules[] = intval($stat->modules);
    $permodulestats[$stat->name][] = intval($stat->modules); 
}

$coursestats = local_uai_calculate_stats(array_values($courses), 'courses');
$totalmodules  = local_uai_calculate_stats($modules, 'modules');

$courseurl = new moodle_url('/course/view.php', array('id'=>$stat->id));
$data = array();
$data[] = $coursestats->count;
$data[] = $totalmodules->sum;
$data[] = round($totalmodules->sum / $coursestats->count, 1);
$table->add_data($data);

$table->print_html();

$mstat_table = new html_table();
$mstat_table->head = array('Módulo', 'Cursos', 'Promedio', 'Min', 'Max');
foreach(array_keys($permodulestats) as $module) {
    $mstat = local_uai_calculate_stats($permodulestats[$module], $module);
    $mstat_table->data[] = new html_table_row(array($mstat->name, $mstat->count, $mstat->avg, $mstat->min, $mstat->max));
}

echo $OUTPUT->heading('Estadísticas de módulos', 4);
echo html_writer::table($mstat_table);

echo $OUTPUT->footer();