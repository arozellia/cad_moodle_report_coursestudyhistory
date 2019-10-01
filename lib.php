<?php

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 *
 * @return bool
 */
function report_coursestudyhistory_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
	global $COURSE;
	
    if (isguestuser($user)) {
        return false;
    }
    $category = new core_user\output\myprofile\category('coursestudyhistory', 'Course and Study history', 'reports');
    $tree->add_category($category);
    
    if ($course === null) {
	    $course = new \stdClass;
    	$course->id = $COURSE->id;
    }
    
    $node = new core_user\output\myprofile\node('coursestudyhistory', 'coursestudyhistorylink', 'View progress',null,
        new moodle_url('/report/coursestudyhistory/index.php', ['userid' => (int) $user->id, 'course' => (int) $course->id]) , '');
    $tree->add_node($node);
 
    return true;
}
