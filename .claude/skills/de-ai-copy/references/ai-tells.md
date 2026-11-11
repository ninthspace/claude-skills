# AI Tell Taxonomy

Reference for the `de-ai-copy` skill. Each tell lists why it reads as machine-generated and how to rewrite it. The underlying principle behind almost every tell: **AI copy is vague, symmetric, and over-eager. Human copy is specific, varied, and earns its claims.**

Match rewrites to the project's established voice. The examples below are illustrative, not a house style to impose.

---

## 1. Lexical tells (shared word list)

This section is the one word list for both `de-ai-copy` and `natural-voice`. `natural-voice` reads it by path from its profile rules, so add or remove words here, not in either skill's own files. The subsection names match the rule names in `natural-voice/references/voice.md`.

Presence of one word isn't damning; density is the tell. Each entry targets a pattern, so a listed word used in its literal sense passes: "drive to the coast", "the lake reflects the mountains", "a key for the shed".

**Heuristic:** if removing the word changes nothing about meaning, it's filler.

### 1.1 Corporate-register verbs

| Flagged word/phrase | Rewrite toward |
|---|---|
| leverage / leveraging, utilize / utilise | use, with |
| facilitate, enable | the action itself: "lets you", "helps", or name who does what |
| underscore, reflect (figurative), drive (figurative) | say the plain relation: shows, comes from, causes |
| elevate / unlock / empower | the concrete thing it does |
| designed to / built to | "it does X" |

### 1.2 AI vocabulary

Words that show up far more in model output than in human writing and tend to cluster.

| Flagged word/phrase | Rewrite toward |
|---|---|
| additionally | also, or start a new sentence |
| align with | matches, fits, agrees with |
| crucial, key (adjective), valuable | say why it matters, or cut |
| delve / dive into / navigate | look at, go through, use |
| emphasizing / emphasising, highlight (verb), showcase | shows, or state the fact itself |
| enduring, intricate / intricacies, interplay | the specific quality or relation |
| enhance, fostering, garner | improves, builds, gets |
| landscape (abstract), tapestry | the actual field, set or mix of things |
| testament, pivotal | see 1.4 |
| shape (of a request, response, problem) | format, structure, fields, variation |

### 1.3 Promotional language and hype

| Flagged word/phrase | Rewrite toward |
|---|---|
| seamless / seamlessly | say what actually connects, or cut |
| effortless / effortlessly | show the steps; quantify if true |
| robust / powerful | the specific capability |
| tailored / bespoke / curated | name what's customised |
| cutting-edge / state-of-the-art / next-generation | drop, or cite the actual advantage |
| game-changer / game-changing / revolutionary | the measurable difference |
| boasts, stunning, nestled, breathtaking, must-visit, vibrant | plain description with one specific detail |
| rich / comprehensive / holistic / wide range of | the actual number or scope |

### 1.4 Inflated significance

| Flagged word/phrase | Rewrite toward |
|---|---|
| pivotal moment, testament to, a new chapter, setting the stage, marks a turning point | what happened, with a date or a reason |

### 1.5 Superficial -ing analyses

| Flagged word/phrase | Rewrite toward |
|---|---|
| ensuring, highlighting, reflecting, symbolizing / symbolising, underscoring (tacked on as a participle phrase) | cut the phrase, or make it its own sentence with a real claim |

### 1.6 Copula avoidance

| Flagged word/phrase | Rewrite toward |
|---|---|
| serves as, stands as, features, boasts (in place of is/has) | is, has |

### 1.7 Hedging qualifiers

| Flagged word/phrase | Rewrite toward |
|---|---|
| potentially, arguably, somewhat, it seems, may possibly | cut; if the doubt is real, say it once and say where it comes from |
| worth confirming / worth being clear / worth asking about | state the point or the question |

### 1.8 Filler intensifiers and filler phrases

| Flagged word/phrase | Rewrite toward |
|---|---|
| genuinely, really, truly, actually, very, incredibly | cut |
| in order to | to |
| due to the fact that | because |
| at this point in time | now |
| has the ability to | can |
| it is important to note that | delete |
| at the end of the day / when it comes to | cut |

### 1.9 Stock openers and crutches

| Flagged word/phrase | Rewrite toward |
|---|---|
| in today's fast-paced world / in the digital age | delete; start with the point |
| whether you're X or Y | address the actual reader |
| Let's be honest, Make no mistake, Here's the thing, The truth is | delete; make the point |

---

## 2. Structural tells (sentence & paragraph shape)

### Rule-of-three triads
LLMs default to three parallel items everywhere: "fast, simple, and reliable", "plan, build, and ship", "secure, scalable, and seamless".
- **Fix:** keep the one claim that's true and provable; cut the padding. Vary list length (2, 4, 5) so triads don't repeat down the page.

### "It's not just X, it's Y" / "More than just X"
The single most recognisable LLM construction.
- *"It's not just a booking system — it's a complete experience."*
- **Fix:** state what it is, directly. *"It books activities and takes payment in one screen."*

