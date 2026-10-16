import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, expect, it } from 'vitest';
import { VerifiedBadge } from './VerifiedBadge';

afterEach(cleanup);

it('uses the same verification asset and accessible label as Blade in both themes', () => {
    const { container, rerender } = render(<VerifiedBadge label="Verified account" />);
    const badge = screen.getByRole('img', { name: 'Verified account' });
    expect(badge.getAttribute('data-verified-badge')).toBe('');
    expect(badge.classList.contains('skuy-verified-badge')).toBe(true);
    expect(badge.querySelector('img')?.getAttribute('src')).toBe('/images/verified-badge.svg');
    expect(badge.querySelector('img')?.getAttribute('alt')).toBe('');

    container.classList.add('dark');
    rerender(<VerifiedBadge label="Akun terverifikasi" />);
    expect(screen.getByRole('img', { name: 'Akun terverifikasi' }).querySelector('img')?.getAttribute('src')).toBe('/images/verified-badge.svg');
});
