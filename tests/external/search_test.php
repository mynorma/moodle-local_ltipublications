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

/**
 * Tests the search of the LTI publications.
 *
 * @package    local_ltipublications
 * @category   test
 * @covers     \local_ltipublications\external\search
 * @copyright  2026 CEI - Comitato Elettrotecnico Italiano
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class search_test extends externallib_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assignUserCapability('local/ltipublications:search', context_system::instance()->id);
    }

    public function test_finds_a_publication_by_part_of_its_course_name(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Impianti elettrici utilizzatori']);
        $tool = $this->publish(['courseid' => $course->id]);

        $this->assertSame(
            [['uuid' => $tool->uuid, 'title' => 'Impianti elettrici utilizzatori']],
            $this->search('ELETTRICI')
        );
    }

    public function test_finds_a_publication_by_its_own_name(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Corso base']);
        $tool = $this->publish(['courseid' => $course->id, 'name' => 'Modulo sicurezza']);

        $this->assertSame([['uuid' => $tool->uuid, 'title' => 'Corso base']], $this->search('sicurezza'));
    }

    public function test_finds_a_publication_by_its_uuid(): void {
        $tool = $this->publish(['courseid' => $this->getDataGenerator()->create_course()->id]);
        $this->publish(['courseid' => $this->getDataGenerator()->create_course()->id]);

        $this->assertSame([$tool->uuid], array_column($this->search($tool->uuid), 'uuid'));
    }

    public function test_titles_an_activity_publication_with_the_activity_name(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Corso con attività']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'name' => 'Esercitazione finale']);
        $tool = $this->publish(['courseid' => $course->id, 'cmid' => $page->cmid]);

        $this->assertSame([['uuid' => $tool->uuid, 'title' => 'Esercitazione finale']], $this->search('attività'));
    }

    public function test_ignores_disabled_and_legacy_publications(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Corso nascosto']);
        $this->publish(['courseid' => $course->id, 'status' => ENROL_INSTANCE_DISABLED]);
        $this->publish(['courseid' => $course->id, 'ltiversion' => 'LTI-1p0/LTI-2p0']);

        $this->assertSame([], $this->search('nascosto'));
    }

    public function test_searches_hidden_courses_too(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Corso riservato', 'visible' => 0]);
        $tool = $this->publish(['courseid' => $course->id]);

        $this->assertSame([$tool->uuid], array_column($this->search('riservato'), 'uuid'));
    }

    public function test_treats_like_wildcards_as_plain_text(): void {
        $this->publish(['courseid' => $this->getDataGenerator()->create_course(['fullname' => 'Corso base'])->id]);

        $this->assertSame([], $this->search('%'));
        $this->assertSame([], $this->search('_'));
    }

    public function test_lists_the_publications_for_an_empty_query(): void {
        $tool = $this->publish(['courseid' => $this->getDataGenerator()->create_course()->id]);

        $this->assertSame([$tool->uuid], array_column($this->search('  '), 'uuid'));
    }

    public function test_returns_at_most_twenty_publications(): void {
        for ($i = 1; $i <= 21; $i++) {
            $this->publish(['courseid' => $this->getDataGenerator()->create_course(['fullname' => "Corso $i"])->id]);
        }

        $this->assertCount(20, $this->search('Corso'));
    }

    public function test_refuses_a_user_without_the_capability(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(required_capability_exception::class);
        search::execute('Corso');
    }

    /**
     * Publishes a course or an activity as an LTI 1.3 tool, unless the fields say otherwise.
     *
     * @param array $fields the enrolment instance fields
     * @return stdClass the publication
     */
    private function publish(array $fields): stdClass {
        return $this->getDataGenerator()->create_lti_tool((object) ($fields + ['ltiversion' => 'LTI-1p3']));
    }

    /**
     * Searches the publications the way a web service client sees the result.
     *
     * @param string $query the search text
     * @return array the publications found
     */
    private function search(string $query): array {
        return external_api::clean_returnvalue(
            search::execute_returns(),
            search::execute($query)
        );
    }
}
