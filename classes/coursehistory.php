<?php
declare(strict_types=1);

namespace report_coursestudyhistory;


use stdClass;

/**
 * Class coursehistory
 * @package report_coursestudyhistory
 */
class coursehistory
{

    /**
     * @var int
     */
    private $userId;
	
	/**
	 * Returns the number of courses completed based on certificate issues.
	 *
	 * @return array Completion percent data.
	 * @throws \moodle_exception
	 */
	public function get_completion_data() : stdClass {
		global $DB; 
		
		$sql = "select round(count(distinct cert.course + ccert.course)  / count(distinct ctx.instanceid), 2) * 100 as percentcompleted
					, count(distinct cert.course) AS coursescompleted
					, count(distinct ctx.instanceid) AS coursestocomplete
				from {role_assignments} ra
				inner join {context} ctx on ctx.id = ra.contextid and ctx.contextlevel = 50
			    inner join mdl_course c on c.id = ctx.instanceid and c.visible = 1
				left outer join {certificate} cert on cert.course = ctx.instanceid
				left outer join {certificate_issues} issues on issues.certificateid = cert.id
				left outer join {customcert} ccert on cert.course = ctx.instanceid
				left outer join {customcert_issues} cissues on issues.certificateid = cert.id
				where ra.userid = ?
				and ra.roleid = 5";
		$result = $DB->get_record_sql($sql, [$this->userId]);
		
		return $result;
	}

    /**
     * coursehistory constructor.
     * @param int $userId
     */
    public function __construct(string $userId)
    {
        $this->setUserId($userId);
    }

    /**
     * @return int
     */
    public function getUserId() : string
    {
        return $this->userId;
    }

    /**
     * @param int $userId
     */
    public function setUserId(string $userId)
    {
        $this->userId = $userId;
    }
	
