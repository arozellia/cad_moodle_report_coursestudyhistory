<?php
declare( strict_types=1 );

namespace report_coursestudyhistory;


use ArrayIterator;
use dml_exception;
use moodle_exception;
use stdClass;

/**
 * Class coursehistory
 *
 * @package report_coursestudyhistory
 */
class coursehistory {
	
	/**
	 * @var int
	 */
	private $userId;
	
	/**
	 * coursehistory constructor.
	 *
	 * @param int $userId
	 */
	public function __construct(string $userId) {
		$this->setUserId($userId);
	}
	
	/**
	 * @return stdClass
	 * @throws dml_exception
	 */
	public function getCourses(): array {
		$getCoursesData = $this->getCoursesData();
		
		$coursedata = [];
		
		foreach ($getCoursesData as $cd) {
			$coursekey = 'c' . $cd->courseid;
			if (!array_key_exists($cd->courseid, $coursedata)) {
				$coursedata[$coursekey]['fullname'] = $cd->fullname;
				$coursedata[$coursekey]['timeenrolled'] = userdate($cd->timeenrolled);
				$coursedata[$coursekey]['coursecompleted'] = ( (boolean)$cd->coursecompleted ) ? '<strong>&#9745;</strong>' : '&#9744;';
				$coursedata[$coursekey]['coursegrade'] = ( (float)$cd->coursegrade ) . '%';
				$coursedata[$coursekey]['certificateissued'] = ( (boolean)$cd->certificateissued ) ? '<strong>&#9745;</strong>' : '&#9744;';
				$coursedata[$coursekey]['courseid'] = (int)$cd->courseid;
			}
			
			if (!empty($cd->itemname)) {
				$gradeitemkey = 'gi' . $cd->gradeitemid;
				$coursedata[$coursekey]['gradeitems'][$gradeitemkey]['itemname'] = $cd->itemname;
				$coursedata[$coursekey]['gradeitems'][$gradeitemkey]['assignmentcompleted'] = ( (boolean)$cd->assignmentcompleted ) ? '<strong>&#9745;</strong>' : '&#9744;';
				$coursedata[$coursekey]['gradeitems'][$gradeitemkey]['finalgrade'] = ( (float)$cd->finalgrade ) . '%';
			}
			
		}
		
		$coursedata = array_map(function ($cd) {
			if (!empty($cd['gradeitems'])) {
				$cd['gradeitems'] = new ArrayIterator($cd['gradeitems']);
			}
			return $cd;
		}, $coursedata);
		
		$coursedata = new ArrayIterator($coursedata);
		
		$getcourses = ['coursedata' => $coursedata];
		
		return $getcourses;
	}
	
