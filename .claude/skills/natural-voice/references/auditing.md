# Auditing copy in a repository

The audit mode finds the parts of a product's customer-facing writing and presentation that read as machine-written, and proposes specific replacements. It works on files in a repository: Laravel by default, any stack otherwise.

The aim is copy that reads as written by a person, without losing the product's own voice. A confident human writer uses a group of three now and then. Remove the pattern that gives the text away, and keep the personality.

Polish is not evidence of a human writer. Copy that is specific, well organised and pleasant to read can still be machine-written, because editing removes most surface signs and leaves the structural ones. Do not conclude "this reads human, nothing to do" before you have counted. Count the em dashes (`—` and `&mdash;`) across the whole in-scope surface first: a high count is a strong sign even when every sentence reads well.

## Posture: report first

The audit reports before it edits.

1. Produce a findings report: file and line, the snippet, the rule it breaks, a confidence level and a proposed rewrite.
2. Wait for approval, in batches the user can review, not one bulk apply.
3. Leave borderline cases alone unless asked. When you can't tell a sign of AI writing from a deliberate human choice, mark it **low** and move on. Never rewrite it without saying so.

Never rewrite outside the surface map in step 2 without an explicit request.

## Step 1: Find the project's own voice

Search for an existing style authority before applying the profile:

- A brand, voice or tone guide, style guide, content guidelines or glossary. Check `docs/`, `README`, `CONTRIBUTING`, design docs, and `.md` files that mention voice, tone, copy or brand.
- A body of copy in the repository that was clearly written by a person. Match its register and rhythm.
- Project `CLAUDE.md` instructions about copy or UI.

**Where the project's guide and the voice profile conflict, the project's guide wins.** This covers spelling (UK or US), quote style, title case on buttons and headings, and how far enthusiasm goes. Use the profile for everything the guide does not cover. Name the authority you used in the report.

## Step 2: Map the customer-facing surface

**In scope.** Laravel paths shown; see "Other stacks" for the rest.

- Blade views: `resources/views/**`
- Livewire and Volt component templates, and any inline strings rendered to users
- Filament resource labels, navigation labels, empty states, descriptions, help text and notifications
- Language files: `lang/**`, `resources/lang/**`
- Mail and notification templates (Blade and Markdown mailables)
- Seeders and factories, only where they produce content users see
- Config files that hold user-visible strings, such as the app name or tagline
- SEO and meta copy: `<title>`, `<meta name="description">`, OG tags
- Accessibility text: `alt`, `aria-label`, `aria-description`
- Graphics and presentation (step 4)

**Out of scope.** Flag these if you notice them, and never rewrite them in the default sweep:

- Code comments, PHPDoc, variable and method names
- Log messages and developer-facing exception text
- Internal docs, READMEs, ADRs and planning documents
- Test data and fixtures with no display path
- Commit messages

The user can ask for out-of-scope text to be cleaned up. A single file given as the argument is that request, even when it sits in a planning or documents folder. Outward correspondence (a letter, email or list of questions for a supplier or partner) is the common case: treat it as customer-facing, and apply the document-level and firm-register rules in `voice.md`.

## Step 3: Check the text

Read `voice.md` and `word-list.md` in full, then read each in-scope file as a whole before judging single sentences. The document-level rules in `voice.md` (repeated stock phrases, signposting, abstract nouns as metaphor, facts that do not add up) only show across a page.

Apply the em dash rule in `voice.md` (at most one per paragraph, prefer none) by default, even in otherwise clean copy, and report em dash changes as **high** confidence. Where a passage holds two or more, every one after the first goes.

Product copy has some patterns that personal writing rarely does. Check for these as well:

- **Vague reassurance.** "We take security seriously." "Built with the user in mind." Replace with one concrete fact: "Card data never touches our servers. Stripe handles it."
- **Boilerplate calls to action.** "Get started today!", "Take your X to the next level", "Join thousands of…". Say what happens on click: "Create your first booking", "See pricing".
- **Social proof with no names or numbers.** "Trusted by businesses worldwide." Use real names or figures, or remove the claim.
- **Unearned superlatives.** "Game-changing", "cutting-edge". See Promotional language and hype in `word-list.md`.
- **Symmetric lists and headings.** Every list item the same length, every heading in the same grammatical form. Vary them.
- **Mixed quote styles.** Normalise to the project's convention. With no convention, follow `voice.md`.

After every batch of rewrites, count the replacement phrases across the whole file again. A fix must not create a new repeat: changing four "we would rather" to "we would like" gives you four "we would like." Vary the construction instead.

Each proposed rewrite keeps the original meaning and every factual claim, adds the specific detail the vague version lacked, and matches the voice found in step 1. Never invent a claim to replace vague copy. If the real benefit or figure is not known, flag it and ask.

## Step 4: Check graphics and presentation

The audit flags and recommends for graphics. It cannot redraw artwork. For each finding, describe what a person-made replacement looks like, rather than only deleting.

| Pattern | Why it reads as generated | Recommend |
|---|---|---|
| Emoji as icons or bullets (✨🚀💡🔒) | common on AI-drafted landing pages | a branded SVG icon set, or remove |
| Default indigo-to-purple gradient on hero or call-to-action | the stock "AI startup" palette | brand colours; a flat or subtle treatment |
| Three equal feature cards with matching copy | template default | an asymmetric layout; vary card content and length |
| Generic AI illustration or stock hero image | nothing specific to the product or brand | real product screenshots, or branded illustration |
| Boilerplate alt text ("image of…", "a picture showing…") | auto-generated | specific alt text that says what the image is for |
| Everything centred, no visual hierarchy | default layout suggestion | a deliberate hierarchy on a real alignment grid |

## Step 5: Report, then apply on approval

Group the report by file:

```
### resources/views/welcome.blade.php
- L42 [word list · high] "seamlessly integrate" → "connects to"
- L48 [rule of three · medium] "fast, simple, and reliable" → keep the one that's true and show it
- L60 [graphics · high] ✨ emoji icon in <h2> → a branded SVG, or drop it

### lang/en/marketing.php
- L7 [call to action · high] "Take your workflow to the next level" → say what the button does
```

Confidence levels:

- **High**: a clear sign, safe to rewrite.
- **Medium**: likely, worth a look.
- **Low**: possible. Flag only; don't rewrite by default.

Apply approved changes in batches. Run the project's copy or parity tests if it has any. If PHP files changed, run `vendor/bin/pint --dirty --format agent`.

## Other stacks

Drop the Laravel paths and find the equivalent surfaces:

- Templates: `*.html`, JSX/TSX, Vue SFC, Svelte, ERB, Twig, Handlebars
- i18n: `*.json`, `*.yaml` and `*.po` locale files, `messages.*`, `i18n/**`
- Content: `*.md` and `*.mdx` in content or pages folders, CMS exports

The out-of-scope rules are the same on every stack.
