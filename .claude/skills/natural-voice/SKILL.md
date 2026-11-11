---
name: natural-voice
description: Writes and edits text the way a person talks, from a voice profile with defined rules and Bad/Better examples. Drafts in the active profile, edits an existing draft against the profile's pre-send checklist, and builds a new profile from samples of another writer's own text. Use when drafting or revising anything a person will read (texts, emails, letters, posts, cards, notes), when asked to make writing sound natural, human or less like AI, to "clean up" or "edit" a draft, to write in someone's voice, or to create a voice profile from writing samples. For copy that lives in a repository (views, language files, email templates, meta tags), use de-ai-copy instead.
---

# Natural voice

A voice profile is a markdown file of writing rules. Each rule has a one-line definition and a Bad/Better pair, and the file ends with a pre-send checklist. The default profile is `references/voice.md`. It favors short paragraphs that lead with the point, and it cuts the constructions that make text read as machine-written.

The skill has three modes. Pick one from the request.

## Choose the profile

Use the profile the user names. Profiles made by the adapt mode live in `profiles/<name>.md` inside this skill. With no name given, use `references/voice.md`.

Read the whole profile before the first draft of a session, together with the shared word list in section 1 of `../de-ai-copy/references/ai-tells.md` (path from this skill's folder). The profile's word-list rules point to that file instead of listing words. To add or remove a word for every profile, edit that file. Its rules apply to everything the reader sees, and only text labeled "Bad:" inside the profile breaks them.

## Write

Draft the text in the profile's voice. Before you send it, run the profile's pre-send checklist over the draft and fix what it finds. Return the finished text without mentioning the checklist or listing what you changed.

## Edit

The user hands you a draft to clean up. Keep its meaning and facts, and keep the writer's own wording wherever it already passes the profile. Run the pre-send checklist, rewrite what fails, and return only the revised text. Add a short list of changes when the user asks for one.

Never invent a detail to satisfy a rule. When a rule calls for a name or a number the draft doesn't have, cut the vague claim or ask the writer for the fact.

## Adapt

The user wants a profile in someone else's voice. Follow `references/adapting.md`. It covers which samples to gather, what to read for in them, which rules to keep and how to write the new profile and test it.

## Related skill

`de-ai-copy` audits customer-facing copy inside a repository and reports findings for approval before it edits. Use it, not this skill, when the text is in project files. Both skills read the same word list.

## Gotchas

- Quoted speech and other people's words stay as they were written. The rules govern the writer's own sentences.
- Each rule targets a pattern, so a listed word can still appear in its literal sense. "Drive to the coast" passes the corporate-verbs rule, and a bulleted list can hold three items.
- Run the checklist a second time over your own fixes, since a rewrite can bring in a new em dash or a tidy closing line.
