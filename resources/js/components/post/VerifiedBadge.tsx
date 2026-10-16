/** Shared verified-account mark. Blade uses the same SVG asset and CSS class. */
export function VerifiedBadge({ label }: { label: string }) {
    return (
        <span className="skuy-verified-badge" data-verified-badge="" role="img" title={label} aria-label={label}>
            <img src="/images/verified-badge.svg" width="22" height="22" alt="" aria-hidden="true" />
        </span>
    );
}
