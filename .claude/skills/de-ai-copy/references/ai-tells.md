# AI Tell Taxonomy

Reference for the `de-ai-copy` skill. Each tell lists why it reads as machine-generated and how to rewrite it. The underlying principle behind almost every tell: **AI copy is vague, symmetric, and over-eager. Human copy is specific, varied, and earns its claims.**

Match rewrites to the project's established voice. The examples below are illustrative, not a house style to impose.

---

## 1. Lexical tells (vocabulary)

Words and phrases that cluster heavily in LLM output. Presence of one isn't damning; density is the tell.

| Flagged word/phrase | Why | Rewrite toward |
|---|---|---|
| leverage / leveraging | corporate filler for "use" | use, with |
| seamless / seamlessly | empty intensifier | say what actually connects, or cut |
| effortless / effortlessly | unearned claim | show the steps; quantify if true |
| elevate / unlock / empower | inflated benefit verbs | the concrete thing it does |
| robust / powerful | vague reassurance | the specific capability |
| delve / dive into / navigate | LLM stock verbs | look at, go through, use |
| tailored / bespoke / curated | overused for "customised" | name what's customised |
| designed to / built to | hedged capability | "it does X" |
| cutting-edge / state-of-the-art / next-generation | unearned superlative | drop, or cite the actual advantage |
| game-changer / game-changing / revolutionary | hype | the measurable difference |
| in today's fast-paced world / in the digital age | throat-clearing opener | delete; start with the point |
| whether you're X or Y | false-inclusive framing | address the actual reader |
| at the end of the day / when it comes to | filler connectives | cut |
| rich / comprehensive / holistic / wide range of | vague scale claims | the actual number or scope |

**Heuristic:** if removing the word changes nothing about meaning, it's filler.

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