### "Not only… but also"
Symmetric escalation.
- **Fix:** two plain sentences, or one with a specific second clause.

### Relentless parallelism
Every sentence the same length and shape; every heading the same grammatical form.
- **Fix:** vary rhythm deliberately. Follow a long sentence with a short one. Break heading symmetry.

### Hedged throat-clearing intros
*"In this section, we'll explore…", "It's worth noting that…", "When it comes to X, there are several things to consider."*
- **Fix:** delete the preamble; lead with the content.

### Vague reassurance without specifics
*"We take security seriously." "Built with the user in mind." "Designed for performance."*
- **Fix:** replace with one concrete fact. *"Card data never touches our servers — Stripe handles it."*

### Repeated stock phrases across a document
The same opener or hedge recurring paragraph after paragraph: "we would rather", "we would like", "To be clear", "One more on the same subject". Each sentence reads fine; the repetition is the tell.
- **Fix:** count them across the file. Keep one, rewrite the rest with different constructions. Re-count after rewriting so the replacement does not become the new repeat.

### Signposting transitions
*"There is a second half to this, which is …", "One more on the same mechanism.", "Worth being clear about what we are building, because …"*
- **Fix:** start with the point. *"The second question is how far the first payment date can be moved."*

### Summary sentence that restates the obvious
LLMs close paragraphs by re-summarising what they just said.
- **Fix:** cut the closing restatement; trust the reader.

---

## 3. Punctuation & formatting tells

| Tell | Why | Fix |
|---|---|---|
| **Em-dash use** (not just overuse) | LLMs reach for `—` constantly; hand-written copy rarely does. One of the strongest surface tells. | **Reduce aggressively, default-on (high confidence).** Target ≤1 per paragraph, prefer 0. Replace each by grammar: two clauses → full stop; appositive/list → comma or real list; explanation → colon; aside → parentheses; afterthought → full stop or delete. Keep one only when nothing else preserves the meaning. See the dedicated section in SKILL.md. |
| Title Case On Every Heading And Button | machine consistency | use sentence case unless the brand uses title case |
| Curly vs straight quote inconsistency | mixed `'` and `'` | normalise to one (usually curly for prose, straight in code) |
| Bold scattered on key phrases mid-sentence | LLM emphasis habit | bold sparingly; let words carry weight |
| Every list item the same length | symmetry | vary item length naturally |

---

## 4. Tone tells

### Over-eager enthusiasm
Exclamation marks, "Amazing!", "We're thrilled to…", relentless positivity.
- **Fix:** match the product's actual register. Most B2B/utility products should sound calm and confident, not excited.

### Boilerplate CTAs
*"Get started today!", "Take your X to the next level", "Unlock the power of Y", "Join thousands of…"*
- **Fix:** say what happens on click. *"Create your first booking", "See pricing"*.

### "Let's dive in" / "Let's explore" / "Buckle up"
Conversational filler that no product UI needs.
- **Fix:** delete.

### Firm or defensive register in correspondence
In letters and question lists to suppliers or partners, LLM drafts tend to pre-empt objections and sound curt: "we need confirmed", "we do not want to find a missing step", "is not a problem for us. We are not asking for one.", "only one of them is what you intend", emphasis in bold on **not**.
- **Fix:** plain requests and neutral consequences. *"it would help to know now rather than in November"*, *"so we do not need a payment webhook"*, *"please tell us which one you intend"*. Keep the question itself clear.

### Manufactured social proof phrasing
*"Trusted by businesses worldwide", "Loved by teams everywhere"* with no names or numbers.
- **Fix:** real logos/names/figures, or remove the claim.

---

## 5. Graphics & presentational tells

Flag and recommend — this skill can't regenerate art.

| Tell | Why it reads as AI/templated | Recommend |
|---|---|---|
| Emoji as icons/bullets (✨🚀💡🔒) | hallmark of AI-drafted landing pages | branded SVG/icon set, or remove |
| Default indigo→purple gradient hero/CTA | the stock "AI startup" palette | brand colours; flat or subtle treatment |
| Three equal feature cards with symmetric copy | template default | asymmetric layout; vary card content/length |
| Generic AI illustration / stock hero | no product or brand specificity | real product screenshots, branded illustration |
| Boilerplate alt text ("image of…", "a picture showing…") | auto-generated descriptions | specific, functional alt text describing purpose |
| Centred everything, no visual hierarchy | LLM-suggested layout default | deliberate hierarchy, real alignment grid |

---

## What is NOT a tell (don't over-correct)

- One triad, used deliberately (but em-dashes are the exception — reduce those even when singular and "working"; see the em-dash rule above).
- Strong specific verbs that happen to be vivid.
- Genuine enthusiasm in a brand that is genuinely enthusiastic.
- Technical precision that reads "clean" because the writer is competent.
- Sentence-case headings (often a deliberate, good choice).

When a passage is specific, varied, and earns its claims, leave it alone — even if it's polished. Polish isn't the tell. Vagueness, symmetry, and hype are.
