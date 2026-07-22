import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

const NAV = [
    {
        route: 'dashboard',
        match: 'dashboard',
        label: 'Dashboard',
        icon: 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
    },
    {
        route: 'leads.index',
        match: 'leads.*',
        label: 'Leads',
        icon: 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941',
    },
    {
        route: 'tasks.index',
        match: 'tasks.*',
        label: 'Tareas',
        icon: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    {
        route: 'contacts.index',
        match: 'contacts.*',
        label: 'Contactos',
        icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    },
    {
        route: 'companies.index',
        match: 'companies.*',
        label: 'Empresas',
        icon: 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21',
    },
    {
        route: 'reports.index',
        match: 'reports.*',
        label: 'Reportes',
        icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
    },
    {
        route: 'webforms.index',
        match: 'webforms.*',
        label: 'Formularios',
        adminOnly: true,
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    },
    {
        route: 'custom-fields.index',
        match: 'custom-fields.*',
        label: 'Campos',
        adminOnly: true,
        icon: 'M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75',
    },
    {
        route: 'settings.team',
        match: 'settings.team',
        label: 'Equipo',
        adminOnly: true,
        icon: 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
    },
    {
        route: 'settings.integration',
        match: 'settings.integration',
        label: 'Integración',
        adminOnly: true,
        icon: 'M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244',
    },
];

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const unread = usePage().props.unreadNotifications ?? 0;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const isAdmin = user?.account_role === 'owner' || user?.account_role === 'admin';
    const visibleNav = NAV.filter((item) => !item.adminOnly || isAdmin);

    const nav = (mobile = false) => (
        <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            <Link
                href={route('notifications')}
                onClick={() => mobile && setSidebarOpen(false)}
                className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                    route().current('notifications')
                        ? 'bg-white/15 text-white shadow-sm'
                        : 'text-white/60 hover:text-white hover:bg-white/10'
                }`}
            >
                <span className="relative shrink-0">
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                    {unread > 0 && (
                        <span className="absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-bold text-white ring-2 ring-[#045474]">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </span>
                Notificaciones
            </Link>
            <div className="h-px bg-white/10 my-2 mx-3" />
            {visibleNav.map((item) => {
                const active = route().current(item.match);
                return (
                    <Link
                        key={item.route}
                        href={route(item.route)}
                        onClick={() => mobile && setSidebarOpen(false)}
                        className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all ${
                            active
                                ? 'bg-white/15 text-white shadow-sm'
                                : 'text-white/60 hover:text-white hover:bg-white/10'
                        }`}
                    >
                        <svg className="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                            <path strokeLinecap="round" strokeLinejoin="round" d={item.icon} />
                        </svg>
                        {item.label}
                    </Link>
                );
            })}
        </nav>
    );

    const userBlock = (
        <div className="border-t border-white/10 p-3">
            <div className="flex items-center gap-3 px-2 py-2">
                <div className="w-9 h-9 rounded-xl bg-white/15 flex items-center justify-center text-white text-sm font-bold shrink-0">
                    {user.name.charAt(0).toUpperCase()}
                </div>
                <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold text-white truncate">{user.name}</p>
                    <p className="text-[11px] text-white/50 truncate">{user.email}</p>
                </div>
            </div>
            <div className="flex gap-1 mt-1">
                <Link
                    href={route('profile.edit')}
                    className="flex-1 text-center px-2 py-1.5 rounded-lg text-xs font-medium text-white/60 hover:text-white hover:bg-white/10 transition-all"
                >
                    Perfil
                </Link>
                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="flex-1 text-center px-2 py-1.5 rounded-lg text-xs font-medium text-white/60 hover:text-white hover:bg-white/10 transition-all"
                >
                    Salir
                </Link>
            </div>
        </div>
    );

    return (
        <div className="min-h-screen bg-gray-100">
            {/* Sidebar desktop */}
            <aside className="hidden lg:flex fixed inset-y-0 left-0 w-64 flex-col bg-gradient-to-b from-[#045474] to-[#1c486c] z-30">
                <div className="flex items-center gap-3 px-6 py-5">
                    <div className="w-9 h-9 rounded-xl bg-white/15 flex items-center justify-center text-white shadow-sm">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22" />
                        </svg>
                    </div>
                    <span className="text-lg font-bold text-white tracking-tight">Komo CRM</span>
                </div>
                {nav()}
                {userBlock}
            </aside>

            {/* Sidebar móvil */}
            {sidebarOpen && (
                <div className="lg:hidden fixed inset-0 z-40">
                    <div className="absolute inset-0 bg-black/40" onClick={() => setSidebarOpen(false)} />
                    <aside className="absolute inset-y-0 left-0 w-64 flex flex-col bg-gradient-to-b from-[#045474] to-[#1c486c]">
                        <div className="flex items-center justify-between px-6 py-5">
                            <span className="text-lg font-bold text-white">Komo CRM</span>
                            <button onClick={() => setSidebarOpen(false)} className="p-1 text-white/60 hover:text-white">
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        {nav(true)}
                        {userBlock}
                    </aside>
                </div>
            )}

            {/* Contenido */}
            <div className="lg:pl-64">
                <header className="lg:hidden sticky top-0 z-20 flex items-center gap-3 bg-white border-b border-gray-100 px-4 py-3">
                    <button onClick={() => setSidebarOpen(true)} className="p-1.5 text-gray-500 hover:bg-gray-100 rounded-lg">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <span className="font-bold text-gray-900">Komo CRM</span>
                </header>
                <main>{children}</main>
            </div>
        </div>
    );
}
