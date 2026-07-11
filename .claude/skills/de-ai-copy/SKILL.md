---
name: de-ai-copy
description: Audit customer-facing text and graphics in a repository (Laravel by default, any stack otherwise) and propose conservative edits that remove the constructs, vocabulary, and presentational habits that read as obviously AI-generated. Use when asked to "de-AI this copy", "remove AI tells", "make this sound human", "this reads like ChatGPT wrote it", "audit the marketing copy", or "humanise the UI text".
license: MIT
metadata:
  author: ninthspace
  version: "1.0"
---

# De-AI Copy

Find and fix the parts of a product's **customer-facing** writing and presentation that betray an LLM author — the inflated vocabulary, the symmetric three-part lists, the em-dash habit, the emoji icons and default purple gradients — and propose human, specific, brand-true replacements.

The goal is **less detectable, not less distinctive.** Never flatten genuine voice into bland corporate filler. A confident human writer uses triads too; the job is to remove the *tell*, not to sand off personality.

**Don't be reassured by polish.** Copy that is specific, well-structured, on-brand, and pleasant to read can still be entirely AI-generated — surface quality is not evidence of human authorship, and good editing removes most soft tells while leaving the structural ones intact. Do not conclude "this reads human, nothing to do." Lead with the tells that survive polish — em-dash density above all — and quantify before you judge: count the em-dashes (`—` and `&mdash;`) across the in-scope surface *first*, because a high count is a strong AI signal even when every individual sentence looks well-written.

## Posture: conservative, flag-first

This skill **reports before it edits.** Default behaviour:

1. Produce a findings report (file:line, snippet, tell category, confidence, proposed rewrite).
2. Wait for the user to approve — in reviewable batches, not one bulk apply.
3. Leave borderline cases alone unless asked. When unsure whether something is a tell or a deliberate human choice, flag it as **low confidence** and move on; do not rewrite it silently.

Never rewrite outside the customer-facing surface map (below) without an explicit request.

## Step 1 — Look for the project's own voice first

Before applying any default, search for an existing style authority and defer to it over this skill's heuristics:

- A brand/voice/tone guide, style guide, content guidelines, or glossary (check `docs/`, `README`, `CONTRIBUTING`, design docs, `.md` files mentioning "voice", "tone", "copy", "brand").
- An existing body of clearly human-written copy in the repo — match its register and rhythm.
- Project `CLAUDE.md` instructions about copy or UI.

If a style guide exists, its rules win. Record which authority you used in the report.

## Step 2 — Map the customer-facing surface

**In scope** (Laravel defaults shown; degrade gracefully on other stacks — see "Non-Laravel" below):

- Blade views — `resources/views/**`
- Livewire / Volt component templates and any inline strings rendered to users
- Filament resource labels, navigation labels, empty states, descriptions, help text, notifications
- Language files — `lang/**`, `resources/lang/**`
- Mail + notification templates (Blade and Markdown mailables)
- Seeders / factories **only where they produce display content** users will actually see
- Config files holding user-visible strings (e.g. app name, taglines)
- SEO / meta copy — `<title>`, `<meta name="description">`, OG tags
- Accessibility text — `alt`, `aria-label`, `aria-description`
- Graphics and presentational assets (see Step 4)

**Out of scope — flag, never auto-rewrite:**

- Code comments, PHPDoc, variable/method names
- Log messages and developer-facing exception text
- Internal docs, READMEs, ADRs, planning artifacts
- Test data and fixtures with no display path
- Commit messages

If the user explicitly asks to clean up out-of-scope text, that's fine — but it's never part of the default sweep.

## Step 3 — Detect textual tells

Load `references/ai-tells.md` for the full taxonomy with examples and rewrites. The categories:

- **Lexical** — inflated/filler vocabulary (leverage, seamless, effortless, elevate, unlock, empower, robust, delve, tailored, "designed to", "in today's fast-paced world").
- **Structural** — rule-of-three triads, "it's not just X, it's Y", "not only… but also", relentless symmetric parallelism, hedged throat-clearing intros, vague reassurance with no concrete detail.
- **Punctuation & format** — **em-dash overuse (treat as a primary tell — see below)**, Title Case overuse on headings/buttons, curly/straight quote inconsistency, sentence rhythm that never varies.

