Copy groups between courses Plugin for Moodle
---------------------------------------------------

With this plugin you can synchronize groups and groups members between two courses,
in a way that a course A will be a mirror of course B.

By default, only users that are already enrolled are added as group members,
but you can choose to also enrol users that are not already enrolled.

Install
-------

* Put these files at moodle/local/syncgroups/
 * You may use "composer require moodle-ufsc/local_syncgroups"
 * or git "clone https://gitlab.setic.ufsc.br/moodle-ufsc/moodle-local_syncgroups.git"
 * or download the latest version from https://gitlab.setic.ufsc.br/moodle-ufsc/moodle-local_syncgroups/repository/archive.zip
* Log in your Moodle as Admin and go to "Notifications" page
* Follow the instructions to install the plugin