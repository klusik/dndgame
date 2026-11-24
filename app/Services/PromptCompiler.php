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
        string $diceResults = ''
    ): string {
        $context = $this->contextPacks->build($campaignId);
        $schema = $this->deltaSchema();
        $languageName = $this->languageName($responseLanguage);
        $lengthInstruction = $this->lengthInstruction($narrativeLength);
        $challengeInstruction = $this->challengeInstruction($challengeLevel);
        $diceInstruction = $this->diceInstruction($dicePolicy);
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

Challenge and consequence calibration:
{$challengeInstruction}

Checks and dice:
{$diceInstruction}

Response requirements:
1. Continue the scene naturally and preserve existing tone and continuity.
2. Do not reveal future outcomes before the player commits to an action.
3. Preserve uncertainty. If something is inferred, mark it as inferred in the state delta.
4. Avoid making every reasonable player idea automatically succeed. Use uncertainty, partial success, costs, limited information, or mild complications when appropriate.
5. Return exactly this format.
6. Always include [BEGIN_STATE_DELTA_JSON], even when the narrative only asks the player what they do next. Use empty arrays when nothing changed.

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
     * Return model-facing instructions for how often the DM should request checks.
     */
    private function diceInstruction(string $policy): string
    {
        $base = 'If a check is needed, ask the player to roll instead of rolling for them. State the exact dice expression, the relevant skill or attribute, all known modifiers, and what success or failure might broadly mean without revealing hidden outcomes. If dice results are supplied below, use them and show raw roll, modifiers, and final total in the narrative.';

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
            'session_title' => 'Short title for this response',
            'session_summary' => 'One concise continuity summary of what changed.',
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
