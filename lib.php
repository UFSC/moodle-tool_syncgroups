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

require_once($CFG->libdir . '/formslib.php');

function local_syncgroups_extends_settings_navigation(settings_navigation $nav, context $context = null) {
    global $PAGE;

    // Only add this settings item on non-site course pages.
    if (!$PAGE->course or $PAGE->course->id == 1) {
        return;
    }
    if ($coursenode = $nav->get('courseadmin')) {

        if ($usersnode = $coursenode->get('users')) {

            $str = get_string('pluginname', 'local_syncgroups');
            $url = new moodle_url('/local/syncgroups/index.php', array('courseid' => $context->instanceid));
            $node = navigation_node::create($str, $url, navigation_node::NODETYPE_LEAF, 'local_syncgroups', 'local_syncgroups');

            if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
                $node->make_active();
            }
            $usersnode->add_node($node, 'override');
        }
    }
}

function local_syncgroups_do_sync($grupos, $cdestinos) {
    global $DB;

    foreach ($grupos as $groupid => $groupname) {

        if (!$members = $DB->get_records('groups_members', array('groupid'=>$groupid), '', 'id, groupid, userid')) {
            echo "\t?? grupo vazio. Pulando ....";
            continue;
        }

        $userids_members = array();
        foreach($members AS $id=>$m) {
            $userids_members[$m->userid] = $id;
        }

        foreach($cdestinos AS $cdest) {
            if ($dgr = $DB->get_record('groups', array('courseid'=>$cdest->id, 'name'=>$groupname), 'id, courseid, idnumber, name')) {
                echo "   -- sincronizando em: {$cdest->shortname}\n";
            } else {
                echo "   -- criando grupo em: {$cdest->shortname}\n";
                unset($dgr);
                $dgr->courseid     = $cdest->id;
                $dgr->timecreated  = time();
                $dgr->timemodified = $dgr->timecreated;
                $dgr->name         = $groupname;
               // $dgr->description  = $grp->description;
               // $dgr->descriptionformat = $grp->descriptionformat;
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
