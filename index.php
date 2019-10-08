<?php

declare( strict_types=1 );

namespace report_coursestudyhistory;

use coding_exception;
use context_system;
use moodle_url;

require_once( '../../config.php' );
require_login();

global $CFG, $PAGE, $USER, $COURSE;

$userid = optional_param('userid', $USER->id, PARAM_CLEAN);
$course = optional_param('course', $COURSE->id, PARAM_INT);

$systemcontext = \context_system::instance();
$coursecontext   = \context_course::instance($course);
$context = $coursecontext;

if (has_capability('report/coursestudyhistory:view', $systemcontext)) {
	$context = $systemcontext;
} else if  (has_capability('report/coursestudyhistory:viewstudent', $coursecontext)) {
	// teacher.
} else {
	$userid = $USER->id;
}

require_once( $CFG->libdir . '/adminlib.php' );

// Get plugin data.
$courseuser = new courseuser($userid);
$coursehistory = new coursehistory($userid);

$url = new moodle_url("/report/coursestudyhistory/index.php");

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_cacheable(false);

$title = get_string('usertitle', 'report_coursestudyhistory')
	. ' for '
	. $courseuser->getUserDetails()->firstname
	. ' '
	. $courseuser->getUserDetails()->lastname;

$PAGE->set_title($title);
$PAGE->set_heading($title);

$output = $PAGE->get_renderer('report_coursestudyhistory');

echo $output->header();

$renderable = new output\index_page($courseuser, $coursehistory);
echo $output->render($renderable);

echo $output->footer();
