---
name: stakeholder-rewrite
description: Produce a plain-language version of a technical document for a non-technical audience — board members, trustees, funders, councillors, service managers, procurement, translators, or the public — using ASD-STE100 Simplified Technical English as the controlled-language basis. Use this skill whenever the user asks for a document to be rewritten, simplified, summarised, or reframed for stakeholders, senior management, a board or committee paper, a funding bid, a non-technical reader, or an audience who will not understand the original. Also use it when the user mentions Simplified Technical English, STE, ASD-STE100, controlled language, plain English, or says something like "make this readable for people who aren't developers". Trigger even when the user does not name STE explicitly — any request to translate technical material for a lay or executive audience belongs here.
---

# Stakeholder Rewrite (STE-based)

Turn a technical document into a version a non-technical stakeholder can read, act on, and quote accurately, using ASD-STE100 Simplified Technical English as the discipline behind the rewrite.

## The pair, and how to pick

This skill has an operational sibling, [`operator-rewrite`](../operator-rewrite/SKILL.md). **Pick by what the reader does after reading.** If they **decide, approve, fund, or oversee** — a board, trustees, funders, councillors, remote from the day-to-day — this is the right skill: it *strips* implementation detail down to risk, cost, timescale, and the decision requested. If they **operate, follow, or act repeatedly** — front-line staff, process owners, the desk that runs the thing — use `operator-rewrite` instead: it *protects* every rule, state, exception, and sequence the reader must not get wrong. If the audience you have been given actually runs the system, say so once and offer to switch. When a document genuinely serves both audiences, produce two artifacts, not one compromise.

## Why STE, and where it stops

STE was built so that an aircraft engineer whose first language is not English cannot misread a maintenance instruction. That makes it excellent at removing ambiguity, and it is the reason this skill uses it. But STE was designed for procedures and descriptions of equipment, not for persuasion, context, or governance. A board paper written in strict STE reads like a manual and will not carry a decision.

So the working rule is: **apply the STE rules that remove ambiguity; relax the ones that only serve maintenance-manual register.** The distinction is set out in `references/ste-application.md`. Read it before rewriting.

Do not present output as "STE compliant" unless the user has asked for genuine compliance and you have checked it against their official copy of the standard. Otherwise call it "plain-language, STE-informed" — that is honest and still meaningful.

## Step 1 — Locate the official standard

The official copy of ASD-STE100 is free but is not redistributable, so it is not bundled here. Check for it first:

```
references/ASD-STE100_ISSUE9.pdf
```

If it is present, read the relevant sections as you work — it is authoritative and this skill's summaries are not. If it is absent, read `references/official-copy.md` for how the user obtains one, tell the user once that you are working from the rule summaries rather than the standard itself, and continue. Do not block on it.

The dictionary of approved words is the part that most needs the real document. Never fabricate dictionary entries or claim a word is approved or unapproved from memory. If you are unsure whether a word is in the approved list, either check the official copy or flag it in the change log as "verify against dictionary".

## Step 2 — Establish who the reader is

The rewrite is worthless without this. If the user has not said, ask — one question, not a questionnaire:

- Who reads this, and what do they need to decide or do after reading it?

Common audiences and what changes:

| Audience | Needs | Cut |
| --- | --- | --- |
| Board / trustees | Risk, cost, timescale, decision required | Implementation detail |
| Funders | Outcomes, beneficiaries, evidence | Architecture, tooling |
| Service managers | What changes for their staff and when | Rationale, alternatives considered |
| Procurement / legal | Obligations, dependencies, exit | Design reasoning |
| Public / users | What they can do, what changed | Everything internal |
| Translators | Unambiguous source text | Nothing — this is the case for strictest STE |

## Step 3 — Rewrite

Work through `references/ste-rules.md`. The operative core, in order of how much they matter here:

1. **One idea per sentence.** Procedural sentences stay under 20 words; descriptive sentences under 25. Paragraphs stay under about six sentences.
2. **Active voice, named actor.** "The system was reviewed" hides who. Write "We reviewed the system" or name the team.
3. **One word, one meaning.** Pick a term for each concept and never vary it. Elegant variation is the enemy — if you call it "the platform" once and "the system" later, the reader assumes two things exist.
4. **No unexplained jargon or acronyms.** First use gets a plain gloss. If a term cannot be glossed in a clause, it belongs in the glossary, not the sentence.
5. **Break multi-word nouns.** Three words maximum (rule 2.1). "User authentication token expiry configuration" becomes "the setting that controls when a login token expires."
6. **Simple tenses.** Rule 3.2 permits simple present, simple past, and simple future — so "we will migrate in March" is fine. What is excluded is the perfect and progressive tenses and stacked auxiliaries. Avoid *-ing* forms where a plain verb works (rule 3.5).
7. **Verbs, not nominalisations.** Rule 3.7. "We will decide in March" beats "a decision will be taken in March" — the second hides who decides, which is exactly the ambiguity a board paper cannot afford.
8. **Say the consequence.** STE puts the warning before the action. In a stakeholder document, put the implication before the detail: what it means, then why.

**Spelling:** rule 1.14 mandates American English but yields to the applicable house style. For UK readers, write British English and treat the house style as the governing directive. Note it as a deliberate deviation, not a lapse.

Keep every number, date, cost, and commitment exactly as in the source. Simplifying language must never soften a figure or a deadline. If the source is vague about one, say so rather than inventing precision.

## Step 4 — Output format

Default to a markdown file. Use this structure unless the user asks otherwise:

```markdown
# [Plain title — what this is about, not the project codename]

## In one line
[The single sentence a reader could repeat to a colleague.]

## What this is
## Why it matters
## What we are asking for
[Decision, approval, or "for information" — be explicit.]
## What happens next
## Glossary
[Only terms that survived into the text.]
```

Sections that carry no content for this audience get dropped, not padded.

## Step 5 — Report what you changed

End your reply in the conversation (not in the document) with a short change log:

- **Terms fixed:** the vocabulary decisions you made and stuck to
- **Cut:** what you removed and why it was safe to remove
- **Verify:** anything you were unsure of — words to check against the dictionary, figures the source left ambiguous, claims you could not confirm
- **Judgement calls:** anywhere the plain version could be read as saying less than the original

This section matters more than it looks. The user is accountable for the simplified version, and needs to know where you exercised discretion on their behalf.

## What not to do

- Do not reproduce the ASD-STE100 dictionary or rule text verbatim into any output. It is ASD copyright. Paraphrase and cite the rule number.
- Do not flatten a hedge into a certainty. "We expect" must not become "we will."
- Do not remove a caveat because it is complicated. Rewrite it shorter.
- Do not produce a Word document unless the user explicitly says Word, docx, or .docx.
