import '@inertiajs/core';

export type Locale = 'id' | 'en';

export type ModerationState = 'active' | 'restricted' | 'suspended' | 'banned';

export interface AuthenticatedUser {
    id: number;
    name: string;
    username: string | null;
}

export interface SharedFlash {
    status: string | null;
}

export interface SharedProps {
    [key: string]: unknown;
    auth: {
        user: AuthenticatedUser | null;
    };
    locale: Locale;
    flash: SharedFlash;
    moderation: {
        state: ModerationState | null;
        allows_social_writes: boolean;
    };
}

declare module '@inertiajs/core' {
    interface InertiaConfig {
        flashDataType: SharedFlash;
        sharedPageProps: SharedProps;
    }
}
