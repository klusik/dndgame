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
     * Return the browser storage key for a dice helper panel.
     */
    function diceStorageKey(panel) {
        const contextKey = panel.getAttribute('data-roll-context-key') || '';
        return contextKey === '' ? '' : `dnd-campaign-roll:${contextKey}`;
    }

    /**
     * Persist the locked roll for this turn in browser storage.
     */
    function rememberLockedRoll(panel, line) {
        const key = diceStorageKey(panel);
        if (key === '') {
            return;
        }

        try {
            window.localStorage.setItem(key, line);
        } catch (error) {
            return;
        }
    }

    /**
     * Return a previously locked roll for this turn from browser storage.
     */
    function rememberedLockedRoll(panel) {
        const key = diceStorageKey(panel);
        if (key === '') {
            return '';
        }

        try {
            return window.localStorage.getItem(key) || '';
        } catch (error) {
            return '';
        }
    }

    /**
     * Set the locked roll label, hidden input, and disabled field state.
     */
    function lockDiceResult(panel, line) {
        const expressionInput = panel.querySelector('[data-dice-expression]');
        const reasonInput = panel.querySelector('[data-dice-reason]');
        const resultInput = panel.querySelector('[data-dice-results]');
        const resultDisplay = panel.querySelector('[data-dice-result-display]');
        const rollButton = panel.querySelector('[data-roll-dice]');

        if (resultInput instanceof HTMLInputElement) {
            resultInput.value = line;
            resultInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (resultDisplay instanceof HTMLElement) {
            resultDisplay.textContent = line;
            resultDisplay.classList.remove('is-empty');
        }
        if (rollButton instanceof HTMLButtonElement) {
            rollButton.disabled = true;
            rollButton.textContent = 'Roll locked';
        }
        if (expressionInput instanceof HTMLInputElement) {
            expressionInput.readOnly = true;
        }
        if (reasonInput instanceof HTMLInputElement) {
            reasonInput.readOnly = true;
        }

        panel.classList.add('dice-locked');
    }

    /**
     * Roll dice from the nearest helper panel and lock the result.
     */
    function handleDiceRoll(button) {
        const panel = button.closest('[data-dice-helper-panel]');
        if (!panel) {
            return;
        }

        const expressionInput = panel.querySelector('[data-dice-expression]');
        const reasonInput = panel.querySelector('[data-dice-reason]');
        const resultInput = panel.querySelector('[data-dice-results]');
        if (!(expressionInput instanceof HTMLInputElement) || !(reasonInput instanceof HTMLInputElement) || !(resultInput instanceof HTMLInputElement)) {
            return;
        }

        if (resultInput.value.trim() !== '') {
            window.alert('A roll is already recorded for this turn. Use the recorded result.');
            return;
        }

        try {
            const result = rollExpression(expressionInput.value || '1d20');
            const line = formatRollResult(reasonInput.value || '', result);
            lockDiceResult(panel, line);
            rememberLockedRoll(panel, line);
        } catch (error) {
            window.alert(error instanceof Error ? error.message : 'The dice expression could not be rolled.');
        }
    }

    /**
     * Restore locked rolls from browser storage after a reload.
     */
    function initializeDiceHelpers() {
        document.querySelectorAll('[data-dice-helper-panel]').forEach((panel) => {
            const resultInput = panel.querySelector('[data-dice-results]');
            if (!(resultInput instanceof HTMLInputElement)) {
                return;
            }

            const existing = resultInput.value.trim() || rememberedLockedRoll(panel).trim();
            if (existing !== '') {
                lockDiceResult(panel, existing);
            }
        });
    }

    /**
     * Prevent prompt generation when a roll is required but not recorded yet.
     */
    function validateNextActionForm(form, event) {
        if (form.getAttribute('data-roll-required') !== '1') {
            return;
        }

        const resultInput = form.querySelector('[data-dice-results]');
        if (resultInput instanceof HTMLInputElement && resultInput.value.trim() !== '') {
            return;
        }

        event.preventDefault();
        const panel = form.querySelector('[data-dice-helper-panel]');
        if (panel instanceof HTMLDetailsElement) {
            panel.open = true;
            panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        window.alert('The previous DM response requires a dice roll. Roll once before generating the next prompt.');
    }



    /**
     * Initialize sticky campaign side panel tabs and collapse state.
     */
    function initializeGameSidePanel() {
        const panel = document.querySelector('[data-side-panel]');
        if (!(panel instanceof HTMLElement)) {
            return;
        }

        document.body.classList.add('has-game-side-panel');

        let storedCollapsed = false;
        try {
            storedCollapsed = window.localStorage.getItem('dnd-campaign-side-panel-collapsed') === '1';
        } catch (error) {
            storedCollapsed = false;
        }
        if (storedCollapsed) {
            panel.classList.add('collapsed');
            document.body.classList.add('has-collapsed-game-side-panel');
        }

        panel.querySelectorAll('[data-side-tab]').forEach((tab) => {
            tab.addEventListener('click', () => {
                const selected = tab.getAttribute('data-side-tab') || '';
                panel.querySelectorAll('[data-side-tab]').forEach((item) => item.classList.toggle('active', item === tab));
                panel.querySelectorAll('[data-side-tab-panel]').forEach((item) => {
                    item.classList.toggle('active', item.getAttribute('data-side-tab-panel') === selected);
                });
            });
        });

        const toggle = panel.querySelector('[data-side-panel-collapse]');
        if (toggle instanceof HTMLButtonElement) {
            toggle.addEventListener('click', () => {
                const collapsed = !panel.classList.contains('collapsed');
                panel.classList.toggle('collapsed', collapsed);
                document.body.classList.toggle('has-collapsed-game-side-panel', collapsed);
                try {
                    window.localStorage.setItem('dnd-campaign-side-panel-collapsed', collapsed ? '1' : '0');
                } catch (error) {
                    return;
                }
            });
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

    document.addEventListener('submit', function (event) {
        const target = event.target;
        if (target instanceof HTMLFormElement && target.classList.contains('next-action-form')) {
            validateNextActionForm(target, event);
        }
    });

    initializeDiceHelpers();
    initializeGameSidePanel();
}());
