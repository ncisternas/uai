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
 * This script allows you to reset any local user password.
 *
 * @package    core
 * @subpackage cli
 * @copyright  2010 Jorge Villalon (http://villalon.cl)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir.'/clilib.php');      // cli only functions
require_once($CFG->libdir.'/moodlelib.php');      // moodle lib functions
require_once($CFG->libdir.'/datalib.php');      // data lib functions
require_once($CFG->libdir.'/accesslib.php');      // access lib functions
require_once($CFG->dirroot.'/course/lib.php');      // course lib functions
require_once($CFG->dirroot.'/enrol/guest/lib.php');      // guest enrol lib functions


// now get cli options
list($options, $unrecognized) = cli_get_params(array('help'=>false),
                                               array('h'=>'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
"Este script se trae los datos desde Omega

Options:
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php admin/cli/omega.php
"; //TODO: localize - to be translated later when everything is finished

    echo $help;
    die;
}

echo "Ok\n";
cli_heading('Conectandose con Omega'); // TODO: localize

echo "Ok\n";
putenv('FREETDSCONF=/etc/freetds.conf'); 

echo "Ok\n";
$link = mssql_connect('bdcl4.uai.cl','webcursos','uai2011') or die("Error conectandose a BBDD de Omega");

if(!$link) {
  echo "Error conectandose a la BBDD de Omega\n";
  die;
}
echo "Ok\n";
mssql_select_db('OmegaDB') 
    or die('Could not select a database.');

echo "Ok\n";
mysql_connect('localhost','moodleuser','Diagonal.2011') or die("Imposible conectarse a BBDD local");

echo "Ok\n";
mysql_select_db('omega') or die("Error con BBDD omega ".mysql_error());
echo "Ok\n";

$sql = "select * from sync_data";

$result = mysql_query($sql) or die("Problema con sql".mysql_error());

echo "Ok\n";
$syncsql = "";
$total = 0;
// Fetch rows:
while ($row = mysql_fetch_assoc($result)) {
	$thissql = "";
	if($total > 0)
		$thissql = " union ";
	$thissql = $thissql . "select ".$row['periodo_academico_id'].",".$row['categoria_id'].",'".$row['sede']."','".$row['sede_corto']."','".$row['tipo']."'";
    $syncsql = $syncsql . $thissql;
    $total++;
  }
print $syncsql;

$sql = "declare @categorias table
(
	periodo_academico_id int,
	categoria_id int,
	sede varchar(50),
	sede_corto varchar(50),
	tipo varchar(50)
)

insert into @categorias " . $syncsql . "

select 
	Asignatura + ' Sec. ' + convert(varchar,s.NumeroSeccion) + ' ' + c.sede + ' 1er Sem. 2011' as fullname,
	shortname = c.sede_corto + '-' + s.Sigla + '-' + convert(varchar,s.NumeroSeccion) + '-1-2011',
	SeccionId as idnumber,
	c.categoria_id as category
from WebCursos_Secciones as s
inner join WebCursos_PeriodosAcademicos as p on (s.PeriodoAcademicoId = p.PeriodoAcademicoId)
inner join @categorias as c on (c.periodo_academico_id = s.PeriodoAcademicoId)
";

$result = mssql_query($sql) or die("Error en query");

// Get result count:
$count = mssql_num_rows($result);
print "Showing $count rows:<hr/>\n\n";

if ($count > 0) {

$sql = "delete from cursos";

mysql_query($sql) or die("Problema con sql".mysql_error());
print $sql;

  // Fetch rows:
  while ($row = mssql_fetch_assoc($result)) {
    $inssql = "insert into cursos (fullname, shortname, idnumber, category) values ('".$row['fullname']."','".$row['shortname']."',".$row['idnumber'].",".$row['category'].")";
    mysql_query($inssql) or die("Error insertando cursos ".mysql_error());
    print "Agregando ". $row['fullname']."\n";
  }
}

$sqlinscritos = "declare @categorias table
(
	periodo_academico_id int,
	categoria_id int,
	sede varchar(50),
	sede_corto varchar(50),
	tipo varchar(50)
)

declare @semestre varchar(50)
declare @semestre_corto varchar(50)

set @semestre = '1er Sem. 2011'
set @semestre_corto = '1-2011'

insert into @categorias 
" . $syncsql . "

select
	c.sede_corto + '-' + Sigla + '-' + CONVERT(varchar,NumeroSeccion) + '-1-2011' as course,
	LOWER(SUBSTRING(Email,0,CHARINDEX('@',Email,0))) + '@uai.cl' as 'user',
	'estudiante' as role
from WebCursos_AlumnosSeccion as a
inner join @categorias as c on (c.periodo_academico_id = a.PeriodoAcademicoId)
inner join WebCursos_Secciones as s on (a.SeccionId = s.SeccionId)
union
select
	c.sede_corto + '-' + Sigla + '-' + CONVERT(varchar,NumeroSeccion) + '-1-2011' as course,
 	LOWER(SUBSTRING(Email,0,CHARINDEX('@',Email,0))) + '@uai.cl' as 'user',
	'profesoreditor' as role
from WebCursos_ProfesoresSeccion as a
inner join @categorias as c on (c.periodo_academico_id = a.PeriodoAcademicoId)
inner join WebCursos_Secciones as s on (a.SeccionId = s.SeccionId)
order by role
";

$result = mssql_query($sqlinscritos) or die("Error en query");

// Get result count:
$count = mssql_num_rows($result);
print "Showing $count rows:<hr/>\n\n";

if ($count > 0) {

$sql = "delete from inscritos";

mysql_query($sql) or die("Problema con sql".mysql_error());
print $sql;

  // Fetch rows:
  while ($row = mssql_fetch_assoc($result)) {
    $inssql = "insert into inscritos (course, user, role) values ('".$row['course']."','".$row['user']."','".$row['role']."')";
    mysql_query($inssql) or die("Error insertando cursos ".mysql_error());
    print "Agregando inscrito ". $row['user']." a curso ". $row['course']."\n";
  }
}

mssql_close() or die("Error cerrando conexion a BBDD Omega");

mysql_close();

print "Hecho.";
