# LTI publications (local_ltipublications)

One read-only web service function that lists the LTI 1.3 publications of a Moodle site ("Publish as LTI
tool"), so that an LTI 1.3 platform can let its users search the course or activity a launch opens.

Moodle core's web services return courses, not the publication an LTI 1.3 launch needs (its uuid, sent as
`custom.id`), and `enrol_lti` ships no web service functions. This plugin fills that gap and nothing else.

## Requirements

- Moodle 4.5 LTS.
- The "Publish as LTI tool" enrolment plugin (`enrol_lti`) and LTI authentication (`auth_lti`) enabled, with
  courses or activities published as LTI 1.3 tools.

## What it adds

| Kind | Name |
|---|---|
| Web service function | `local_ltipublications_search`, type `read` |
| Capability | `local/ltipublications:search`, system context, no archetype |
| External service | `LTI publications` (shortname `ltipublications`), authorised users only |

It adds no database tables, settings, pages, scheduled tasks, events or observers, and stores no personal
data.

## The function

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

## Installation

1. Copy this folder to `local/ltipublications`, or upload the ZIP from *Site administration > Plugins >
   Install plugins*.
2. Complete the upgrade from *Site administration > Notifications*, or run `admin/cli/upgrade.php`.

## Configuration

The site administrator configures access by hand:

1. *Site administration > General > Advanced features*: enable web services.
2. *Site administration > Server > Web services > Manage protocols*: enable REST.
3. Create a dedicated user for the LTI platform.
4. Create a role assignable in the system context that allows only `webservice/rest:use` and
   `local/ltipublications:search`, and assign it to that user in the system context.
5. *Site administration > Server > Web services > External services > LTI publications > Authorised users*:
   add the user, optionally with an IP restriction and an expiry date.
6. *Site administration > Server > Web services > Manage tokens > Create token*: that user, the LTI
   publications service, an IP restriction to the platform's server addresses and, if wanted, an expiry date.
7. Give the token to the platform's administrators over a secure channel.

## Privacy

The plugin stores no personal data and declares so through the Privacy API (a null provider).

## Uninstalling

Uninstalling removes the function, the service with its authorised users and tokens, and the capability.
The dedicated user and role created during configuration stay until the administrator deletes them.

## Issues

Report bugs and request features at https://github.com/mynorma/moodle-local_ltipublications/issues.

## License

GNU GPL v3 or later, see [LICENSE](LICENSE). Copyright 2026 CEI - Comitato Elettrotecnico Italiano.
