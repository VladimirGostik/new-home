import {
    HomeIcon,
    ClipboardDocumentListIcon,
    UsersIcon,
    ShieldCheckIcon,
    UserCircleIcon,
    UserIcon,
    Cog6ToothIcon,
    PhotoIcon,
    EnvelopeIcon,
    HomeModernIcon,
    ListBulletIcon,
    Squares2X2Icon,
    BanknotesIcon,
} from '@heroicons/vue/24/outline';

const ICONS: Record<string, object> = {
    HomeIcon,
    UsersIcon,
    UserIcon,
    ShieldCheckIcon,
    ClipboardDocumentListIcon,
    PhotoIcon,
    EnvelopeIcon,
    UserCircleIcon,
    Cog6ToothIcon,
    HomeModernIcon,
    ListBulletIcon,
    Squares2X2Icon,
    BanknotesIcon,
};

export function resolveIcon(name: string): object {
    return ICONS[name] ?? HomeIcon;
}

export function translateLabel(t: (key: string) => string, label: string): string {
    return label.startsWith('app.') ? t(label.slice(4)) : t(label);
}
