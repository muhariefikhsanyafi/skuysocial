import { describe, expect, it, vi } from 'vitest';
import { feedTopicAutocomplete } from './feedTopicAutocomplete';

function keyEvent(key: string) {
    return {
        key,
        preventDefault: vi.fn(),
    } as unknown as KeyboardEvent;
}

describe('Feed topic combobox', () => {
    it('filters the server-provided topic list and exposes a listbox only when results exist', () => {
        const state = feedTopicAutocomplete(['Alpha', 'Animation', 'Beta']);
        state.topicQuery = 'a';
        state.open();

        expect(state.filteredTopics).toEqual(['Alpha', 'Animation', 'Beta']);
        expect(state.expanded).toBe(true);
        expect(state.activeDescendant).toBe('');

        state.topicQuery = 'not-a-real-topic';
        expect(state.expanded).toBe(false);
        expect(state.activeDescendant).toBe('');
    });

    it('moves the active option with arrow keys, clamps at both ends, and selects on Enter', () => {
        const state = feedTopicAutocomplete(['alpha', 'alpine', 'amber']);
        state.topicQuery = 'a';
        state.open();

        const down = keyEvent('ArrowDown');
        state.handleKeydown(down);
        expect(state.activeIndex).toBe(0);
        expect(down.preventDefault).toHaveBeenCalledOnce();

        state.handleKeydown(keyEvent('ArrowDown'));
        state.handleKeydown(keyEvent('ArrowDown'));
        state.handleKeydown(keyEvent('ArrowDown'));
        expect(state.activeIndex).toBe(2);

        const up = keyEvent('ArrowUp');
        state.handleKeydown(up);
        expect(state.activeIndex).toBe(1);
        expect(up.preventDefault).toHaveBeenCalledOnce();

        const enter = keyEvent('Enter');
        state.handleKeydown(enter);
        expect(enter.preventDefault).toHaveBeenCalledOnce();
        expect(state.topicQuery).toBe('alpine');
        expect(state.expanded).toBe(false);
        expect(state.activeIndex).toBe(-1);
    });

    it('opens at the relevant edge, supports click selection, and closes on Escape', () => {
        const state = feedTopicAutocomplete(['alpha', 'amber']);
        state.topicQuery = 'a';
        state.open();

        state.handleKeydown(keyEvent('ArrowUp'));
        expect(state.activeIndex).toBe(1);
        state.selectTopic('alpha');
        expect(state.topicQuery).toBe('alpha');
        expect(state.expanded).toBe(false);

        state.topicQuery = 'a';
        state.open();
        const escape = keyEvent('Escape');
        state.handleKeydown(escape);
        expect(escape.preventDefault).toHaveBeenCalledOnce();
        expect(state.expanded).toBe(false);
        expect(state.topicQuery).toBe('a');
    });

    it('leaves Enter available for normal form submission when no option is active', () => {
        const state = feedTopicAutocomplete(['alpha']);
        state.topicQuery = 'a';
        state.open();

        const enter = keyEvent('Enter');
        state.handleKeydown(enter);

        expect(enter.preventDefault).not.toHaveBeenCalled();
        expect(state.topicQuery).toBe('a');
    });
});
