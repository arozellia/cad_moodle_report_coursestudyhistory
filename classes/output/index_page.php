<?php
// Standard GPL and phpdocs
namespace report_coursestudyhistory\output;

global $CFG;

require_once($CFG->libdir.'/outputrenderers.php');

use renderable;
use renderer_base;
use templatable;
use stdClass;
use report_coursestudyhistory\courseuser;
use report_coursestudyhistory\coursehistory;

class index_page implements renderable, templatable {
    var $picture = null;
    var $email = null;
    var $messageUrl = null;
    var $preferencesUrl = null;
    var $passwordUrl = null;

    public function __construct(courseuser $courseuser, coursehistory $coursehistory) {
        
        $userData = $courseuser->getUserDetails();
        $courseCompletionData = $coursehistory->get_completion_data();
		$courseHistoryData = $coursehistory->getCourses();

		
        $this->picture = $userData->userpicture;
        $this->email = $userData->email;
        $this->userid = $userData->id;
        $this->percentcompleted = $courseCompletionData->percentcompleted;
        $this->coursescompleted = $courseCompletionData->coursescompleted;
        $this->coursestocomplete = (int) $courseCompletionData->coursestocomplete - (int) $courseCompletionData->coursescompleted;
        $this->coursehistorydata = $courseHistoryData;
		
        $this->messageUrl = new \moodle_url('/message/index.php');
        $this->preferencesUrl = new \moodle_url('/user/preferences.php');
        $this->passwordUrl = new \moodle_url('/login/change_password.php');
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->userpicture = $this->picture;
        $data->useremail = $this->email;
        $data->messageurl = $this->messageUrl;
        $data->prefencesurl = $this->preferencesUrl;
        $data->passwordurl = $this->passwordUrl;
        $data->userid = $this->userid;
	    $data->percentcompleted = $this->percentcompleted;
	    $data->coursescompleted = $this->coursescompleted;
	    $data->coursestocomplete = $this->coursestocomplete;
	    $data->coursehistorydata = $this->coursehistorydata;

        return $data;
    }
}
