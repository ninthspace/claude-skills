---
name: natural-voice
description: Writes, edits and audits text so it reads as written by a person, from a voice profile of rules with Bad/Better examples and a pre-send checklist. Four modes - write new text, edit a draft, audit customer-facing copy and graphics in a repository (Laravel by default, any stack) and report findings before editing, and build a new profile from another writer's samples. Use when drafting or revising anything a person will read (texts, emails, letters, posts, notes, UI copy), when asked to make writing sound natural, human or less like AI, to "de-AI this copy", "remove AI tells", "clean up" a draft, "audit the marketing copy", "humanise the UI text", to write in someone's voice, or to create a voice profile from writing samples.
---

# Natural voice

A voice profile is a markdown file of writing rules. Each rule has a one-line definition and a Bad/Better pair, and the file ends with a pre-send checklist. The default profile is `references/voice.md`. It favors short paragraphs that lead with the point, and it cuts the constructions that make text read as machine-written.

The skill has four modes. Pick one from the request:

- **Write**: the user wants new text.
- **Edit**: the user gives you a draft in the conversation, or names a single file to clean up.
- **Audit**: the text is copy in a repository (views, language files, email templates, meta tags), or the user asks to audit or sweep a project's copy.
- **Adapt**: the user wants a profile in someone else's voice.

## Choose the profile

Use the profile the user names. Profiles made by the adapt mode live in `profiles/<name>.md` inside this skill. With no name given, use `references/voice.md`.

Read the whole profile before the first draft of a session, together with `references/word-list.md`. The profile's word-list rules name a section of that file instead of listing words, and every profile uses the same file. Only text labeled "Bad:" inside the profile breaks its rules.

## Write

Draft the text in the profile's voice. Before you send it, run the profile's pre-send checklist over the draft and fix what it finds. Return the finished text without mentioning the checklist or listing what you changed.

## Edit

Keep the draft's meaning and facts, and keep the writer's own wording wherever it already passes the profile. Run the pre-send checklist, rewrite what fails, and return only the revised text. Add a short list of changes when the user asks for one.

Never invent a detail to satisfy a rule. When a rule calls for a name or a number the draft doesn't have, cut the vague claim or ask the writer for the fact.

## Audit

Follow `references/auditing.md`. It maps the customer-facing surface of a repository, checks text against the profile and graphics against a list of generated-looking patterns, and reports findings for approval before it edits anything. A style guide in the project overrides the profile where they conflict.

## Adapt

Follow `references/adapting.md`. It covers which samples to gather, what to read for in them, which rules to keep and how to write the new profile and test it.

## Gotchas

- Quoted speech and other people's words stay as they were written. The rules govern the writer's own sentences.
- Each rule targets a pattern, so a listed word can still appear in its literal sense. "Drive to the coast" passes the corporate-verbs rule, and a bulleted list can hold three items.
- Run the checklist a second time over your own fixes, since a rewrite can bring in a new em dash, a repeated phrase or a tidy closing line.
