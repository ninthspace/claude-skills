# SVG UML kit

The drawing kit for `code-to-uml`. Everything here is hand-authored inline SVG — no
renderer, no CDN. Pair it with `assets/artifact-scaffold.html`, which already
defines the CSS tokens and SVG primitive classes referenced below.

---

## 1. Which diagram answers which question

| The prompt is really asking… | Diagram | What it shows |
| --- | --- | --- |
| "What are the steps and where does it branch?" | **Activity** | Control flow: actions, decisions, forks, per-system swimlanes, start/end. |
| "Who calls whom, in what order, sync or async?" | **Sequence** | Messages between participants over time; activations; `alt`/`opt`/`loop`. |
| "How do the systems relate?" | **System context** | A few boxes + the seam(s) between them. Plain HTML/CSS, not SVG. |
| "Where does the data live?" | **Data model** | A table of stores, key columns, and links. Plain HTML table. |

Default deliverable: one activity diagram + one or more sequence diagrams, with a
context panel when >1 system and a data-model panel when persistence is central.

---

## 2. UML element → SVG mapping

**Activity diagram**

| UML | SVG primitive (class in the scaffold) | Notes |
| --- | --- | --- |
| Initial node ● | `<circle class="term">` filled | one per flow start |
| Activity / action | `<rect class="node …" rx=9>` + `<text>` | colour by owning system |
| Decision / merge ◇ | `<polygon class="dec">` diamond | 2 short lines of label inside |
| Guard / branch label | `<text class="flbl yes/no">` | put *on* the outgoing edge |
| Fork / join | a thick `<line>` bar | only if there's real concurrency |
| Flow edge → | `<path class="flow" marker-end>` | orthogonal; end on the node border |
| Final node ◉ | `<circle class="term-ring">` + inner `<circle class="term">` | double ring |
| Swimlane / partition | `<rect class="lane-bg-*">` + `<line class="divider">` + title | one column per system/phase |

**Sequence diagram**

| UML | SVG primitive | Notes |
| --- | --- | --- |
| Participant | `<rect class="head-box node …">` head + `<line class="life">` dashed lifeline | colour head by system |
| Activation | `<rect class="actbar">` on the lifeline | spans the active window |
| Synchronous call → | `<path class="flow" marker-end=#solid>` | solid line, filled head |
| Return ⇠ | `<path class="flow ret" marker-end=#open>` | dashed line, open head |
| Async / dispatch ⇢ | `<path class="flow" marker-end=#open>` | solid line, open head |
| Self-call ↺ | small `<path>` looping off and back to the same lifeline | |
| Combined fragment | `<rect class="frag">` + tab `<path class="frag-tab">` + `<text class="frag-lbl">` | `alt` / `opt` / `loop` / `par` |
| `alt` divider | dashed `<line class="frag">` across the frame | separates the branches |
| Note | small `<rect class="frag-tab">` + `<text>` | inline annotation |

---

## 3. Coordinate discipline (how a hand-drawn diagram stays clean)

Hand-authoring means *you* own every coordinate. Impose a grid and the diagram
stays tidy; freehand it and arrows cross and boxes overlap.

- **Fixed viewBox, scale to fit.** `viewBox="0 0 W H"` and let CSS (`width:100%; max-width:Wpx; height:auto`) scale it. The scaffold's `.scroll` wrapper adds horizontal scroll when the page is narrower than the diagram.
- **Uniform node box:** width `250`, height `60`, `rx="9"`. Use height `72` only when a box genuinely needs three text lines.
- **Decision diamond:** half-width `70`, half-height `40`, as a `polygon` around its centre `(cx,cy)`:
  `points="cx,cy-40 cx+70,cy cx,cy+40 cx-70,cy"`.
