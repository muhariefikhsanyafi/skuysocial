export interface FeedTopicAutocompleteState {
    topicQuery: string;
    availableTopics: string[];
    isOpen: boolean;
    activeIndex: number;
    filteredTopics: string[];
    expanded: boolean;
    activeDescendant: string;
    open(): void;
    close(): void;
    handleInput(): void;
    handleKeydown(event: KeyboardEvent): void;
    selectTopic(topic: string): void;
}

export function feedTopicAutocomplete(topics: unknown = []): FeedTopicAutocompleteState {
    return {
        topicQuery: '',
        availableTopics: Array.isArray(topics)
            ? topics.filter((topic): topic is string => typeof topic === 'string')
            : [],
        isOpen: false,
        activeIndex: -1,

        get filteredTopics() {
            const query = this.topicQuery.toLowerCase();
            if (query === '') return [];

            return this.availableTopics.filter((topic) => topic.toLowerCase().includes(query));
        },

        get expanded() {
            return this.isOpen && this.filteredTopics.length > 0;
        },

        get activeDescendant() {
            return this.expanded && this.activeIndex >= 0
                ? `composer-topic-option-${this.activeIndex}`
                : '';
        },

        open() {
            this.isOpen = true;
        },

        close() {
            this.isOpen = false;
            this.activeIndex = -1;
        },

        handleInput() {
            this.isOpen = true;
            this.activeIndex = -1;
        },

        handleKeydown(event) {
            const topics = this.filteredTopics;

            if (event.key === 'ArrowDown' && topics.length > 0) {
                event.preventDefault();
                this.isOpen = true;
                this.activeIndex = Math.min(this.activeIndex + 1, topics.length - 1);
                return;
            }

            if (event.key === 'ArrowUp' && topics.length > 0) {
                event.preventDefault();
                this.isOpen = true;
                this.activeIndex = this.activeIndex < 0
                    ? topics.length - 1
                    : Math.max(this.activeIndex - 1, 0);
                return;
            }

            if (event.key === 'Enter' && this.expanded && this.activeIndex >= 0) {
                const selectedTopic = topics[this.activeIndex];
                if (typeof selectedTopic !== 'string') return;

                event.preventDefault();
                this.selectTopic(selectedTopic);
                return;
            }

            if (event.key === 'Escape' && this.isOpen) {
                event.preventDefault();
                this.close();
            }
        },

        selectTopic(topic) {
            if (!this.availableTopics.includes(topic)) return;

            this.topicQuery = topic;
            this.close();
        },
    };
}
