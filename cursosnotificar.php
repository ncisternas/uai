<?php

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once ($CFG->libdir . '/tablelib.php');

$context = context_system::instance();

$url = new moodle_url('/local/uai/cursosnotificar.php');

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');

// Creamos una nueva tabla flexible (i.e. ordenable)
$showpages = new flexible_table ('cursos');

// Encabezados para la tabla
$headers = array ();
$headers[] = get_string ('fullname');
$headers[] = get_string ('shortname');
$headers[] = get_string ( 'active');

// Define flexible table (can be sorted in different ways)
$showpages->define_headers($headers);

// Columnas de la tabla
$columns = array();
$columns[] = 'fullname';
$columns[] = 'shortname';
$columns[] = 'active';
$showpages->define_columns($columns);

$showpages->define_baseurl($url);

$showpages->sortable ( true, 'fullname', SORT_ASC );
$showpages->pageable ( true );
$showpages->pagesize ( 10, 100);
$showpages->setup ();

echo $OUTPUT->header();
$showpages->print_html ();
echo $OUTPUT->footer();
