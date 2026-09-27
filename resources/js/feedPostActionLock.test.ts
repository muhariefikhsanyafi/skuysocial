import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { acquireFeedPostActionLock } from './feedPostActionLock';

function addFeedCard(postUid: string) {
    const article = document.createElement('article');
    article.setAttribute('data-feed-post-uid', postUid);

    for (const action of ['like', 'repost', 'bookmark']) {
        const button = document.createElement('button');
        button.dataset.feedPostAction = action;
        article.appendChild(button);
    }

    document.body.appendChild(article);
    return article;
}

describe('Feed post-action locks', () => {
    beforeEach(() => document.body.replaceChildren());
    afterEach(() => document.body.replaceChildren());

    it('serializes one action across duplicate Feed cards and restores buttons on release', () => {
        const first = addFeedCard('post-1');
        const duplicate = addFeedCard('post-1');
        const firstLike = first.querySelector('button[data-feed-post-action="like"]') as HTMLButtonElement;
        const duplicateLike = duplicate.querySelector('button[data-feed-post-action="like"]') as HTMLButtonElement;

        const release = acquireFeedPostActionLock('post-1', 'like');

        expect(release).toBeTypeOf('function');
        expect(firstLike.disabled).toBe(true);
        expect(duplicateLike.disabled).toBe(true);
        expect(firstLike.getAttribute('aria-busy')).toBe('true');
        expect(acquireFeedPostActionLock('post-1', 'like')).toBeNull();

        release?.();

        expect(firstLike.disabled).toBe(false);
        expect(duplicateLike.disabled).toBe(false);
        expect(firstLike.hasAttribute('data-feed-action-pending')).toBe(false);
        expect(firstLike.hasAttribute('aria-busy')).toBe(false);
    });

    it('leaves unrelated actions and different posts interactive', () => {
        const first = addFeedCard('post-1');
        const duplicate = addFeedCard('post-1');
        const other = addFeedCard('post-2');
        const firstLike = first.querySelector('button[data-feed-post-action="like"]') as HTMLButtonElement;
        const firstRepost = first.querySelector('button[data-feed-post-action="repost"]') as HTMLButtonElement;
        const otherLike = other.querySelector('button[data-feed-post-action="like"]') as HTMLButtonElement;

        const releaseLike = acquireFeedPostActionLock('post-1', 'like');
        const releaseRepost = acquireFeedPostActionLock('post-1', 'repost');
        const releaseOtherPost = acquireFeedPostActionLock('post-2', 'like');

        expect(firstLike.disabled).toBe(true);
        expect(duplicate.querySelector('button[data-feed-post-action="like"]')?.hasAttribute('data-feed-action-pending')).toBe(true);
        expect(firstRepost.disabled).toBe(true);
        expect(otherLike.disabled).toBe(true);
        expect(acquireFeedPostActionLock('post-2', 'repost')).toBeTypeOf('function');

        releaseLike?.();
        releaseRepost?.();
        releaseOtherPost?.();
    });
});