- **Terminals:** start = filled circle `r=12`; end = ring (`r=12`) + inner filled (`r=6.5`).
- **Vertical pitch** between stacked node centres: `~92`. Enough for a 60-tall box plus an arrow and a label.
- **Draw order:** background rects → dividers/lane titles → **edges** → **nodes** → edge labels. Edges first so node fills tuck over the arrow tails; labels last so nothing paints over them.
- **End arrows on the node border, not its centre** — compute the border point so the arrowhead sits just outside the box (e.g. a top-entering arrow ends at `y = boxTop - 4`).
- **Multi-line text:** one `<text>` with the shared `x`/`text-anchor="middle"`, then per line either separate `<text>` elements at stepped `y`, or `<tspan x=cx dy="16">`. Keep to ≤3 lines per node.

---

## 4. Reusable primitives (copy, then place)

`<defs>` — arrowheads (already in the scaffold):

```svg
<defs>
  <marker id="ah" markerWidth="10" markerHeight="10" refX="8" refY="4" orient="auto">
    <path d="M0,0 L9,4 L0,8 z" fill="var(--line-strong)"/>
  </marker>
  <marker id="open" markerWidth="12" markerHeight="10" refX="10" refY="4" orient="auto">
    <path d="M0,0 L10,4 L0,8" fill="none" stroke="var(--line-strong)" stroke-width="1.4"/>
  </marker>
</defs>
```

Action node (owner colour via the second class — `collector` / `ingest` / `async` / `surface`, or semantic `warn` / `err` / `ok` / `muted`):

```svg
<g>
  <rect class="node ingest" x="395" y="506" width="250" height="60" rx="9"/>
  <text class="nt" x="520" y="531" text-anchor="middle">ClassName::method</text>
  <text class="nt sm mut" x="520" y="549" text-anchor="middle">one-line detail</text>
</g>
```

Decision diamond + branch labels:

```svg
<polygon class="dec" points="520,596 590,636 520,676 450,636"/>
<text class="nt sm" x="520" y="632" text-anchor="middle">guard</text>
<text class="nt sm" x="520" y="648" text-anchor="middle">passes?</text>
<text class="flbl yes" x="528" y="702">yes</text>
<text class="flbl no"  x="602" y="628">no</text>
```

Terminals:

```svg
<circle class="term" cx="170" cy="104" r="12"/>                              <!-- start -->
<circle class="term-ring" cx="880" cy="2236" r="12"/>
<circle class="term" cx="880" cy="2236" r="6.5"/>                            <!-- end -->
```

Swimlane (background tint + divider + title):

```svg
<rect class="lane-bg-p" x="340" y="0" width="360" height="2360"/>
<line class="divider" x1="340" y1="8" x2="340" y2="2352"/>
<text class="lane-title" x="360" y="30">Ingest · sync</text>
<text class="lane-sub"   x="360" y="48">performance-app · HTTP request</text>
```
Draw the network boundary between two systems as `class="divider net"` (dashed,
accent-coloured) so the one HTTP hop reads as a real boundary.

Flow edge (orthogonal path, ends on a border):

```svg
<path class="flow" d="M520,566 L520,596" marker-end="url(#ah)"/>
<path class="flow" d="M295,536 L393,536" marker-end="url(#ah)"/>   <!-- cross-lane handoff -->
```

Sequence participant + lifeline + activation:

```svg
<line class="life" x1="520" y1="96" x2="520" y2="1150"/>
<rect class="head-box node ingest" x="444" y="40" width="152" height="52" rx="8"/>
<text class="nt sm"     x="520" y="62" text-anchor="middle">Performance API</text>
<text class="nt sm mut" x="520" y="80" text-anchor="middle">ResponsesController</text>
<rect class="actbar" x="514" y="205" width="12" height="330"/>
```

Sequence messages (sync solid / return dashed / async open):

```svg
<path class="flow"     d="M300,205 L508,205" marker-end="url(#ah)"/>     <!-- sync call -->
<text class="flbl" x="306" y="197">POST responses[] · backfill:false</text>
<path class="flow ret" d="M514,548 L306,548" marker-end="url(#open)"/>   <!-- return -->
<path class="flow"     d="M526,478 L918,478" marker-end="url(#open)"/>   <!-- async dispatch -->
<path class="flow"     d="M526,238 C560,238 560,262 526,262" marker-end="url(#ah)"/> <!-- self-call -->
```

