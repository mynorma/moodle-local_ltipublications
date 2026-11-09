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

/**
 * Searches the enabled LTI 1.3 publications by name, or finds one by its uuid.
 *
 * @package    local_ltipublications
 * @copyright  2026 CEI - Comitato Elettrotecnico Italiano
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search extends external_api {
    /** @var int The most publications one search returns. */
    private const LIMIT = 20;

    /**
     * Describes the parameters of execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Part of the course or publication name, or a publication uuid'),
        ]);
    }

    /**
     * Searches the enabled LTI 1.3 publications by course or publication name, or finds one by its uuid.
     *
     * @param string $query part of the course or publication name, or a publication uuid
     * @return array the matching publications, each with its uuid and title
     */
    public static function execute(string $query): array {
        global $DB;
        ['query' => $query] = self::validate_parameters(self::execute_parameters(), ['query' => $query]);
        $system = context_system::instance();
        self::validate_context($system);
        require_capability('local/ltipublications:search', $system);

        $query = trim($query);
        $pattern = '%' . $DB->sql_like_escape($query) . '%';
        $coursename = $DB->sql_like('c.fullname', ':coursename', false, false);
        $publicationname = $DB->sql_like('e.name', ':publicationname', false, false);
        $records = $DB->get_records_sql(
            "SELECT t.id, t.uuid, t.contextid, c.fullname
               FROM {enrol_lti_tools} t
               JOIN {enrol} e ON e.id = t.enrolid
               JOIN {course} c ON c.id = e.courseid
              WHERE e.enrol = 'lti' AND e.status = :enabled AND t.ltiversion = 'LTI-1p3'
                AND (t.uuid = :uuid OR $coursename OR $publicationname)
           ORDER BY c.fullname, t.id",
            ['enabled' => ENROL_INSTANCE_ENABLED, 'uuid' => $query,
                'coursename' => $pattern, 'publicationname' => $pattern],
            0,
            self::LIMIT
        );

        return array_values(array_map(static function ($record) {
            $published = context::instance_by_id($record->contextid, IGNORE_MISSING);
            return [
                'uuid' => $record->uuid,
                'title' => $published ? $published->get_context_name(false, false, false) : $record->fullname,
            ];
        }, $records));
    }

    /**
     * Describes the value execute returns.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'uuid' => new external_value(PARAM_ALPHANUMEXT, 'The publication id a launch carries as custom.id'),
            'title' => new external_value(PARAM_TEXT, 'The published course or activity name'),
        ]));
    }
}
