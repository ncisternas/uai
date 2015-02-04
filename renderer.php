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
 * Defines the renderer for the question engine upgrade helper plugin.
 *
 * @package    local
 * @subpackage uai
 * @copyright  2012 Adolfo Ibanez University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


class local_uai_renderer extends plugin_renderer_base {


    /**
     * Renderer para la página de agregar Datos
     * @return Un Html con el layout de la página.
     * Primero debe comenzarse, y dado que este contiene un
     * inicio y un fin
     */
	public function adddata_page_start() {
    	global $CFG;
    	
    	$output = '';
        $output .= $this->header();
        $output .= $this->heading(get_string('pluginname', 'local_uai'));
        return $output;
    }
	public function adddata_page_end() {
    	global $CFG;
    	$url = $CFG->wwwroot . '/local/uai/syncomega.php';
    	
    	
    	$output = '';
    	$output .= html_writer::start_tag('div');
    	$output .= $this->single_button($url, 'Volver', 'get');
    	$output .= html_writer::end_tag('div');
        $output .= $this->footer();
        return $output;
    }


	public function index_page($data) {
    	global $CFG;
		
    	$tabla = new html_table();
    	$tabla->head = array("Período Académico", "Categoría Webcursos", "Sede", "Fecha inicio Período Académico", "Fecha término Período Académico", "Sincronización está activa", "");
    	$tabla->data = $data;
    	
    	$url = $CFG->wwwroot . '/local/uai/agregar.php';
    	
    	$output = '';
        $output .= $this->header();
        $output .= $this->heading(get_string('pluginname', 'local_uai'));
        $output .= html_writer::table($tabla);
	    $output .= html_writer::start_tag('div', array('style' => 'float:right;'));
        $output .= $this->single_button($url, 'Agregar Sincronización', 'get');
        $output .= html_writer::end_tag('div');
	    $output .= $this->footer();
    	
        return $output;
    }

}