Combined fragment (`alt` with a divider):

```svg
<rect class="frag" x="330" y="452" width="700" height="66" rx="4"/>
<path class="frag-tab" d="M330,452 h58 v14 l-8,8 h-50 z"/>
<text class="frag-lbl" x="338" y="466">alt</text>
<text class="flbl"     x="392" y="466">backfill = false</text>
<line class="frag" x1="330" y1="490" x2="1030" y2="490"/>
<text class="flbl"     x="338" y="503">else · backfill = true</text>
```

---

## 5. Layout patterns

**Activity — swimlane "staircase".** Vertical lanes, one per system/phase, left→right
in flow order. Time runs down; each handoff is a horizontal arrow into the next
lane, dropping the flow down a "step". This naturally emphasises the repo boundary
(a vertical divider) and reads as a cascade. Expect lanes to have different heights
— the long-pole lane (usually async analysis) sets the diagram height; empty space
in a short lane reads correctly as "this system's work is done". Draw side-branches
(reject, degrade, skip, suppress) as small boxes offset toward the lane edge, with
their own short terminal, so the happy-path spine stays a clean vertical line.

**Sequence — participant grid.** Evenly spaced lifelines across the top (7 is about
the max before it gets cramped at 1400 wide). Messages step down at a steady pitch
(~32–46px). Group the write path, then a labelled dashed divider ("async · queue
worker"), then the async path — so the sync/async boundary is visible. Keep an
activation bar on a participant only while it is genuinely active.

**Labels.** On flow edges, sit the label just above/beside the line, not on it.
`yes` labels in the ok colour, `no`/reject labels in the error colour — the branch
outcome reads at a glance.

---

## 6. Pitfalls (the things that go wrong hand-drawing SVG)

- **Boxes overlapping side-branches.** A 250-wide node in a 320-wide lane leaves
  little room for a side box. Either widen the lane, narrow the side box, or route
  the branch to a box *below* on its own short rail (a vertical line down the lane's
  inner edge, clearing the main boxes by ≥20px).
- **Arrowheads buried inside boxes.** End the path at the border minus ~4px, not at
  the centre. A head drawn under a fill looks like a missing arrow.
- **Text overflowing the box.** Mono at 12–13px fits ~24 chars in a 250-wide box.
  Shorten the label or drop to `.sm` (11px). Never let text spill past the stroke.
- **Cross-lane arrows crossing nodes.** Route orthogonally around, not through:
  `M x1,y1 L x1,ymid L x2,ymid L x2,y2`. Pick a `ymid` gap that no node occupies.
- **Hard-coded colours.** Every `fill`/`stroke` must be `var(--token)` (via a class),
  or the dark theme breaks. The scaffold's classes already do this — use them.
- **Animation.** Don't. Static technical diagrams read as intentional; drifting or
  fading nodes read as AI-generated. (If you must, gate it behind
  `@media (prefers-reduced-motion: no-preference)` — but prefer none.)
- **One giant diagram.** If a single SVG passes ~2400px in either dimension and is
  dense, split it. Two clear diagrams beat one that needs a magnifying glass.

---

## 7. Palette tokens (defined in the scaffold)

System-ownership accents (assign one per repo/lane; rename to fit the subject):
`--collector` · `--ingest` · `--async` · `--surface`.
Semantic channel (kept separate from ownership): `--warn` / `--warn-bg`,
`--err` / `--err-bg`, `--ok` / `--ok-bg`.
Structure: `--ink`, `--muted`, `--line`, `--line-strong`, `--rule`, `--card`,
`--card-2`, `--sheet`, `--paper`. All are redefined under
`@media (prefers-color-scheme: dark)` **and** `:root[data-theme="dark"|"light"]` so
the viewer's OS preference and the in-page toggle both work. Style shapes only
through these tokens.
