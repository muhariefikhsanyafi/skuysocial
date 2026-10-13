import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, expect, it } from 'vitest';
import { Avatar } from './Avatar';

afterEach(cleanup);

it('uses the persisted avatar and falls back to the same initial when the image fails', () => {
    const author = { id: 7, name: 'Synthetic', username: 'synthetic', is_verified: false, avatar_url: '/storage/avatars/gravatar/retro.png' };
    const { rerender } = render(<Avatar author={author} labelTemplate="Avatar for :name" />);
    expect(screen.getByRole('img').getAttribute('src')).toBe(author.avatar_url);
    fireEvent.error(screen.getByRole('img'));
    expect(screen.getByRole('img').textContent).toBe('S');
    rerender(<Avatar author={{ ...author, avatar_url: '/storage/avatars/uploaded.jpg' }} labelTemplate="Avatar for :name" />);
    expect(screen.getByRole('img').getAttribute('src')).toBe('/storage/avatars/uploaded.jpg');
});
