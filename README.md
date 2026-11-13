# LTI publications (local_ltipublications)

Two read-only web service functions for an LTI 1.3 platform linked to a Moodle site ("Publish as LTI tool"):
one searches the publications, so the platform's users can pick the course or activity a launch opens; the
other lists when the learners who came through a publication completed it.

Moodle core's web services return courses, not the publication an LTI 1.3 launch needs (its uuid, sent as
`custom.id`), and `enrol_lti` ships no web service functions. Moodle sends a course's completion to the platform only with a
grade, through LTI Assignment and Grade Services: a course or activity completed without a grade never reaches
the platform. This plugin fills those two gaps and nothing else.

## Requirements

- Moodle 4.5 LTS.
- The "Publish as LTI tool" enrolment plugin (`enrol_lti`) and LTI authentication (`auth_lti`) enabled, with
  courses or activities published as LTI 1.3 tools.

## What it adds

| Kind | Name |
|---|---|
| Web service function | `local_ltipublications_search`, type `read` |
| Web service function | `local_ltipublications_completions`, type `read` |
| Capability | `local/ltipublications:search`, system context, no archetype |
| Capability | `local/ltipublications:viewcompletions`, system context, no archetype, personal data risk |
| External service | `LTI publications` (shortname `ltipublications`), authorised users only |

It adds no database tables, settings, pages, scheduled tasks, events or observers, and stores no personal
data.

## Searching the publications

`local_ltipublications_search(query)` returns at most 20 enabled LTI 1.3 publications whose course name or
publication name contains `query`, or whose uuid equals it. The match ignores case; it also ignores accents
on MySQL and MariaDB, but not on PostgreSQL. An empty query lists the first 20 publications by course name.
Every enabled publication is searched, whatever the visibility of its course.

```bash
curl https://moodle.example.org/webservice/rest/server.php \
  -d wstoken=<token> \
  -d wsfunction=local_ltipublications_search \
  -d moodlewsrestformat=json \
  -d query=safety
```

```json
[{"uuid": "6f1e0c2a-...", "title": "Electrical safety basics"}]
```

`title` is the published course or activity name.

## Reading the completions

`local_ltipublications_completions(uuid, since)` lists the learners who came through the publication `uuid`
and completed it after the Unix time `since` (0 if left out), oldest first. Each learner is identified by the
`sub` the platform sends in its launches, so the platform never needs Moodle's user ids. A published course
counts as completed by its course completion; a published activity by its activity completion, complete or
passed. An unknown uuid lists nothing. Moodle does not notify the platform, which polls this function.

```bash
curl https://moodle.example.org/webservice/rest/server.php \
  -d wstoken=<token> \
  -d wsfunction=local_ltipublications_completions \
  -d moodlewsrestformat=json \
  -d uuid=6f1e0c2a-... \
  -d since=1767225600
```

```json
[{"sub": "8c2f...", "timecompleted": 1767312000}]
```

## Installation

1. Copy this folder to `local/ltipublications`, or upload the ZIP from *Site administration > Plugins >
   Install plugins*.
2. Complete the upgrade from *Site administration > Notifications*, or run `admin/cli/upgrade.php`.

## Configuration

The site administrator configures access by hand:

1. *Site administration > General > Advanced features*: enable web services.
2. *Site administration > Server > Web services > Manage protocols*: enable REST.
3. Create a dedicated user for the LTI platform.
4. Create a role assignable in the system context that allows only `webservice/rest:use`,
   `local/ltipublications:search` and, if the platform reads completions, `local/ltipublications:viewcompletions`,
   and assign it to that user in the system context.
5. *Site administration > Server > Web services > External services > LTI publications > Authorised users*:
   add the user, optionally with an IP restriction and an expiry date.
6. *Site administration > Server > Web services > Manage tokens > Create token*: that user, the LTI
   publications service, an IP restriction to the platform's server addresses and, if wanted, an expiry date.
7. Give the token to the platform's administrators over a secure channel.

## Privacy

The plugin stores no personal data and declares so through the Privacy API (a null provider). The completions
function returns personal data, the learners' platform ids and completion times, to the users who hold
`local/ltipublications:viewcompletions` only.

## Uninstalling

Uninstalling removes the functions, the service with its authorised users and tokens, and the capabilities.
The dedicated user and role created during configuration stay until the administrator deletes them.

## License

GNU GPL v3 or later, see [LICENSE](LICENSE). Copyright 2026 CEI - Comitato Elettrotecnico Italiano.
