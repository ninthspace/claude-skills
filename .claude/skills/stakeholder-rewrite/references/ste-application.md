# Applying STE outside its home domain

STE was written for maintenance documentation. Stakeholder documents are a different genre with a different failure mode: a manual fails when a step is misread, a board paper fails when a decision is made on a misunderstanding of risk, cost, or scope. Some STE rules transfer directly. Some produce text that is technically clear and rhetorically useless.

## Apply in full

These carry the ambiguity-removal value and cost nothing in register:

- **One term per concept.** The single highest-value rule for stakeholder writing. Non-technical readers cannot tell whether two names refer to one thing.
- **Sentence and paragraph limits.** Readers skim. Long sentences get skipped, not parsed.
- **Active voice with a named actor.** Passive voice in governance documents usually hides accountability, sometimes deliberately. Naming the actor is a substantive improvement, not just a stylistic one.
- **Noun cluster limit.** Three words.
- **Condition before action.** Reframed: implication before detail.
- **Acronym glossing on first use.**
- **Warnings before content.** Reframed: if a section carries a risk or a caveat, state it at the top of the section.
- **GR-7, inclusive language.** New in Issue 9 and it transfers to stakeholder writing unchanged. The standard points to the EIGE gender-sensitive communication toolkit and the UN disability-inclusive communications guidelines.
- **Verbs over nominalisations (3.7).** "A decision will be taken" hides the decision-maker. This is the same accountability problem as the passive, in a different disguise, and it is endemic in committee papers.

## Relax

- **The approved dictionary — but less than you would think.** Rule 1.5 category 21 (law and regulations) already approves *compliance*, *contract*, *scope*, *purpose*, *recommendation*, *jurisdiction*, *waiver*, *term*, *damages*, and rule 1.12 category 4 approves *comply with*, *conform to*, *enforce*, *notify*, *supersede*. Category 19 covers most of the technical vocabulary a digital project needs. So check the technical noun categories before concluding a governance word is unavailable — much of the gap people assume exists was closed in Issue 9. Where a genuine gap remains (*stakeholder*, *outcome*, *assurance*, *mitigation*), use the word and note it. The test: would a reader with school-level English and no domain knowledge get this right first time?
- **Verb form restrictions.** Less restrictive than commonly assumed — rule 3.2 permits simple future, so commitments and timelines write naturally. The perfect tense is genuinely excluded, and it sometimes carries information that matters ("we have already migrated" says something "we migrated" does not). Keep it where it does real work and note the deviation.
- **Strict imperative procedures.** Stakeholder documents describe and request; they rarely instruct.

## Drop

- **Procedural step formatting** unless the document genuinely contains a procedure.
- **Maintenance-specific subject field classification.**
- **The claim of compliance.** Unless the user wants audited STE, do not label the output STE compliant. Say STE-informed.

## When to go strict instead

Use full STE discipline, and check against the dictionary properly, when:

- The document will be **translated**, especially into several languages. This is STE's original purpose and it is very good at it. Translation cost drops and consistency rises.
- The document carries **safety or legal instructions** that a reader will act on.
- The organisation already uses **S1000D** or supplies into aerospace or defence, where STE may be contractually required.
- The audience includes many readers whose **first language is not English**.

In those cases, tell the user that strict compliance needs a checker tool — HyperSTE, Congree, and Acrolinx are the established ones — because manual dictionary conformance across a long document is not reliably achievable by reading alone.

## The failure mode to watch for

Simplification that quietly changes meaning. A hedge removed, a conditional made absolute, a range collapsed to its midpoint, a "may" turned into a "will". Each individually looks like better writing. Together they turn a careful document into an overcommitment that someone else has to honour. Every time you simplify a qualified statement, check that the qualification survived — and if it could not survive, say so in the change log rather than dropping it silently.
