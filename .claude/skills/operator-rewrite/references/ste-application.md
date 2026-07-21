# Applying STE to operational documents (the inverted note)

The sibling skill's `ste-application.md` says *relax the procedural rules* — a board paper describes and requests, it does not instruct. This skill says the opposite for the procedural machinery. STE was written for maintenance documentation: action-oriented, one step at a time, read by staff whose first language may not be English. An operational user document is the same genre. This is STE's home turf, so use it as intended.

The failure mode is also inverted. A board paper fails when a decision is made on a misunderstanding. An operational document fails when a **task is done wrong** — a step missed, a rule broken, an edge case mishandled. Every rule below is chosen to prevent that.

## Apply in full — even more than the sibling

- **Imperative procedures, one action per step (STE §5).** The home domain. Number the steps; do not fold two actions into one sentence.
- **Condition before action (§5).** "If the return is nil, enter 0 and submit" — the condition comes first so the reader knows whether the step applies before they read the action.
- **Warning / caution before the step it governs (§5, §7).** Never after. A reader who has already acted cannot use a warning that follows the action.
- **One term per concept (1.11).** Highest value here too. An operator following a procedure cannot tell whether two names mean one thing or two — and will assume two. Pick one term per state, screen, field, and action, and never vary it.
- **Sentence and paragraph limits; multi-word-noun limit (2.1, three words).** Break "monthly absence return upload file" into plain relative phrasing.
- **Simple tenses (3.2), verbs over nominalisations (3.7), active voice with a named actor (3.6).** "You upload the file", not "the file is uploaded" — the reader must know who does what, and which parts are theirs versus the system's.

## The dictionary matters more here

These documents are read repeatedly, by new starters, and by staff whose first language may not be English — the exact conditions STE was built for. Lean toward checking words against the approved dictionary rather than waving them through. Where a genuine gap remains, use the word and flag it in the change log to "verify against dictionary" — do not invent an approval.

## Relax

- **Pure maintenance-manual register** where a warmer operational tone drives adoption. Change comms are not a torque-spec; a flat imperative wall will not get a team to switch. Keep the steps disciplined, let the framing breathe.
- **Strict subject-field vocabulary** where the reader's own house terms are clearer. Use the term the team already says (the sibling's rule 1.8 point, applied to the operational jargon the reader owns) — but then hold that term consistently, per 1.11.

## Drop

- **The claim of compliance.** Say "plain-language, STE-informed" unless genuine audited compliance is requested — same as the sibling.

## When to go strict / recommend a checker tool

The same triggers as the sibling — translation, safety or legal instructions the reader acts on, many non-native-English readers — **but these are more likely to apply to operational docs than to board papers.** So treat the strict path as the default to consider, not the exception. When strict compliance is needed, tell the user it requires a checker tool (HyperSTE, Congree, Acrolinx are the established ones) — manual dictionary conformance across a long document is not reliably achievable by reading alone.

## The failure mode to watch for

Simplification that quietly loosens a rule. A threshold softened, a "must" turned into a "should", a required sequence left unordered, an exception dropped because it was fiddly. Each individually looks like cleaner writing. Together they turn a precise procedure into something an operator can follow and still get wrong. Every time you shorten a rule, check that the rule still binds as tightly — and if it cannot survive in the shorter form, keep the longer form or flag it in the coverage gate rather than dropping it silently.
