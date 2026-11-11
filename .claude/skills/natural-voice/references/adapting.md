# Adapting a voice profile

This procedure turns `voice.md` into a profile for another writer. The new profile keeps the general clarity rules and swaps this author's preferences for the new writer's habits. It uses the same format, so the write and edit modes work unchanged.

## 1. Gather samples

Ask for three to five pieces the writer wrote themselves, in the kind of writing the profile is for. Texts to friends and a eulogy call for different profiles. Text the writer drafted with an AI tool will teach you the tool's habits, so ask for older pieces if they aren't sure.

## 2. Read the samples for habits

Note what the writer does, with a quoted line for each habit:

- Sentence and paragraph length, and how much they vary
- Punctuation: dashes, semicolons, exclamation points, ellipses, emoji
- Contractions and how formal the word choice runs
- How they open a message and how they sign off
- Humor, asides and words or phrases they come back to

Record only what shows up more than once. A single exclamation point in five samples tells you nothing.

## 3. Sort the rules

Keep these rules as they are. Nearly every reader takes their opposites as careless or machine-written:

- Leading with the point and keeping paragraphs short
- Plain words and specifics over vague claims
- Leaving out unrequested caveats
- Every entry under "Other signs of AI writing," except the Formatting entries

These rules are the default author's preferences. Check each one against the samples:

- The em and en dash ban
- The word lists (the shared list in `../de-ai-copy/references/ai-tells.md` §1). Do not copy or edit that file for one writer. Record the writer's exceptions in the profile instead, as a "Word list exceptions" line under the matching rule, for example "Uses 'really' as an intensifier; allowed."
- Using "I"
- The strict limits on rule of three, parataxis, parallel structure and contrasting pairs
- The Formatting entries: boldface, inline-header lists, title case, emoji and curly quotes

When the samples show the writer doing one of these on purpose, replace the rule with one that describes how they do it. A writer who uses a dash once a paragraph for an aside gets a rule that allows one aside dash per paragraph. With no evidence either way, keep the default rule.

## 4. Write the profile

Copy `references/voice.md` to `profiles/<name>.md` at the skill root (create the folder if it's missing) and change its title to "Voice profile: <name>." Under the title, add a "Voice notes" section of three to six habits from step 2, each with its quoted line.

Rewrite each replaced rule in the same format as the rest: a one-line definition and a Bad/Better pair. Model the Better example on a line from the samples. Then edit the matching checklist line so the checklist tests the new rule.

## 5. Test it

Take a short neutral draft, or one the writer wrote with an AI tool, and run the edit mode on it with the new profile. Show the writer the result next to one of their samples. Adjust the profile from what they say, and save it once they say it sounds like them.
