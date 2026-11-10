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

use context_system;
use core_external\external_api;
use core_external\tests\externallib_testcase;
use required_capability_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');

/**
 * Tests the completions of the LTI publications.
 *
 * @package    local_ltipublications
 * @category   test
 * @covers     \local_ltipublications\external\completions
 * @copyright  2026 CEI - Comitato Elettrotecnico Italiano
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class completions_test extends externallib_testcase {
    /** @var string The issuer of the platform the learners come from. */
    private const ISSUER = 'https://platform.example.org';

    /** @var int The deployment the learners launch through. */
    private int $deploymentid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assignUserCapability('local/ltipublications:viewcompletions', context_system::instance()->id);
        $this->deploymentid = $this->deployment(self::ISSUER);
    }

    public function test_lists_the_learners_who_completed_a_published_course_oldest_first(): void {
        $course = $this->getDataGenerator()->create_course();
        $tool = $this->publish(['courseid' => $course->id]);
        $this->complete_course($this->learner($tool, 'late'), $course, 2000);
        $this->complete_course($this->learner($tool, 'early'), $course, 1000);
        $this->learner($tool, 'unfinished');

        $this->assertSame(
            [['sub' => 'early', 'timecompleted' => 1000], ['sub' => 'late', 'timecompleted' => 2000]],
            $this->completions($tool->uuid)
        );
    }

    public function test_lists_only_the_completions_after_since(): void {
        $course = $this->getDataGenerator()->create_course();
        $tool = $this->publish(['courseid' => $course->id]);
        $this->complete_course($this->learner($tool, 'early'), $course, 1000);
        $this->complete_course($this->learner($tool, 'late'), $course, 2000);

        $this->assertSame([['sub' => 'late', 'timecompleted' => 2000]], $this->completions($tool->uuid, 1000));
    }

    public function test_leaves_out_the_learners_of_another_publication(): void {
        $course = $this->getDataGenerator()->create_course();
        $tool = $this->publish(['courseid' => $course->id]);
        $other = $this->publish(['courseid' => $course->id]);
        $this->complete_course($this->learner($other, 'elsewhere'), $course, 1000);

        $this->assertSame([], $this->completions($tool->uuid));
    }

    public function test_gives_the_sub_from_the_platform_the_learner_launched_from(): void {
        $course = $this->getDataGenerator()->create_course();
        $tool = $this->publish(['courseid' => $course->id]);
        $userid = $this->learner($tool, 'ours');
        get_auth_plugin('lti')->create_user_binding('https://other.example.org', 'theirs', $userid);
        $this->complete_course($userid, $course, 1000);

        $this->assertSame([['sub' => 'ours', 'timecompleted' => 1000]], $this->completions($tool->uuid));
    }

    public function test_uses_the_activity_completion_for_a_published_activity(): void {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id]);
        $tool = $this->publish(['courseid' => $course->id, 'cmid' => $page->cmid]);
        $this->complete_activity($this->learner($tool, 'done'), $page->cmid, COMPLETION_COMPLETE, 1000);
        $this->complete_activity($this->learner($tool, 'passed'), $page->cmid, COMPLETION_COMPLETE_PASS, 2000);
        $this->complete_activity($this->learner($tool, 'failed'), $page->cmid, COMPLETION_COMPLETE_FAIL, 3000);
        $this->complete_course($this->learner($tool, 'courseonly'), $course, 4000);

        $this->assertSame(['done', 'passed'], array_column($this->completions($tool->uuid), 'sub'));
    }

    public function test_finds_nothing_for_an_unknown_uuid(): void {
        $this->assertSame([], $this->completions('00000000-0000-0000-0000-000000000000'));
    }

    public function test_refuses_a_user_without_the_capability(): void {
        $tool = $this->publish(['courseid' => $this->getDataGenerator()->create_course()->id]);
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(required_capability_exception::class);
        completions::execute($tool->uuid);
    }

    /**
     * Publishes a course or an activity as an LTI 1.3 tool.
     *
     * @param array $fields the enrolment instance fields
     * @return stdClass the publication
     */
    private function publish(array $fields): stdClass {
        return $this->getDataGenerator()->create_lti_tool((object) ($fields + ['ltiversion' => 'LTI-1p3']));
    }

    /**
     * Registers a platform with one deployment.
     *
     * @param string $issuer the platform's issuer
     * @return int the deployment id
     */
    private function deployment(string $issuer): int {
        global $DB;
        $now = time();
        $registrationid = $DB->insert_record('enrol_lti_app_registration', [
            'name' => 'Platform', 'platformid' => $issuer, 'clientid' => 'client', 'uniqueid' => uniqid(),
            'status' => 1, 'timecreated' => $now, 'timemodified' => $now,
        ]);
        return $DB->insert_record('enrol_lti_deployment', [
            'name' => 'Deployment', 'deploymentid' => 'deployment', 'platformid' => $registrationid,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Makes a learner who launched the publication from the platform.
     *
     * @param stdClass $tool the publication
     * @param string $sub the learner's id on the platform
     * @return int the learner's Moodle user id
     */
    private function learner(stdClass $tool, string $sub): int {
        global $DB;
        $userid = $this->getDataGenerator()->create_user(['auth' => 'lti'])->id;
        get_auth_plugin('lti')->create_user_binding(self::ISSUER, $sub, $userid);
        $DB->insert_record('enrol_lti_users', [
            'userid' => $userid, 'toolid' => $tool->id, 'ltideploymentid' => $this->deploymentid, 'timecreated' => time(),
        ]);
        return $userid;
    }

    /**
     * Records that a learner completed a course.
     *
     * @param int $userid the learner
     * @param stdClass $course the course
     * @param int $time the completion time
     */
    private function complete_course(int $userid, stdClass $course, int $time): void {
        global $DB;
        $DB->insert_record('course_completions', [
            'userid' => $userid, 'course' => $course->id, 'timeenrolled' => 0, 'timestarted' => 0, 'timecompleted' => $time,
        ]);
    }

    /**
     * Records a learner's completion state of an activity.
     *
     * @param int $userid the learner
     * @param int $cmid the activity
     * @param int $state the completion state
     * @param int $time when the state was reached
     */
    private function complete_activity(int $userid, int $cmid, int $state, int $time): void {
        global $DB;
        $DB->insert_record('course_modules_completion', [
            'coursemoduleid' => $cmid, 'userid' => $userid, 'completionstate' => $state, 'timemodified' => $time,
        ]);
    }

    /**
     * Lists the completions the way a web service client sees them.
     *
     * @param string $uuid the publication uuid
     * @param int $since only the completions after this Unix time
     * @return array the completions
     */
    private function completions(string $uuid, int $since = 0): array {
        return external_api::clean_returnvalue(
            completions::execute_returns(),
            completions::execute($uuid, $since)
        );
    }
}