### Em-dashes: reduce aggressively (default-on)

The em-dash (`—`) is one of the single strongest AI tells. LLMs reach for it constantly; hand-written marketing and UI copy uses it sparingly, preferring full stops, commas, colons, and parentheses. Unlike other tells, **this one is not flag-only** — propose a concrete replacement for em-dashes by default, even in otherwise clean copy.

Target: **at most one em-dash per paragraph, and prefer zero.** Where a passage stacks two or more, that is a near-certain tell and every one after the first should go.

For each em-dash, pick the replacement that fits the grammar — don't mechanically swap one character:
- **Two independent clauses** → full stop (split into two sentences). This is the most common fix and usually the best.
- **A list or appositive mid-sentence** → comma, or restructure into a real list.
- **An introduced explanation / definition** → colon.
- **A genuine aside** → parentheses, or commas.
- **A trailing afterthought** → full stop, or just delete the afterthought if it adds nothing.

Keep an em-dash only when removing it genuinely changes meaning or rhythm in a way no other punctuation can — and never more than one in a short passage. When in doubt, replace it. Report em-dash changes as **high confidence**.
- **Tone** — over-eager enthusiasm, unearned superlatives ("game-changing", "cutting-edge", "revolutionary"), "Let's dive in", boilerplate CTAs ("Get started today", "Take your X to the next level").

For each finding, propose a rewrite that:
- Keeps the original meaning and any factual claims.
- Adds specificity where the AI version was vague (the single biggest tell is vagueness).
- Matches the project voice from Step 1.

## Step 4 — Detect graphics & presentational tells

This skill **flags and recommends** for graphics — it cannot regenerate artwork. Look for:

- Emoji used as UI icons or bullet points (✨🚀💡 in headings, feature lists, buttons).
- Default Tailwind indigo→purple gradient hero/CTA treatments used as decoration.
- Three-equal-column feature card grids with symmetric copy in each.
- Generic AI-illustration / stock hero art with no real product or brand specificity.
- Boilerplate or missing alt text ("image of…", "a picture showing…").

For each, describe what a human-made replacement looks like (a real screenshot, a branded icon set, asymmetric layout, specific alt text) rather than just deleting.

## Step 5 — Report, then apply on approval

Output a findings report grouped by file. Suggested format:

```
### resources/views/welcome.blade.php
- L42 [lexical · high] "seamlessly integrate" → "connects to" — generic intensifier
- L48 [structural · medium] triad "fast, simple, and reliable" → pick the one that's true and prove it
- L60 [graphics · high] ✨ emoji icon in <h2> → use a branded SVG or drop it

### lang/en/marketing.php
- L7 [tone · high] "Take your workflow to the next level" → state the actual benefit
```

Confidence levels: **high** (clear tell, safe to rewrite), **medium** (likely, worth a look), **low** (possible, flag only — don't rewrite by default).

After the report, apply approved changes in batches. Re-run any copy/parity tests the project has. Run `vendor/bin/pint --dirty --format agent` if PHP files were edited.

## Non-Laravel repositories

Degrade gracefully. Drop the Laravel-specific paths and detect the equivalent surfaces:

- HTML/templating: any `*.html`, JSX/TSX, Vue SFC, Svelte, ERB, Twig, Handlebars.
- i18n: `*.json`/`*.yaml`/`*.po` locale files, `messages.*`, `i18n/**`.
- Content: `*.md`/`*.mdx` in content/pages directories, CMS exports.
- Same out-of-scope rules apply (comments, internal docs, dev strings).

The tell taxonomy and posture are stack-independent; only the file map changes.

## Guardrails

- Less detectable, not less distinctive — preserve real voice.
- Defer to a project style guide over these defaults.
- Conservative by default: report first, rewrite on approval, leave low-confidence cases alone.
- Never touch out-of-scope (developer-facing) text in the default sweep.
- Don't invent claims to replace vague copy — if the specific benefit isn't known, flag it and ask rather than fabricate.
