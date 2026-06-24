<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Creates copy-ready prompts from context and user action.
 */
final class PromptCompiler
{
    /**
     * Store the context pack service dependency.
     */
    public function __construct(private ContextPackService $contextPacks)
    {
    }

    /**
     * Compile a prompt for ChatGPT.
     */
    public function compile(
        int $campaignId,
        string $userAction,
        string $extraNotes = '',
        string $responseLanguage = 'en',
        string $narrativeLength = 'medium',
        string $challengeLevel = 'balanced',
        string $dicePolicy = 'occasional',
        string $agencyMode = 'player_led',
        string $descriptionStyle = 'direct',
        string $dmStance = 'guarded',
        string $diceResults = ''
    ): string {
        $context = $this->contextPacks->build($campaignId);
        $schema = $this->deltaSchema();
        $languageName = $this->languageName($responseLanguage);
        $lengthInstruction = $this->lengthInstruction($narrativeLength);
        $challengeInstruction = $this->challengeInstruction($challengeLevel);
        $diceInstruction = $this->diceInstruction($dicePolicy);
        $agencyInstruction = $this->agencyInstruction($agencyMode);
        $descriptionInstruction = $this->descriptionInstruction($descriptionStyle);
        $dmStanceInstruction = $this->dmStanceInstruction($dmStance);
        $diceSection = $this->diceSection($diceResults);

        return trim(<<<PROMPT
You are continuing a long-running tabletop RPG campaign. Use the context pack below as the canonical source of truth. Full transcripts may exist elsewhere, but this context pack is the compact continuity state for the current response.

[BEGIN_CONTEXT]
{$context}
[END_CONTEXT]

Current player action or request:
{$userAction}
{$diceSection}
Additional operator notes:
{$extraNotes}

Required response language:
- Write the entire [BEGIN_NARRATIVE] section in {$languageName}.
- Write human-readable JSON string values in {$languageName}.
- Keep proper nouns, exact user quotes, in-world identifiers, file paths, command outputs, technical labels, and established names unchanged.
- Do not switch to another language because older context, campaign notes, or imported transcripts use another language.
- Use another language only when the operator notes explicitly request it for this prompt.

Campaign isolation and data hygiene:
- Use only the context pack, the current player action, supplied dice results, and operator notes as campaign facts.
- Do not use names, NPCs, locations, factions, plot twists, ships, mysteries, or examples from any other campaign, previous conversation, memory, placeholder text, or unrelated test data.
- Never introduce a named companion or named NPC unless that name appears in the current campaign context or the current player action.
- If a role exists but no name is known, use a neutral role label such as the companion, the guard, the pilot, or the technician.
- Treat UI placeholder text and examples as non-canonical. They are not part of the game world.
- If the current campaign context is empty or incomplete, ask a neutral clarifying question instead of importing outside continuity.

Narrative length and pacing:
{$lengthInstruction}

Readable narrative formatting:
- Use clean Markdown inside [BEGIN_NARRATIVE].
- Use ## or ### headings when the response has distinct sections or diagnostics.
- Use **bold labels** for important status values, consequences, warnings, and item names.
- Use *italics* sparingly for emphasis, not for whole paragraphs.
- Use bullet lists for diagnostics, inventories, options, or discovered subsystems.
- Use fenced code blocks only for exact terminal output, file contents, command output, or machine logs.
- Do not use Markdown blockquote markers (>) for diagnostic lists or system readouts. They are reserved only for real quoted text.
- Do not put every sentence into its own paragraph. Group related sentences into compact readable paragraphs.

Challenge and consequence calibration:
{$challengeInstruction}

DM stance and fallibility:
{$dmStanceInstruction}

Player agency and action boundaries:
{$agencyInstruction}

Direct description style:
{$descriptionInstruction}

Forbidden phrasing patterns:
- Never use rhetorical negative contrast chains such as "not X, not Y, but Z", "not just X, but Y", "not only X, but Y", "it is not X, it is Y", "this is not X, it is Y", "less X and more Y", or similar constructions.
- Do not build suspense by listing rejected interpretations before the useful fact.
- Write the useful fact directly. Example: write "This is a low-level maintenance layer for diagnostics during primary system failure." Do not write "This is not a commander menu. It is a low-level maintenance layer."
- Negative statements are allowed only for direct mechanical facts, failed checks, unavailable actions, safety limits, or explicit diagnostic output. They must not be used as a dramatic contrast formula.

Checks and dice:
{$diceInstruction}

Response requirements:
1. Continue the scene naturally and preserve existing tone and continuity.
2. Resolve only the current player action or request. Stop at the next meaningful choice, risk, uncertainty, resource decision, menu option, or tactical branch.
3. Do not reveal future outcomes before the player commits to an action.
4. Preserve uncertainty. If something is inferred, mark it as inferred in the state delta.
5. Avoid making every reasonable player idea automatically succeed. Use uncertainty, partial success, costs, limited information, or mild complications when appropriate.
6. Do not automatically confirm player theories. Record uncertain assumptions as assumptions, labels, or notes unless the character has evidence.
7. Return exactly this format.
8. Always include [BEGIN_STATE_DELTA_JSON], even when the narrative only asks the player what they do next. Use empty arrays when nothing changed.

[BEGIN_NARRATIVE]
Your narrative response for the user goes here.
[END_NARRATIVE]

[BEGIN_STATE_DELTA_JSON]
{$schema}
[END_STATE_DELTA_JSON]

The JSON must be valid JSON. Do not include comments inside JSON. Do not include Markdown fences around JSON.
PROMPT);
    }

