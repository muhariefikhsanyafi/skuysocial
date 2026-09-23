import type { PostAuthor } from '../../types/post';

interface AvatarProps {
    author: PostAuthor;
    size?: 'default' | 'small' | 'tiny';
    labelTemplate: string;
}

export function Avatar({ author, size = 'default', labelTemplate }: AvatarProps) {
    const sizeClass = size === 'small' ? 'skuy-avatar--small' : size === 'tiny' ? 'skuy-avatar--tiny' : '';
    const className = ['skuy-avatar', sizeClass, 'object-cover'].filter(Boolean).join(' ');
    const label = labelTemplate.replace(':name', author.name);

    return author.avatar_url ? (
        <img src={author.avatar_url} alt={label} className={className} />
    ) : (
        <div className={className} role="img" aria-label={label}>
            {author.name.slice(0, 1)}
        </div>
    );
}
