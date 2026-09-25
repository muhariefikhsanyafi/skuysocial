type FontAwesomeWeight = 'regular' | 'solid' | 'brands';
type FontAwesomeName =
    | 'arrow-left-long'
    | 'bookmark'
    | 'camera'
    | 'chevron-left'
    | 'chevron-right'
    | 'comment'
    | 'ellipsis'
    | 'heart'
    | 'image'
    | 'pen-to-square'
    | 'retweet'
    | 'share-nodes'
    | 'trash-can'
    | 'xmark';

interface IconProps {
    className?: string;
    filled?: boolean;
    active?: boolean;
}

function FontAwesomeIcon({
    name,
    weight = 'regular',
    className = 'text-xl',
}: {
    name: FontAwesomeName;
    weight?: FontAwesomeWeight;
    className?: string;
}) {
    return <i aria-hidden="true" className={`fa fa-${weight} fa-${name} skuy-fa-icon ${className}`} />;
}

export function ArrowLeftIcon({ className = 'text-xl' }: IconProps) {
    return <FontAwesomeIcon name="arrow-left-long" className={className} />;
}

export function ChevronLeftIcon({ className = 'text-2xl' }: IconProps) {
    return <FontAwesomeIcon name="chevron-left" className={className} />;
}

export function ChevronRightIcon({ className = 'text-2xl' }: IconProps) {
    return <FontAwesomeIcon name="chevron-right" className={className} />;
}

export function DotsIcon({ className = 'text-xl' }: IconProps) {
    return <FontAwesomeIcon name="ellipsis" className={className} />;
}

// Keep the established verification mark geometry unchanged.
export function CheckIcon({ className = 'h-4 w-4' }: IconProps) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="2.2" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d="m5 12 4 4L19 6" />
        </svg>
    );
}

export function HeartIcon({ className = 'text-xl', filled = false }: IconProps) {
    return <FontAwesomeIcon name="heart" weight={filled ? 'solid' : 'regular'} className={className} />;
}

export function CommentIcon({ className = 'text-xl' }: IconProps) {
    return <FontAwesomeIcon name="comment" className={className} />;
}

export function RepostContextIcon({ className = 'text-base' }: IconProps) {
    return <FontAwesomeIcon name="retweet" className={className} />;
}

export function RepostIcon({ className = 'text-xl', active = false }: IconProps) {
    return <FontAwesomeIcon name="retweet" weight={active ? 'solid' : 'regular'} className={className} />;
}

export function BookmarkIcon({ className = 'text-xl', filled = false }: IconProps) {
    return <FontAwesomeIcon name="bookmark" weight={filled ? 'solid' : 'regular'} className={className} />;
}

export function ShareIcon({ className = 'text-xl' }: IconProps) {
    return <FontAwesomeIcon name="share-nodes" className={className} />;
}

export function ImageIcon({ className = 'text-xl' }: IconProps) {
    return <FontAwesomeIcon name="image" className={className} />;
}

export function CloseIcon({ className = 'text-[32px]' }: IconProps) {
    return <FontAwesomeIcon name="xmark" weight="solid" className={className} />;
}

export function PencilIcon({ className = 'text-base' }: IconProps) {
    return <FontAwesomeIcon name="pen-to-square" className={className} />;
}

export function TrashIcon({ className = 'text-base' }: IconProps) {
    return <FontAwesomeIcon name="trash-can" className={className} />;
}
