(function () {
    'use strict';

    /**
     * Copy text from a target textarea or input into the clipboard.
     */
    async function copyTarget(button) {
        const targetId = button.getAttribute('data-copy-target');
        if (!targetId) {
            return;
        }
        const target = document.getElementById(targetId);
        if (!target) {
            return;
        }
        const text = target.value || target.textContent || '';
        try {
            await navigator.clipboard.writeText(text);
            const original = button.textContent;
            button.textContent = 'Copied';
            window.setTimeout(() => {
                button.textContent = original;
            }, 1400);
        } catch (error) {
            target.focus();
            if (typeof target.select === 'function') {
                target.select();
            }
            document.execCommand('copy');
        }
    }

    /**
     * Return a random integer between one and the requested number of sides.
     */
    function rollDie(sides) {
        return Math.floor(Math.random() * sides) + 1;
    }

    /**
     * Parse and roll a small dice expression.
     *
     * Supported examples: 1d20, 1d20+4, 2d20kh1+2, 2d20kl1+2, 1d8+2.
     */
    function rollExpression(expression) {
        const normalized = expression.replace(/\s+/g, '').toLowerCase();
        const diceMatch = normalized.match(/^(\d*)d(\d+)(kh1|kl1)?([+-]\d+)?$/);
        if (!diceMatch) {
            throw new Error('Unsupported dice expression. Use examples like 1d20+4, 2d20kh1+2, or 1d8+2.');
        }

        const count = diceMatch[1] === '' ? 1 : Number.parseInt(diceMatch[1], 10);
        const sides = Number.parseInt(diceMatch[2], 10);
        const keepMode = diceMatch[3] || '';
        const modifier = diceMatch[4] ? Number.parseInt(diceMatch[4], 10) : 0;

        if (!Number.isInteger(count) || !Number.isInteger(sides) || count < 1 || count > 50 || sides < 2 || sides > 1000) {
            throw new Error('Dice limits are 1 to 50 dice and 2 to 1000 sides.');
        }

        const rolls = [];
        for (let index = 0; index < count; index += 1) {
            rolls.push(rollDie(sides));
        }

        let usedRolls = rolls.slice();
        if (keepMode === 'kh1') {
            usedRolls = [Math.max.apply(null, rolls)];
        }
        if (keepMode === 'kl1') {
            usedRolls = [Math.min.apply(null, rolls)];
        }

        const diceTotal = usedRolls.reduce((sum, value) => sum + value, 0);
        const total = diceTotal + modifier;

        return {
            expression: normalized,
            rolls,
            usedRolls,
            modifier,
            total,
        };
    }

    /**
     * Format one roll result for insertion into the next prompt.
     */
    function formatRollResult(reason, result) {
        const label = reason.trim() || 'Requested check';
        const modifierText = result.modifier === 0 ? '' : (result.modifier > 0 ? ` + ${result.modifier}` : ` - ${Math.abs(result.modifier)}`);
        const usedText = result.usedRolls.length === result.rolls.length ? '' : `, used [${result.usedRolls.join(', ')}]`;
        return `${label}: ${result.expression} rolled [${result.rolls.join(', ')}]${usedText}${modifierText} = ${result.total}`;
    }

    /**
     * Roll dice from the nearest helper panel and append the result.
     */
    function handleDiceRoll(button) {
        const panel = button.closest('.dice-helper-panel');
        if (!panel) {
            return;
        }

        const expressionInput = panel.querySelector('[data-dice-expression]');
        const reasonInput = panel.querySelector('[data-dice-reason]');
        const resultsInput = panel.querySelector('[data-dice-results]');
        if (!(expressionInput instanceof HTMLInputElement) || !(reasonInput instanceof HTMLInputElement) || !(resultsInput instanceof HTMLTextAreaElement)) {
            return;
        }

        try {
            const result = rollExpression(expressionInput.value || '1d20');
            const line = formatRollResult(reasonInput.value || '', result);
            resultsInput.value = resultsInput.value.trim() === '' ? line : `${resultsInput.value.trim()}\n${line}`;
            resultsInput.dispatchEvent(new Event('input', { bubbles: true }));
        } catch (error) {
            window.alert(error instanceof Error ? error.message : 'The dice expression could not be rolled.');
        }
    }

    document.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const copyButton = target.closest('[data-copy-target]');
        if (copyButton instanceof HTMLButtonElement) {
            event.preventDefault();
            copyTarget(copyButton);
            return;
        }

        const diceButton = target.closest('[data-roll-dice]');
        if (diceButton instanceof HTMLButtonElement) {
            event.preventDefault();
            handleDiceRoll(diceButton);
        }
    });
}());
