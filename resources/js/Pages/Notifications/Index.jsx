import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

const TYPE_META = {
    lead_assigned: { icon: '👤', gradient: 'from-[#045474] to-[#1c486c]' },
    lead_created_whatsapp: { icon: '💬', gradient: 'from-emerald-500 to-teal-600' },
    lead_created_web_form: { icon: '📋', gradient: 'from-purple-500 to-violet-600' },
    task_overdue: { icon: '⏰', gradient: 'from-red-500 to-rose-600' },
};

function timeAgo(iso) {
    const diff = (Date.now() - new Date(iso).getTime()) / 1000;
    if (diff < 60) return 'hace un momento';
    if (diff < 3600) return `hace ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `hace ${Math.floor(diff / 3600)} h`;
    return new Date(iso).toLocaleDateString('es', { day: 'numeric', month: 'short' });
}

export default function Index({ notifications }) {
    const unread = notifications.data.filter((n) => !n.read_at).length;

    return (
        <AuthenticatedLayout>
            <Head title="Notificaciones" />

            <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">Notificaciones</h1>
                        <p className="text-sm text-gray-400 mt-1">{unread > 0 ? `Tienes ${unread} sin leer` : 'Todo al día'}</p>
                    </div>
                    {unread > 0 && (
                        <button
                            onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}
                            className="px-3.5 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all shadow-sm w-fit"
                        >
                            ✓ Marcar todas como leídas
                        </button>
                    )}
                </div>

                <div className="space-y-3">
                    {notifications.data.map((n) => {
                        const meta = TYPE_META[n.type] ?? TYPE_META.lead_assigned;
                        return (
                            <div
                                key={n.id}
                                className={`rounded-2xl p-5 shadow-sm border transition-all hover:shadow-md ${
                                    n.read_at ? 'bg-white border-gray-100' : 'bg-gradient-to-br from-emerald-50 to-teal-50 border-emerald-200'
                                }`}
                            >
                                <div className="flex items-start gap-4">
                                    <div className={`w-10 h-10 shrink-0 rounded-xl bg-gradient-to-br ${meta.gradient} flex items-center justify-center text-white text-lg shadow-lg`}>
                                        {meta.icon}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="font-semibold text-gray-900">{n.title}</p>
                                            <span className="text-xs text-gray-400 shrink-0">{timeAgo(n.created_at)}</span>
                                        </div>
                                        {n.body && <p className="mt-1 text-sm text-gray-600">{n.body}</p>}
                                        {n.lead && (
                                            <Link href={route('leads.show', n.lead.id)} className="mt-1.5 inline-block text-xs font-semibold text-emerald-600 hover:text-emerald-700">
                                                Ver lead «{n.lead.title}» →
                                            </Link>
                                        )}
                                    </div>
                                    {!n.read_at && <span className="w-2 h-2 shrink-0 rounded-full bg-emerald-500 mt-2" />}
                                </div>
                            </div>
                        );
                    })}
                    {notifications.data.length === 0 && (
                        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
                            <p className="text-sm font-medium text-gray-600">Sin notificaciones</p>
                            <p className="text-xs text-gray-400 mt-1">Te avisaremos de leads asignados, nuevos leads y tareas vencidas</p>
                        </div>
                    )}
                </div>

                {(notifications.prev_page_url || notifications.next_page_url) && (
                    <div className="flex justify-end gap-3 text-sm">
                        {notifications.prev_page_url && <Link href={notifications.prev_page_url} className="text-emerald-600 font-medium">← Anterior</Link>}
                        {notifications.next_page_url && <Link href={notifications.next_page_url} className="text-emerald-600 font-medium">Siguiente →</Link>}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
