---
name: cloud-environments
description: Creates, checks and removes Laravel Cloud environments (on-demand previews, staging, production) from a manifest kept in the repository. Shows each change as a diagram for approval before anything is created. Use when asked to set up, plan, apply, verify or tear down a preview, staging or production environment on Laravel Cloud, or to check that one still matches its manifest. For deploying one application by hand use deploying-to-cloud; for resizing or moving one that already runs use evolving-a-cloud-app; for costing an application not yet on Cloud use assess-for-laravel-cloud.
---

# cloud-environments


Builds Laravel Cloud environments from a manifest, `cloud-environments.json`, at the root of the repository. It works for any repository and organisation: nothing in this skill names one.

Cloud also has its own preview environments: a dashboard automation on an environment creates a preview for each pull request against its branch and deletes it on merge or close (https://laravel.com/cloud/docs/preview-environments). Use those for pull-request previews when a dashboard-configured, unreviewed setup is acceptable; Starter allows one automation per organisation. Use this skill's previews when a preview is wanted on demand, from a reviewed plan and diagram, or without a pull request.

## Commands

Run them from the repository root as `php {this skill's directory}/scripts/cloud-env.php {command}`. Every command that reads the manifest takes `--manifest={path}`, defaulting to `cloud-environments.json` in the current directory. They call the binary named by `CLOUD_BIN` (default `cloud`).

| Command | What it does | Gate |
| --- | --- | --- |
| `secrets [--from-stdin]` | Creates each secret the manifest lists that has no ID: prompts for its value with echo off, creates it named by its key and tagged `cloud-environments:{application}:{key}:{class}` in its notes, and writes the ID into the manifest. A secret already carrying its tag is adopted, not created again; more than one with a tag is reported as a duplicate. The value reaches the CLI on stdin only (the CLI trims it). Refuses without a terminal unless `--from-stdin` | the user runs it in their own terminal window (not through the session's `!` prefix, which gives it no terminal to read from), never you |
| `secrets status` | Lists each manifest secret as present, missing, duplicated or unknown, without values; exits 1 unless all are present | none |
| `validate [manifest]` | Checks the manifest and names each bad field | none |
| `plan {kind} [--slug={slug}] [--branch={branch}] [--manifest={path}]` | Reads Cloud and writes `.cloud/plans/{environment}.json`: ordered steps (`create`, `change`, `keep`, `delete`), the state they were made from, and a hash. Makes no write call. A preview is `preview-{slug}` on `--branch` (default the slug); staging and production take their branch from the manifest | `.cloud/plans/` must be git-ignored |
| `render {plan-file}... [--out=file.html]` | Draws the plans as one self-contained HTML page (by default `{environment}.html` beside a single plan, where `apply` looks for it), each environment laid out as the Cloud dashboard shows it (Network and region groups of cards, each marked create, change or keep) with its plan code, then its steps, branch, variable names (never values), its organisation-level secrets with their allowed kinds, and an estimated monthly cost from `references/rates.json` (dated; estimated lines marked). An attachment outside a secret's allowed kinds, or of the wrong class, is listed at the top as a warning. Reads only the plan files. Publish the page as a private artifact and get the plan code approved before `apply` | none |
| `apply {plan-file} --hash={hash} [--code={approved code}]` | Runs an approved plan. Refuses, before any call, when no diagram rendered from this plan sits beside it, when the hash differs from the plan's content or its stored hash, when the plan holds a `delete` step, when a step runs a command a plan never emits, when a step attaches a secret the manifest does not allow for the kind, or when a variable's manifest value no longer matches its digest. Then reads Cloud and refuses, before any write, when Cloud's projected state has changed since the plan, or when the plan's steps are not the ones `plan` derives now from the manifest and Cloud ("the plan's steps are not the ones plan derives"). Waits for each created cluster, schema and environment; stops at the first failed step | the user approves the plan code in the conversation after seeing the diagram |
| `verify {environment} [--branch=] [--extensions=no]` | Compares the built environment with the manifest: branch, build and deploy commands (each kind's own, else the top-level ones), PHP version and extensions (`php -m` through `command:run`, after a deploy), declared variables (by digest), required values, secret attachments, instance size, scheduler, scale to zero and its timeout, managed queue, the schema's cluster and the cluster's declared size, storage and scale to zero. Undeclared variables are ignored | none |
| `remove environment {name} --confirm={name}` | Deletes one environment of the manifest's application, and its schema when no other environment uses it and it lies in a cluster the manifest names. Clusters are never deleted, and the application's default environment is refused (Cloud does not allow deleting it) | the user confirms the named resource |
| `remove domain {environment} {domain} --confirm={domain}` | Deletes one custom domain of an owned environment. `plan` manages domains only for a kind that declares `domains`, and lists an undeclared one as a `delete` step that `apply` refuses; this is how it goes | the user confirms the named resource |
| `preview down {slug} --confirm=preview-{slug}` | The same, refusing any name that is not `preview-*`; a missing preview is reported and nothing happens | the user confirms the named resource |
| `php scripts/check-help.php` (a separate script) | Compares every command and option the skill emits (`scripts/lib/Catalog.php`) with `cloud {command} -h` | none |

The manifest schema, with a generic example, is `references/manifest.md`. The variables Cloud injects or manages, which a manifest may not declare, are in `references/injected-keys.json` and nowhere else.

## Workflow

Requires Cloud CLI 0.7.2 or later (`cloud --version`); 0.6 cannot create a MySQL cluster without a terminal.

Before the first plan for a new application, check with the user that:

- Laravel Cloud's GitHub app can see the repository: in GitHub, the organisation's Settings → GitHub Apps → Laravel Cloud → Configure → Repository access lists it. Without it `application:create` fails with "The repository … could not be found", and `apply` names this step.
- The branch the environment tracks is pushed to the repository.
- Database sizes are Cloud's names, as `cloud database:types` lists them (`mysql-flex-512mb` … `mysql-pro-32gb`).

1. `validate`, then have the user run `secrets` in their terminal and check `secrets status`, then `plan {kind}`.
2. `render` the plan. It writes `.cloud/plans/{environment}.html`, drawn as the Cloud dashboard draws an environment, with the plan code (the first 8 characters of the plan's hash).
3. Publish `.cloud/plans/{environment}.html` as a private claude.ai artifact, keeping one link per environment: republish to the same link whenever the plan changes, and never edit the page by hand.
4. Ask the user, naming the link and the code: "Create {environment} as shown in the diagram at {link} (plan {code})?" (for a plan that changes an environment, "Change …"). Do not show the full hash; checking it is the skill's job. Without a published diagram there is no approval, and no answer is not approval.
5. `apply {plan} --hash={hash} --code={code}` with the full hash read from the plan file and the code the user approved; it refuses if the hash does not start with that code, or if the plan has changed since.
6. Ask the user for the one setting the CLI cannot make, in the dashboard: the PHP version (General Settings). Push-to-deploy is set from the manifest's per-kind `push_to_deploy`. PHP extensions need no step: every extension Cloud supports, `intl` and `gd` among them, is available on every PHP environment (Cloud's environment docs, "PHP extensions"), and `verify` confirms the manifest's `php.extensions` with `php -m`. The database cluster's size, storage and scale to zero are set by `apply` when the manifest declares them.
7. Deploy, then `verify {environment}`. On an environment other than `production`, fetch a public page and confirm it shows the deployment stamp with the commit just deployed; if the application has no stamp yet, build it first as `references/deployment-stamp.md` describes, and confirm `production` shows none.
8. Removing anything goes through `remove` or `preview down`, each with the user's confirmation naming the resource.

## What plan reads

- The organisation comes from `application:list` (each application carries it). It must match the manifest, and an organisation with no applications is refused because it cannot be confirmed. This is the first call; nothing else is called after a mismatch.
- Only the manifest's application, its environments, the clusters and secret IDs it names are planned. Other applications' resources are never matched.
- A plan holds ID references (`{step:N.path}`, resolved when applying) and variable references (`{var:KEY}`, read from the manifest when applying). It holds a variable's SHA-256 digest, never its value.
- An instance's scale-to-zero timeout is in minutes. CLI 0.7.2 reads it back, so `plan` and `verify` compare it, and turning scale to zero on needs it, so a manifest with `scale_to_zero: true` must give `sleep_timeout`.
- Cloud creates a default environment with the application, named `production`, and does not let you delete it. The production plan adopts it; `remove` refuses it.
- A managed queue is recognised among an environment's instances by a queue status, a visibility timeout or a queue type.

## Gates

- Nothing is created or changed until a plan's diagram has been shown and the user has approved that plan's code. No answer is not approval.
- Deleting needs a separate confirmation that names the resource.
- Every call passes explicit IDs and acts only on resources the manifest owns.

## Secrets

- The user creates every secret by running `secrets` in their own terminal, so no value enters the conversation; it writes each secret's ID into the manifest. Never ask the user to paste a secret value, or to create secrets with `cloud secret:create` and paste the IDs.
- A secret has a class (`test` or `live`) and the environment kinds it may attach to. The validator refuses a live secret allowed on a preview or staging, a test secret allowed on production, two secrets with one key on the same kind, a second entry with the same key and class (list all its kinds on one entry), and a secret key also declared as a variable.
- Secret names are not unique in Cloud, so a test and a live secret can share a key. Attachments are always by ID.
- Only `secrets` creates a secret; `plan` and `apply` never do. No command reads a secret's value back.

## Tests

`scripts/run-tests.sh [test-name]` runs the suite against a stub binary and refuses to start if `CLOUD_BIN` is anything else. `AGNOSTIC_TERMS={terms-file}` adds the scan, and `scripts/agnostic-scan.sh {terms-file}` runs it directly.
