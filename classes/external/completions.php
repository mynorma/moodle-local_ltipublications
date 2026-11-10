<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_ltipublications\external;

use context;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

/**
 * Lists when the learners who came through an LTI 1.3 publication completed the published course or activity.
 *
 * @package    local_ltipublications
 * @copyright  2026 CEI - Comitato Elettrotecnico Italiano
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completions extends external_api {
    /**
     * Describes the parameters of execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'uuid' => new external_value(PARAM_ALPHANUMEXT, 'The publication uuid'),
            'since' => new external_value(PARAM_INT, 'Only the completions after this Unix time', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Lists the completions of a publication's learners, each with the platform's user id.
     *
     * @param string $uuid the publication uuid
     * @param int $since only the completions after this Unix time
     * @return array the completions, oldest first, each with the platform's user id and the completion time
     */
    public static function execute(string $uuid, int $since = 0): array {
        global $DB;
        ['uuid' => $uuid, 'since' => $since] = self::validate_parameters(
            self::execute_parameters(),
            ['uuid' => $uuid, 'since' => $since]
        );
        $system = context_system::instance();
        self::validate_context($system);
        require_capability('local/ltipublications:viewcompletions', $system);

        $tool = $DB->get_record('enrol_lti_tools', ['uuid' => $uuid, 'ltiversion' => 'LTI-1p3'], 'id, contextid');
        $published = $tool ? context::instance_by_id($tool->contextid, IGNORE_MISSING) : false;
        if (!$published) {
            return [];
        }

        $learners = "FROM {enrol_lti_users} lu
                     JOIN {enrol_lti_deployment} d ON d.id = lu.ltideploymentid
                     JOIN {enrol_lti_app_registration} r ON r.id = d.platformid
                     JOIN {auth_lti_linked_login} l ON l.userid = lu.userid
                      AND " . $DB->sql_compare_text('l.issuer') . ' = ' . $DB->sql_compare_text('r.platformid');
        $params = ['toolid' => $tool->id, 'since' => $since];
        if ($published->contextlevel == CONTEXT_MODULE) {
            $sql = "SELECT lu.id, l.sub, cmc.timemodified AS timecompleted
                    $learners
                    JOIN {course_modules_completion} cmc ON cmc.userid = lu.userid AND cmc.coursemoduleid = :cmid
                   WHERE lu.toolid = :toolid AND cmc.timemodified > :since
                     AND cmc.completionstate IN (:complete, :completepass)
                ORDER BY cmc.timemodified, lu.id";
            $params += ['cmid' => $published->instanceid,
                'complete' => COMPLETION_COMPLETE, 'completepass' => COMPLETION_COMPLETE_PASS];
        } else {
            $sql = "SELECT lu.id, l.sub, cc.timecompleted
                    $learners
                    JOIN {course_completions} cc ON cc.userid = lu.userid AND cc.course = :courseid
                   WHERE lu.toolid = :toolid AND cc.timecompleted > :since
                ORDER BY cc.timecompleted, lu.id";
            $params += ['courseid' => $published->get_course_context()->instanceid];
        }

        return array_values(array_map(static fn($record) => [
            'sub' => $record->sub,
            'timecompleted' => (int) $record->timecompleted,
        ], $DB->get_records_sql($sql, $params)));
    }

    /**
     * Describes the value execute returns.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'sub' => new external_value(PARAM_RAW, 'The user id the platform sends as sub in its launches'),
            'timecompleted' => new external_value(PARAM_INT, 'When the learner completed the course or activity'),
        ]));
    }
}
