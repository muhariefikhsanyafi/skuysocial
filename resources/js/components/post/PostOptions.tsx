import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { PostDetailTranslations } from '../../types/post';
import { DotsIcon, PencilIcon, TrashIcon } from './icons';

interface PostOptionsProps {
    uid: string;
    editUrl: string | null;
    deleteUrl: string | null;
    translations: PostDetailTranslations;
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function PostOptions({ uid, editUrl, deleteUrl, translations }: PostOptionsProps) {
    const [open, setOpen] = useState(false);
    const [pending, setPending] = useState(false);
    const wrapperRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const menuRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const closeOnOutsideClick = (event: MouseEvent) => {
            if (wrapperRef.current && !wrapperRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && open) {
                setOpen(false);
                triggerRef.current?.focus();
            }
        };

        document.addEventListener('mousedown', closeOnOutsideClick);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('mousedown', closeOnOutsideClick);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [open]);

    useEffect(() => {
        if (!open) return undefined;

        const frame = window.requestAnimationFrame(() => {
            menuRef.current?.querySelector<HTMLElement>('[role="menuitem"]')?.focus();
        });

        return () => window.cancelAnimationFrame(frame);
    }, [open]);
    if (!editUrl && !deleteUrl) return null;

    const deletePost = () => {
        if (!deleteUrl || pending || !window.confirm(translations.confirm_delete)) return;

        setPending(true);
        router.delete(deleteUrl, {
            preserveScroll: true,
            headers: { 'X-CSRF-TOKEN': csrfToken() },
            onFinish: () => setPending(false),
        });
    };

    return (
        <div ref={wrapperRef} className="skuy-post__options">
            <button
                ref={triggerRef}
                id={`post-options-trigger-${uid}`}
                type="button"
                className="skuy-action"
                aria-label={translations.open_options}
                aria-haspopup="menu"
                aria-expanded={open}
                aria-controls={`post-options-${uid}`}
                onClick={() => setOpen((current) => !current)}
            >
                <DotsIcon />
            </button>

            {open && (
                <div
                    ref={menuRef}
                    id={`post-options-${uid}`}
                    role="menu"
                    aria-labelledby={`post-options-trigger-${uid}`}
                    className="skuy-menu-surface absolute right-0 z-50 mt-2 w-44"
                >
                    {editUrl && (
                        <a href={editUrl} role="menuitem" className="skuy-menu-link flex items-center gap-2" onClick={() => setOpen(false)}>
                            <PencilIcon />
                            {translations.edit}
                        </a>
                    )}
                    {deleteUrl && (
                        <button
                            type="button"
                            role="menuitem"
                            disabled={pending}
                            className="skuy-menu-link flex w-full items-center gap-2 text-[var(--skuy-danger)]"
                            onClick={deletePost}
                        >
                            <TrashIcon />
                            {translations.delete}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
