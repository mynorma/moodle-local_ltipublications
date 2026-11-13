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

Do not open an issue or a pull request. Report the vulnerability privately to the repository's owners.

Include the plugin and Moodle versions, the steps to reproduce and the impact you expect.

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
