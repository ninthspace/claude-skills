---
name: operator-rewrite
description: Produce an operational version of a technical document for the people who will run or use the thing — front-line staff, process owners, the desk that does the work today, new starters, a team adopting a new system — using ASD-STE100 Simplified Technical English as the controlled-language basis. Use this skill whenever the user asks for a document to be rewritten, simplified, or reframed into end-user documentation, a user guide, how-to, quick reference, cheat sheet, operational runbook, SOP, standard operating procedure, work instructions, training or induction material, or a "what changes for you" adoption note for staff who will use a new system. Also trigger — with an offer to switch — when the user reaches for stakeholder-rewrite but the stated audience is operational (process owners, front-line staff, "the team who'll actually run this"). Routing rule: choose by what the reader DOES after reading. If they operate, follow, or act repeatedly, use this skill; if they decide, approve, fund, or oversee, use stakeholder-rewrite. When a document has both audiences, produce two artifacts, not one compromise. Do not use for board/funder/councillor papers (that is stakeholder-rewrite) or for developer-facing API reference.
---

# Operator Rewrite (STE-based)

Turn a technical document into one the people who **operate** the thing can follow to do their job — preserving and sharpening every operational detail while removing the architecture, rationale, and business case that do not help them act.

## The pair, and how to pick

This skill is the operational half of a pair with [`stakeholder-rewrite`](../stakeholder-rewrite/SKILL.md). They pull in opposite directions on purpose:

| | `stakeholder-rewrite` | `operator-rewrite` (this skill) |
| --- | --- | --- |
| Reader | Decides, approves, funds, oversees; remote from the day-to-day | Operates the thing, hands-on and repeatedly |
| Reader's expertise | Shallow in the domain; needs the implication | Expert in the process; shallow in the technology |
| Fails when | A decision is made on misunderstood risk/cost/scope | A task is done wrong: step missed, rule broken, edge case mishandled |
| Cut | Implementation detail, procedure, edge cases | Rationale, alternatives, architecture, business case |
| Protect above all | Risk, cost, timescale, the decision requested | Every rule, state, exception, sequence, deadline, code list |

**Pick by what the reader does after reading.** Decide/approve → `stakeholder-rewrite`. Operate/follow/act repeatedly → this skill. If the user aimed at `stakeholder-rewrite` but the audience actually runs the system, say so once and offer to switch. If a document genuinely serves both, produce two artifacts, not one compromise.

Where `stakeholder-rewrite` **strips** detail, this skill **protects** it. Simplify the *language*, never the *rule*.

## Why STE fits here even better

STE was built for maintenance procedures — action-oriented, one step at a time, read by staff whose first language may not be English. That is *closer* to operational user docs than to board papers. So where `stakeholder-rewrite` had to relax STE's procedural machinery, this skill gets to use it as intended. Read `references/ste-application.md` before rewriting — it is the inverse of the sibling's note: **apply the procedural rules in full.**

Do not present output as "STE compliant" unless the user asked for genuine compliance and you checked it against their official copy of the standard. Otherwise call it "plain-language, STE-informed."

## Step 1 — Locate the standard

Reuse the sibling's copy — do not duplicate it. Check for:

```
../stakeholder-rewrite/references/ASD-STE100_ISSUE9.pdf
```

If present, read the relevant sections as you work — it is authoritative and the rule map is not. If absent, read `../stakeholder-rewrite/references/official-copy.md` for how the user obtains one, say once that you are working from the rule map (`../stakeholder-rewrite/references/ste-rules.md`) rather than the standard, and continue. Do not block on it.

Never fabricate dictionary entries or claim a word is approved from memory. If unsure, check the official copy or flag it in the change log as "verify against dictionary." The dictionary matters *more* here than in the sibling — these docs are read repeatedly, by new starters, and by non-native-English readers.

## Step 2 — Establish the operator and the task

This is the whole game, and it is a different question from the sibling's. Ask — conversationally, not as a questionnaire, but surface the *task*, not just the job title:

- **Who does this work, how often, and what must they be able to do after reading?**
- **What is their current process?** — so the rewrite anchors to "what changes for you," not an abstract description.
- **Where do they go wrong today?** — the rules and edge cases that cause errors are the content that must survive most intact.

## Step 3 — Extract the operational spine BEFORE rewriting

The step the sibling does not have, and what prevents detail loss. Before writing a word of prose, enumerate from the source:

- every **procedure / workflow** the reader performs, in sequence;
- every **rule / validation / constraint** they must respect;
- every **state** a thing can be in (e.g. submitted / outstanding / nil / amended);
- every **exception, edge case, and "when X do Y"**;
- every **exact value**: deadline, threshold, code, code list, filename pattern, sequence order;
- every **hand-off** — what the system does versus what the person still does manually.

Hold this list. It is the coverage checklist for Step 5. Nothing on it may silently vanish. For safety- or legally-loaded docs, consider showing the reader this spine as an artifact, not just using it internally.

## Step 4 — Rewrite, task-first, with procedural discipline

- **Reorganise from system-shape to task-shape.** The source is usually structured by architecture or feature; the reader thinks in tasks, in the order they do them. Re-sequence accordingly — never reorganise into the system's structure.
- Apply STE per `references/ste-application.md`: imperative steps, one action per step, condition before action, warning before the step it governs, numbered sequences, one term per concept, short sentences.
- **Anchor to today.** For change comms, frame each item as "today you do X → now you do Y." Adoption depends on the reader locating themselves.
- **Preserve every item from the Step 3 spine.** A rule made shorter is good; a rule made vaguer is a defect.
- Keep a light "why this is better for you" thread for change comms — operators need buy-in — but never let it displace the procedure.

## Step 5 — Coverage gate (the signature discipline)

Where `stakeholder-rewrite` asks "did every *hedge* survive?", this skill asks **"did every *rule* survive?"** Walk the Step 3 spine and confirm each item is present in the output or is a conscious, stated out-of-scope. End your reply in the conversation (not in the document) with a change log:

- **Preserved:** the rules, states, and exceptions carried through.
- **Dropped as out of scope:** what was cut and why it is safe for *this* task/reader (e.g. build-vs-buy reasoning, architecture).
- **Verify:** anything the source left ambiguous that an operator would need pinned down (a threshold, a code, a sequence order), and any term to check against the STE dictionary.
- **Judgement calls:** anywhere the plain version risks reading as *less precise* than the source.

## Step 6 — Output format

Default to markdown. Offer the reader-appropriate skeleton rather than one fixed template — the four skeletons are in `references/skeletons.md`:

- **User guide** — task-ordered sections, each a short procedure.
- **Quick reference / cheat sheet** — states, rules, and "when X do Y" in tables; minimal prose.
- **"What changes for you" note** — today → now, per task; the adoption/change-comms shape.
- **Runbook / SOP** — numbered procedures, preconditions, warnings-before-step, what to do on failure.

Sections that carry no content for this reader get dropped, not padded. Keep every number, date, threshold, code, and sequence exactly as in the source.

## What not to do

- Do not simplify a **rule** into vagueness. "Must net to zero" must not become "should roughly balance."
- Do not drop an **edge case** because it is fiddly. Rewrite it shorter; keep it.
- Do not silently drop a **state, code, threshold, or sequence order**. If it cannot be placed, flag it in the coverage gate.
- Do not reorganise into the **system's** structure. Reorganise into the **reader's task** order.
- Do not reproduce the ASD-STE100 dictionary or rule text verbatim. It is ASD copyright. Paraphrase and cite the rule number.
- Do not label output STE-compliant without an audited check.
- Do not produce a Word document unless the user explicitly says Word, docx, or .docx.
