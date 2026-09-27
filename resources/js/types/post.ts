import type { SharedProps } from './inertia';

export interface PostAuthor {
    id: number;
    name: string;
    username: string;
    avatar_url: string | null;
    is_verified: boolean;
}

export interface PostMedia {
    type: 'image';
    url: string;
    full_url: string;
    alt: string;
    full_alt: string;
}

export interface PostInteractions {
    liked: boolean;
    like_count: number;
    bookmarked: boolean;
    reposted: boolean;
    repost_count: number;
    comment_count: number;
}

export interface PostCapabilities {
    can_edit: boolean;
    can_delete: boolean;
    can_perform_social_writes: boolean;
}

export interface DisplayPost {
    uid: string;
    content: string;
    datetime: string;
    display_time: string;
    author: PostAuthor;
    topic: string | null;
    media: PostMedia[];
    interactions: PostInteractions;
    capabilities: PostCapabilities;
}

export interface PostRouteContext {
    uid: string;
    url: string;
    is_repost: boolean;
    reposted_by: PostAuthor | null;
}

export interface CommentActions {
    like_url: string;
    reply_url: string;
    share_url: string;
}

export interface PostDetailComment {
    id: number;
    post_uid?: string;
    parent_id?: number | null;
    content: string;
    datetime: string;
    display_time: string;
    author: PostAuthor;
    like_count: number;
    liked: boolean;
    replies_count: number;
    replies: PostDetailComment[];
    actions: CommentActions;
    reply_expansion_url?: string | null;
}

export interface ReactiveCommentCreationResponse {
    post_uid: string;
    comment_count: number;
    root_comment_count: number;
    parent_reply_count: number | null;
    comment: PostDetailComment & { post_uid: string; parent_id: number | null };
    feed_html?: string;
}

export interface CommentLikeResponse {
    comment_id: number;
    liked: boolean;
    like_count: number;
}

export interface CommentPagination {
    data: PostDetailComment[];
    current_page: number;
    per_page: number;
    has_more: boolean;
    next_url: string | null;
    previous_url: string | null;
}

export interface ExpandedReplies {
    data: PostDetailComment[];
    has_more: boolean;
    next_url: string | null;
    previous_url: string | null;
    per_page: number;
}

export interface PostDetailRoutes {
    feed: string;
    like: string;
    repost: string;
    bookmark_store: string;
    bookmark_destroy: string;
    comment: string;
    share: string;
    tags_base: string;
    edit: string | null;
    delete: string | null;
}

export interface PostDetailFeedbackTranslations {
    liked: string;
    unliked: string;
    session_expired: string;
    restricted: string;
    invalid: string;
    failed: string;
    network: string;
}

export interface CommentFeedbackTranslations {
    session_expired: string;
    restricted: string;
    invalid: string;
    failed: string;
    network: string;
}

export interface PostDetailTranslations {
    back_to_feed: string;
    thread: string;
    replies: string;
    like: string;
    unlike: string;
    reply: string;
    repost: string;
    save: string;
    remove_from_saved: string;
    share: string;
    send: string;
    view_more_replies: string;
    image_coming_soon: string;
    repost_coming_soon: string;
    open_options: string;
    verified: string;
    avatar_of: string;
    reposted_by: string;
    confirm_delete: string;
    edit: string;
    delete: string;
    comments_reply_to: string;
    thread_link_copied: string;
    comment_link_copied: string;
    reply_link_copied: string;
    photo: string;
    full_photo: string;
    close_preview: string;
    previous_image: string;
    next_image: string;
    pagination_previous: string;
    pagination_next: string;
    feedback: PostDetailFeedbackTranslations;
    comment_feedback: CommentFeedbackTranslations;
}

export interface PostDetailPageProps extends SharedProps {
    post: PostRouteContext;
    display_post: DisplayPost;
    comments: CommentPagination;
    expanded_reply_root_id: number | null;
    expanded_replies: ExpandedReplies | null;
    viewer: {
        id: number;
        author: PostAuthor;
        can_perform_social_writes: boolean;
    };
    routes: PostDetailRoutes;
    translations: PostDetailTranslations;
}
