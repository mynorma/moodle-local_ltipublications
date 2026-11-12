# Security policy

## Supported versions

Each Moodle version the plugin supports has its own branch. Security fixes go to the supported branches and
ship as a new release, with a tag and a ZIP package.

| Plugin | Moodle | Branch | Security fixes |
|---|---|---|---|
| 1.1.x | 4.5 LTS | `MOODLE_405_STABLE` | yes |
| 1.0.x | 4.5 LTS | none | no, upgrade to 1.1 |

`main` is where the next version is developed. Before a new Moodle version is supported, the plugin is tested
on it and released on a new `MOODLE_XXX_STABLE` branch.

## Reporting a vulnerability

Do not open a public issue. Report the vulnerability privately through GitHub:
[Security > Report a vulnerability](https://github.com/mynorma/moodle-local_ltipublications/security/advisories/new).

Include the plugin and Moodle versions, the steps to reproduce and the impact you expect. We confirm we have
received the report, agree with you on the fix and the disclosure date, then publish a fixed release and a
GitHub security advisory that credits you, unless you prefer otherwise.

## What the plugin exposes

- Two read-only web service functions, `local_ltipublications_search` and
  `local_ltipublications_completions`, behind the capabilities `local/ltipublications:search` and
  `local/ltipublications:viewcompletions`. Both capabilities are in the system context and given to no
  archetype; the second is flagged as a personal data risk.
- The external service `LTI publications`, open to authorised users only.

Every call validates its parameters, validates the system context and checks the capability before it reads
anything. The queries go through Moodle's database API with bound parameters, and the search escapes the
`LIKE` wildcards. The plugin adds no database tables, settings, pages, forms, files, scheduled tasks or events,
and stores no personal data.
