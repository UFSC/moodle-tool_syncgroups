<?php

class destination_courses_search extends restore_course_search {
    /**
     * Sets up any access restrictions for the courses to be displayed in the search.
     *
     * This will typically call $this->require_capability().
     */
    protected function setup_restrictions() {
        $this->require_capability('moodle/course:managegroups');
    }
}