	/**
	 * @return stdClass
	 * @throws dml_exception
	 */
	private function getCoursesData(): array {
		global $DB;
		
		$sql = " SELECT DISTINCT UUID() AS rand, 
                 		c.id AS courseid,
		                u.id AS userid,
                 		gi.id AS gradeid,
		                c.fullname,
		                u.firstname,
		                u.lastname,
		                CASE
		                  WHEN gi.itemtype = 'course' OR gi.hidden = 1 THEN NULL
		                  ELSE gi.itemname
		                end                                                AS itemname,
		                round(((gg.finalgrade/gi.grademax) * 100)) as finalgrade,
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
	                               AND iissues.userid = u.id
	                               LIMIT 1)
	                               ,
							(SELECT 1
	                        from mdl_customcert icert
		                        inner join mdl_customcert_issues iissues
		                        on iissues.customcertid = icert.id
	                        WHERE  icert.course = c.id
	                               AND iissues.userid = u.id
	                               LIMIT 1) 
	                               , 0)        AS
		                certificateissued,
		                (SELECT round(finalgrade) as finalgrade
		                 FROM   {grade_grades} fgg
		                        INNER JOIN {grade_items} fgi
		                                ON fgg.itemid = fgi.id
		                 WHERE  fgi.itemtype = 'course'
		                        AND fgi.courseid = c.id
		                        AND fgg.userid = u.id)                     AS
		                coursegrade,
		                case when gi.hidden = 1 or gi.itemtype = 'course' then 0 else gi.sortorder end AS sortorder,	
                 		case when gi.hidden = 1 or gi.itemtype = 'course' or gi.hidden = 1 or gi.itemtype = 'checklist' or gi.itemname is null or  gi.itemtype != 'mod' 
							then 0 else 
								(SELECT scs.section
								  FROM {grade_items} sgi
									INNER JOIN {course_modules} scm ON scm.course = sgi.courseid AND scm.instance = sgi.iteminstance
									inner join {course_sections} scs on scs.course = sgi.courseid and FIND_IN_SET(scm.id,scs.sequence)
									WHERE sgi.id = gi.id
								    and sgi.itemtype = 'mod' 
					                AND scm.deletioninprogress = 0 
					                AND scm.course = c.id
					                limit 1
					            )
					        end as sectionorder
		FROM   {user} u
		       INNER JOIN {role_assignments} ra
		               ON ra.userid = u.id and ra.roleid = 5
		       INNER JOIN {context} ctx
		               ON ra.contextid = ctx.id
		                  AND ctx.contextlevel = 50
		       INNER JOIN {course} c
		               ON c.id = ctx.instanceid
		       LEFT OUTER JOIN {course_modules} cm
		               ON cm.course = c.id
		                  AND cm.course = c.id
		       LEFT OUTER JOIN {grade_items} gi
		               ON gi.courseid = c.id
		                   		-- Courses with visible assignments or a checklist. 
		                  AND ( ( gi.iteminstance = cm.instance
		                          AND ( gi.itemmodule = 'checklist'
		                                 OR gi.hidden = 0 )
		                          AND gi.itemtype <> 'course'
		                          AND gi.itemname IS NOT NULL )
		                   		-- Courses with no assignments.
		                         OR NOT EXISTS (SELECT 1
		                                        FROM   {grade_items} egi
		                                        WHERE  gi.courseid = egi.courseid
		                                               AND egi.itemtype <> 'course') 
		                   		 -- Courses with no visible assignments.
	                             OR NOT EXISTS (SELECT 1
                                        FROM   mdl_grade_items egi
                                        WHERE  gi.courseid = egi.courseid
                                               AND egi.itemtype <> 'course'
                                               and egi.hidden = 0))
		       LEFT OUTER JOIN {grade_grades} gg
		                    ON gg.userid = u.id
		                    AND gg.itemid = gi.id
		                   	AND gi.hidden = 0
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
		ORDER  BY timeenrolled DESC,
		          c.fullname,
		          sectionorder,
		          sortorder,
		          itemname";
		
		$result = $DB->get_records_sql($sql, [$this->userId]);
		
		return $result;
	}
	
	/**
	 * @return int
	 */
	public function getUserId(): string {
		return $this->userId;
	}
	
	/**
	 * Returns the number of courses completed based on certificate issues.
	 *
	 * @return array Completion percent data.
	 * @throws moodle_exception
	 */
	public function get_completion_data(): stdClass {
		global $DB;
		
		$sql = "select ifnull(round((sum(case when cc.timecompleted is not null then 1 else 0 end )/ count(c.id)) * 100),0) as percentcompleted
					, count(c.id) as coursestocomplete
				    , sum(case when cc.timecompleted is not null then 1 else 0 end ) as coursescompleted
				from {role_assignments} ra
				inner join {context} ctx on ctx.id = ra.contextid and ctx.contextlevel = 50
				inner join {course} c on c.id = ctx.instanceid and c.visible = 1
				inner join {course_completions} cc on cc.course = c.id and cc.userid = ra.userid
				where ra.userid = ?
				and ra.roleid = 5
				group by ra.userid";
		$result = $DB->get_record_sql($sql, [$this->userId]);
		
		if (!$result) {
			$result = new stdClass();
			$result->percentcompleted = 0;
			$result->coursestocomplete = 0;
			$result->coursescompleted = 0;
		}
		
		return $result;
	}
	
	/**
	 * @param int $userId
	 */
	public function setUserId(string $userId) {
		$this->userId = $userId;
	}
}