	/**
	 * @return stdClass
	 * @throws \dml_exception
	 */
	public function getCourses(): array
	{
		$getCoursesData = $this->getCoursesData();
		
		$coursedata = [];

		foreach ($getCoursesData as $cd) {
			$coursekey = 'c' . $cd->courseid;
			if (!array_key_exists($cd->courseid, $coursedata)) {
				$coursedata[$coursekey]['fullname'] = $cd->fullname;
				$coursedata[$coursekey]['timeenrolled'] = userdate($cd->timeenrolled);
				$coursedata[$coursekey]['coursecompleted'] = ((boolean)  $cd->coursecompleted) ? '&#9745;' : '&#9744;';
				$coursedata[$coursekey]['coursegrade'] = ((float) $cd->coursegrade) . '%';
				$coursedata[$coursekey]['certificateissued'] = ((boolean) $cd->certificateissued) ? '&#9745;' : '&#9744;';
				$coursedata[$coursekey]['courseid'] = (int) $cd->courseid;
			}
			
			if (!empty($cd->itemname)) {
				$gradeitemkey = 'gi' . $cd->gradeitemid;
				$coursedata[$coursekey]['gradeitems'][$gradeitemkey]['itemname'] = $cd->itemname;
				$coursedata[$coursekey]['gradeitems'][$gradeitemkey]['assignmentcompleted'] = ((boolean)  $cd->assignmentcompleted) ? '&#9745;' : '&#9744;';
				$coursedata[$coursekey]['gradeitems'][$gradeitemkey]['finalgrade'] = ((float) $cd->finalgrade) . '%';
			}

		}

		$coursedata = array_map(function ($cd){
			if (!empty($cd['gradeitems'])) {
				$cd['gradeitems'] = new \ArrayIterator($cd['gradeitems']);
			}
			return $cd;
		}, $coursedata);
		
		$coursedata = new \ArrayIterator($coursedata);
		
		$getcourses = ['coursedata' => $coursedata];
		
		return $getcourses;
	}
    /**
     * @return stdClass
     * @throws \dml_exception
     */
    private function getCoursesData(): array
    {
        global $DB;

        $sql = " SELECT DISTINCT UUID() AS rand, 
                 		c.id AS courseid,
		                u.id AS userid,
                 		gi.id AS gradeid,
		                c.fullname,
		                u.firstname,
		                u.lastname,
		                CASE
		                  WHEN gi.itemtype = 'course' THEN NULL
		                  ELSE gi.itemname
		                end                                                AS itemname,
		                ((gg.finalgrade/gi.grademax) * 100) as finalgrade,
                 		gi.id as gradeitemid,
		                Coalesce((SELECT Min(tue.timestart)
		                          FROM   {user_enrolments} tue
		                                 INNER JOIN {enrol} te
		                                         ON tue.enrolid = te.id
		                                 INNER JOIN {course} tc
		                                         ON tc.id = te.courseid
		                          WHERE  tue.userid = u.id
		                                 AND tc.id = c.id
		                                 AND te.enrol = 'meta'
		                                 AND tue.timestart <> 0
		                          GROUP  BY te.courseid), ra.timemodified) AS
		                timeenrolled,
		                CASE
		                  WHEN mcompletion.completionstate IS NOT NULL
		                       AND mcompletion.completionstate = 2
		                        OR ( gg.finalgrade IS NOT NULL
		                             AND crit.gradepass IS NULL
		                             AND gg.finalgrade >= gi.gradepass
		                             AND mcompletion.completionstate IS NULL )
		                        OR ( crit.gradepass IS NOT NULL
		                             AND gg.finalgrade >= crit.gradepass
		                             AND mcompletion.completionstate IS NULL ) THEN 1
		                  ELSE 0
		                end                                                AS
		                assignmentcompleted,
		                CASE
		                  WHEN cc.timecompleted IS NOT NULL THEN 1
		                  ELSE 0
		                end                                                AS
		                coursecompleted,
		                Coalesce((SELECT 1
	                        FROM   mdl_certificate icert
	                               INNER JOIN mdl_certificate_issues iissues
	                                       ON iissues.certificateid = icert.id
	                        WHERE  icert.course = c.id
	                               AND iissues.userid = u.id)
	                               ,
							(SELECT 1
	                        from mdl_customcert icert
		                        inner join mdl_customcert_issues iissues
		                        on iissues.customcertid = icert.id
	                        WHERE  icert.course = c.id
	                               AND iissues.userid = u.id) 
	                               , 0)        AS
		                certificateissued,
		                (SELECT round(finalgrade, 2) as finalgrade
		                 FROM   {grade_grades} fgg
		                        INNER JOIN {grade_items} fgi
		                                ON fgg.itemid = fgi.id
		                 WHERE  fgi.itemtype = 'course'
		                        AND fgi.courseid = c.id
		                        AND fgg.userid = u.id)                     AS
		                coursegrade,
		                (SELECT sgi.sortorder
		                 FROM   {grade_items} sgi
		                 WHERE  sgi.id = gi.id)                            AS sortorder
		FROM   {user} u
		       INNER JOIN {role_assignments} ra
		               ON ra.userid = u.id and ra.roleid = 5
		       INNER JOIN {context} ctx
		               ON ra.contextid = ctx.id
		                  AND ctx.contextlevel = 50
		       INNER JOIN {course} c
		               ON c.id = ctx.instanceid
		                  AND c.visible = 1
		       INNER JOIN {course_modules} cm
		               ON cm.course = c.id
		                  AND cm.course = c.id
		       INNER JOIN {grade_items} gi
		               ON gi.courseid = c.id
		                  AND ( ( gi.iteminstance = cm.instance
		                          AND ( gi.itemmodule = 'checklist'
		                                 OR gi.hidden = 0 )
		                          AND gi.itemtype <> 'course'
		                          AND gi.itemname IS NOT NULL )
		                         OR NOT EXISTS (SELECT 1
		                                        FROM   {grade_items} egi
		                                        WHERE  gi.courseid = egi.courseid
		                                               AND egi.itemtype <> 'course') )
		       LEFT OUTER JOIN {grade_grades} gg
		                    ON gg.userid = u.id
		                       AND gg.itemid = gi.id
		       LEFT OUTER JOIN {course_completions} cc
		                    ON cc.userid = u.id
		                       AND cc.course = c.id
		       LEFT OUTER JOIN {course_completion_criteria} crit
		                    ON crit.course = c.id
		                       AND crit.moduleinstance = cm.id
		       LEFT OUTER JOIN {course_modules_completion} mcompletion
		                    ON mcompletion.coursemoduleid = cm.id
		                       AND mcompletion.userid = u.id
		WHERE  u.id = ?
		ORDER  BY timeenrolled,
		          c.fullname,
		          sortorder,
		          itemname";

	    $result = $DB->get_records_sql($sql, [$this->userId]);
	 
        return $result;
    }
}