    /**
     * Convert a locale code into a model-facing language name.
     */
    private function languageName(string $locale): string
    {
        return $locale === 'cs' ? 'Czech' : 'English';
    }

    /**
     * Return model-facing instructions for the selected narrative length.
     */
    private function lengthInstruction(string $length): string
    {
        return match ($length) {
            'compact' => '- Keep the narrative compact: usually 1 to 3 short paragraphs. Skip decorative prose unless it changes play-relevant information.',
            'rich' => '- Use richer narration when useful, but keep it playable. Do not bloat the answer with repeated atmosphere or internal monologue.',
            default => '- Use medium-length narration: normally 2 to 5 concise paragraphs. Prefer clear playable information over long atmospheric padding.',
        };
    }

    /**
     * Return model-facing instructions for the selected challenge level.
     */
    private function challengeInstruction(string $level): string
    {
        return match ($level) {
            'gentle' => '- Use mostly forgiving outcomes. Add complications only when the action is risky, rushed, poorly specified, or opposed.',
            'demanding' => '- Use firmer resistance. Risky actions can fail, consume resources, reveal danger, or require a check. Never punish without giving readable signals or alternatives.',
            default => '- Use balanced resistance. Good plans should help, but not guarantee success. Early campaign consequences should usually be mild, partial, or reversible unless the player clearly takes a major risk.',
        };
    }


    /**
     * Return model-facing instructions for how much the DM should challenge player assumptions.
     */
    private function dmStanceInstruction(string $stance): string
    {
        $base = implode("\n", [
            '- Act as the world and its systems, not as a helpful assistant validating the player.',
            '- Do not praise, confirm, or optimize the player plan unless the character has actually earned that information through observation, tools, checks, or clear prior facts.',
            '- If the player makes a plausible but unverified assumption, let the character act on that assumption without correcting it out of character.',
            '- Preserve false leads, decoys, inefficient routes, incomplete notes, ambiguous labels, and misunderstood evidence when they are plausible in-world.',
            '- If the player says a paper probably contains passwords, you may write that the character copies the strings as possible passwords. Do not say they are passwords unless proven.',
            '- Do not hint that a player assumption is wrong merely because the DM knows hidden truth. Reveal correction only through in-world evidence, failed use, later discovery, or a check result.',
            '- Be fair. Do not lie about directly perceived facts, resolved mechanics, damage, inventory, dice results, or clearly readable text.',
            '- Use state delta certainty carefully. Player assumptions should usually be suspected or inferred, not confirmed.',
        ]);

        return match ($stance) {
            'straight' => $base . "\n" . '- Use a mostly transparent DM stance. Correct obvious misunderstandings when the character would notice them, but avoid meta hints.',
            'shady' => $base . "\n" . '- Use a guarded and occasionally misleading stance. Let plausible wrong theories persist. Let false trails consume time or attention when that follows naturally from the world. Keep the misleading part subtle and in-world, never as a cheap gotcha.',
            default => $base . "\n" . '- Use a guarded DM stance. Sometimes allow plausible wrong assumptions to stand. Sometimes answer only what the character can directly verify. This is the default mode.',
        };
    }

    /**
     * Return model-facing instructions for how strongly the DM should preserve player agency.
     */
    private function agencyInstruction(string $mode): string
    {
        $base = implode("\n", [
            '- Do not autoplay the player character. Do not choose menu options, routes, tools, dialogue lines, checks, or risk-taking actions for the player unless the current player action explicitly says to do that.',
            '- If the player asks to inspect, search, diagnose, open a menu, read a screen, or look around, provide the discovered information and stop before choosing what they operate next.',
            '- When a device, door, terminal, NPC, map, hazard, or mystery presents multiple plausible next steps, list concise options and ask what the player does.',
            '- Do not chain several consequential player actions together. One player input should normally produce one resolved action plus the next decision point.',
            '- You may narrate routine micro-actions needed to complete the stated action, such as sitting down before using a console. Do not narrate extra strategic decisions beyond the stated action.',
            '- Bad example: the player says they return to the cockpit, so the DM also selects biometric rescan and opens a deeper recovery prompt. Good example: the DM shows the recovery options and asks which one the player chooses.',
        ]);

        return match ($mode) {
            'guided' => $base . "\n" . '- Use a guided style. You may recommend one or two likely next steps, but still ask for confirmation before the character commits to them.',
            'sandbox' => $base . "\n" . '- Use a very open style. Avoid recommendations unless the player asks. Present the situation, consequences, and available handles, then stop.',
            default => $base . "\n" . '- Use a player-led style. Offer useful options when helpful, but keep the decision with the player. This is the default mode.',
        };
    }

