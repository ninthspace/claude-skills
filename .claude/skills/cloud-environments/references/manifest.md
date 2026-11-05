# Manifest

`cloud-environments.json`, at the repository root. `references/example-manifest.json` is a complete generic example.

| Field | Meaning |
| --- | --- |
| `organisation`, `application`, `repository`, `region` | Required. The organisation the CLI must be authenticated to, the application's name, `owner/repo`, and the Cloud region. |
| `php` | `version` and `extensions`. |
| `build_command`, `deploy_command` | Strings. |
| `instance` | `size`, `scheduler`, `scale_to_zero`, `sleep_timeout` (minutes). |
| `queue` | The managed queue: `size`, `max_workers`, and `min_workers` (0 lets it scale to zero; Cloud sets 1 when it is left out). |
| `databases` | Named clusters (`name`, `type`, and optionally `size` as `cloud database:types` lists it, such as `mysql-flex-512mb`, `storage` in GB (5 or more) and `scale_to_zero_after` in seconds (0 for never, up to 3600)). A kind's `database` names one; kinds may share a cluster and each takes its own schema. |
| `variables` | Defaults: name to string. None may be an injected key. |
| `kinds` | Required. Keys are `preview`, `staging`, `production`; any other is refused. |
| `secrets` | A list of `key`, `class` (`test` or `live`) and `kinds` (allowed), and `id`, which `secrets` writes. |

A kind holds:

- `branch`: required. `{"rule": "slug"}` (the preview's slug, or `--branch`) or `{"rule": "fixed", "name": "staging"}`.
- `database`: a key of `databases`.
- `variables`: values that override the defaults for this kind.
- `required`: per variable, `{"equals": "log"}` or `{"not": "log"}`. The kind's resolved variables must satisfy it.
- `domains`: optional custom domains.
- `push_to_deploy`: optional, true or false; set by `plan`/`apply` and checked by `verify`.
