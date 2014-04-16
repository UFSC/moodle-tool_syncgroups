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
 * Version details.
 *
 * @package    local_syncgroups
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function sincroniza_grupos($grupos, $cdestinos) {
    global $DB;

    foreach($grupos AS $grp) {
        echo "\n----------------------------------------------------------------------\n";
        echo "Processando grupo: {$grp->id} - {$grp->name}\n";

        if (!$members = $DB->get_records('groups_members', array('groupid'=>$grp->id), '', 'id, groupid, userid')) {
            echo "\t?? grupo vazio. Pulando ....";
            continue;
        }

        $userids_members = array();
        foreach($members AS $id=>$m) {
            $userids_members[$m->userid] = $id;
        }

        foreach($cdestinos AS $cdest) {
            if ($dgr = $DB->get_record('groups', array('courseid'=>$cdest->id, 'name'=>$grp->name), 'id, courseid, idnumber, name')) {
                echo "   -- sincronizando em: {$cdest->shortname}\n";
            } else {
                echo "   -- criando grupo em: {$cdest->shortname}\n";
                unset($dgr);
                $dgr->courseid     = $cdest->id;
                $dgr->timecreated  = time();
                $dgr->timemodified = $dgr->timecreated;
                $dgr->name         = $grp->name;
                $dgr->description  = $grp->description;
                $dgr->descriptionformat = $grp->descriptionformat;
                $dgr->id = $DB->insert_record('groups', $dgr);
                if(empty($dgr->id)) {
                    echo "?? erro ao criar grupo\n";
                    exit;
                }
            }
            echo "   -- inserindo membros: ";
            foreach($members AS $member) {
                if (!$DB->get_field('groups_members', 'id', array('groupid'=>$dgr->id, 'userid'=>$member->userid))) {
                    if ($DB->get_field('role_assignments', 'id', array('contextid'=>$cdest->contextid, 'userid'=>$member->userid))) {
                        groups_add_member($dgr->id, $member->userid);
                        echo $member->userid . ', ';
                    } else {
                        echo "\t?? usuario id: {$member->userid} não matriculado no curso\n";
                    }
                }
            }
            echo "\n";

            echo "   -- removendo membros: ";
            $members_dest = $DB->get_records('groups_members', array('groupid'=>$dgr->id), '', 'id, groupid, userid');
            foreach($members_dest AS $id=>$usum) {
                if(!isset($userids_members[$usum->userid])) {
                    groups_remove_member($dgr->id, $usum->userid);
                    echo $usum->userid . ', ';
                }
            }
            echo "\n";
        }
    }
}

function obtem_curso($shortname) {
    global $DB;

    if ($course = $DB->get_record('course', array('shortname'=>$shortname), 'id, shortname, fullname')) {
        if ($ctx_id = $DB->get_field('context', 'id', array('contextlevel'=>50, 'instanceid'=>$course->id))) {
            $course->contextid = $ctx_id;
            echo "\tcurso: {$course->id} - {$course->fullname}\n";
            return $course;
        } else {
            echo "\t?? erro ao buscar contexto do curso: {$shortname}\n";
            return false;
        } 
    } else {
        echo "\t?? Curso não localizado: {$shortname}\n";
        return false;
    }
}

function obtem_grupos_curso($courseid, $grupos=array()) {
    global $DB;

    $erro = false;
    if ($grupos_orig = $DB->get_records('groups', array('courseid'=>$courseid), 'name', 'id, name, description, descriptionformat')) {
        $grp_localizados = array();
        $grp_descartados = array();
        foreach($grupos_orig AS $grp_id=>$grp) {
            if(empty($grupos) || in_array($grp->name, $grupos)) {
                $grp_localizados[] = $grp->name;
                echo "\t{$grp->id} - {$grp->name}\n";
            } else {
                $grp_descartados[] = $grp->name;
                unset($grupos_orig[$grp_id]);
            }
        }
        $grupos = array_diff($grupos, $grp_localizados);
        if(!empty($grupos)) {
            foreach($grupos AS $g) {
                echo "\t?? Grupo: '{$g}' não localizado no curso de origem\n";
                $erro = true;
            }
            if($erro && count($grp_descartados) > 0) {
                echo "\tOutros grupos do curso de origem:\n";
                foreach($grp_descartados AS $g) {
                    echo "\t\t'{$g}'\n";
                }
            }
        }
    } else {
        echo "\t?? Não foram localizados grupos no curso de origem.\n";
        $erro = true;
    }
    if($erro) {
        return false;
    } else {
        return $grupos_orig;
    }
}

function obtem_sincronizacao($conf) {
    if(!is_readable($conf)) {
         echo "??? Não localizado ou sem permissão de leitura ao arquivo de configuração da sincronização: '{$conf}'\n";
         return false;
    }
    $sincs = new stdClass;
    $sincs->contexto = '';
    $sincs->sincronizacoes = array();

    $texto = file_get_contents($conf);
    $pattern = '|\[([a-z]+)\]([^\[]+)|';
    preg_match_all($pattern, $texto, $matches);

    $titulos = $matches[1];
    $valores = $matches[2];

    $sinc = new stdClass;
    foreach($titulos AS $i=>$tit) {
        switch ($tit) {
           case 'contexto' :
                $sincs->contexto = trim($valores[$i], " \n");
                break;
           case 'origem' :
                if(isset($sinc->origem)) {
                    echo "\tOrigem sem correspondentes destinos: '{$sinc->origem}'\n";
                    return false;
                }
                $sinc->origem = trim($valores[$i], " \n");
                $sinc->grupos = array();
                break;
           case 'grupos' :
                if(!isset($sinc->origem)) {
                    echo "\tGrupos sem correspondente origem: '{$valores[$i]}'\n";
                    return false;
                }
                $sinc->grupos = separa_itens($valores[$i]);
                break;
           case 'destinos' :
                $destinos = explode("\n", $valores[$i]);
                if(!isset($sinc->origem)) {
                    echo "\tDestinos sem correspondente origem: '{$valores[$i]}'\n";
                    return false;
                }
                if(isset($sinc->destinos)) {
                    echo "\tDestinos sem correspondente origem: '{" . implode("\n", $sinc->destinos) . "}'\n";
                    return false;
                }
                $sinc->destinos = separa_itens($valores[$i]);
                $sincs->sincronizacoes[] = $sinc;
                $sinc = new stdClass;
                break;
           default:
                echo "\tTipo inválido para seção na configuração: '{$tit}'\n";
                return false;
        }
    }
    return $sincs;
}

function separa_itens($string) {
    $linhas = explode("\n", $string);
    $itens = array();
    foreach($linhas AS $l) {
        $lp = trim($l, " \n");
        if(!empty($lp) && $l[0] != '#') {
            $itens[] = $l;
        }
    }
    return $itens;
}