    /**
     * Return model-facing instructions for concise direct description style.
     */
    private function descriptionInstruction(string $style): string
    {
        $base = implode("\n", [
            '- Use direct positive description as a hard style rule, not a preference.',
            '- State the useful fact first. Tell the player what the character sees, hears, learns, can do, cannot do, or must decide.',
            '- Never use the common contrast formula where the text says what something is not before saying what it is.',
            '- Forbidden patterns include: "not X, but Y", "not just X, but Y", "not only X, but Y", "not even X", "not a X, a Y", "less X and more Y", and stacked lines beginning with "Not".',
            '- Rewrite contrast formulas into direct statements. Example: "The interface is a low-level maintenance layer for diagnostics during primary system failure."',
            '- Negative facts are allowed only when they are direct mechanics: unavailable actions, failed checks, explicit errors, safety limits, missing inventory, or diagnostic output.',
            '- If a negative fact is needed, write it once and move on. Do not pair it with a dramatic "but" reversal.',
            '- Replace rhetorical setup with playable information, concrete options, clear status, or a required check.',
        ]);

        return match ($style) {
            'plain' => $base . "\n" . '- Use a plain utilitarian style. Avoid metaphor, rhetorical setup, and stylized contrast. Prioritize useful information.',
            'atmospheric' => $base . "\n" . '- Use atmosphere only through concrete sensory detail. Do not use negative contrast as atmosphere.',
            default => $base . "\n" . '- Use direct narrative style. Some mood is acceptable, but never through the forbidden contrast formulas.',
        };
    }

    /**
     * Return model-facing instructions for how often the DM should request checks.
     */
    private function diceInstruction(string $policy): string
    {
        $base = 'If a check is needed, ask the player to roll instead of rolling for them. Use a clear standardized request so the app can detect it: write a short line ending with "Check required", then a separate line starting with "Roll:" and the exact dice expression, for example "Roll: 1d20 + Technical Knowledge modifier". State all known modifiers and what success or failure might broadly mean without revealing hidden outcomes. If dice results are supplied below, use them and show raw roll, modifiers, and final total in the narrative. Do not resolve the check until dice results are supplied.';

        return match ($policy) {
            'rare' => '- Request dice rarely. Use checks only for meaningful risk, uncertainty, opposition, or time pressure. ' . $base,
            'frequent' => '- Request checks regularly for uncertain exploration, stealth, technical work, social pressure, and combat. Do not request checks for trivial actions. ' . $base,
            default => '- Request checks occasionally. Prefer them when success is uncertain, there is pressure, a hidden system resists, or failure would create an interesting but fair complication. ' . $base,
        };
    }

    /**
     * Create an optional prompt section with user-supplied dice rolls.
     */
    private function diceSection(string $diceResults): string
    {
        $diceResults = trim($diceResults);
        if ($diceResults === '') {
            return '';
        }

        return "\nDice results already rolled by the player for this turn:\n[BEGIN_DICE_RESULTS]\n" . $diceResults . "\n[END_DICE_RESULTS]\n";
    }

    /**
     * Return the expected state delta JSON skeleton.
     */
    private function deltaSchema(): string
    {
        return json_encode([
            'turn_title' => 'Short title for this response',
            'turn_summary' => 'One concise continuity summary of what changed.',
            'events' => [
                [
                    'type' => 'event_type',
                    'title' => 'Short event title',
                    'description' => 'What happened and why it matters.',
                    'visibility' => 'player',
                ],
            ],
            'entities' => [
                [
                    'name' => 'Entity name',
                    'type' => 'character|npc|location|item|vehicle|system|creature|organization|other',
                    'status' => 'active',
                    'visibility' => 'player',
                ],
            ],
            'facts' => [
                [
                    'category' => 'character|location|item|mystery|rule|event|system|other',
                    'subject' => 'Subject name or text',
                    'predicate' => 'precise_relation_or_property',
                    'value' => 'Precise value or statement',
                    'certainty' => 'confirmed|inferred|suspected|false|dm_only',
                    'visibility' => 'player|dm_only|prompt_allowed',
                ],
            ],
            'mysteries' => [
                [
                    'title' => 'Mystery title',
                    'status' => 'open|developing|resolved|closed',
                    'visibility' => 'player',
                    'clues' => [
                        [
                            'text' => 'New clue text',
                            'certainty' => 'confirmed|inferred|suspected',
                            'visibility' => 'player',
                        ],
                    ],
                ],
            ],
            'warnings' => [
                'Any continuity issue, contradiction, or uncertainty that needs human review.',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
