import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';

const EVENT_META = {
    created: { icon: '✨', color: 'bg-emerald-100 text-emerald-700', label: 'Lead creado' },
    stage_changed: { icon: '➡️', color: 'bg-blue-100 text-blue-700', label: 'Cambio etapa' },
    won: { icon: '🏆', color: 'bg-emerald-100 text-emerald-700', label: 'Ganado' },
    lost: { icon: '✕', color: 'bg-red-100 text-red-700', label: 'Perdido' },
    note_added: { icon: '📝', color: 'bg-amber-100 text-amber-700', label: 'Nota' },
    task_created: { icon: '📋', color: 'bg-purple-100 text-purple-700', label: 'Tarea' },
    task_completed: { icon: '✅', color: 'bg-emerald-100 text-emerald-700', label: 'Tarea completada' },
    message_in: { icon: '💬', color: 'bg-teal-100 text-teal-700', label: 'WhatsApp recibido' },
    message_out: { icon: '📤', color: 'bg-[#e6f0f4] text-[#045474]', label: 'WhatsApp enviado' },
};

function initials(name) {
    return (name || '?').trim().split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

export default function Timeline({ contact, leads, events, tasks, notes }) {
    const unified = useMemo(() => {
        const items = [];
        events.forEach(e => items.push({ kind: 'event', at: e.created_at, data: e }));
        tasks.forEach(t => items.push({ kind: 'task', at: t.due_at, data: t }));
        notes.forEach(n => items.push({ kind: 'note', at: n.created_at, data: n }));
        return items.sort((a, b) => new Date(b.at) - new Date(a.at));
    }, [events, tasks, notes]);

    return (
        <AuthenticatedLayout header={<h2 className="text-lg font-semibold text-gray-900">Timeline del contacto</h2>}>
            <Head title={contact.name || 'Contacto'} />

            <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">
                <Link href={route('contacts.index')} className="text-sm text-emerald-600 hover:text-emerald-700 font-medium inline-flex items-center gap-1">
                    ← Volver a contactos
                </Link>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex items-center gap-5">
                    <div className="w-16 h-16 rounded-full bg-gradient-to-br from-[#045474] to-[#1c486c] flex items-center justify-center text-white text-xl font-bold shadow-lg">{initials(contact.name)}</div>
                    <div className="flex-1 min-w-0">
                        <h1 className="text-2xl font-bold text-gray-900">{contact.name || 'Sin nombre'}</h1>
                        <div className="text-sm text-gray-500 flex flex-wrap gap-x-4 gap-y-1 mt-1 font-mono">
                            {contact.phone && <span>📱 {contact.phone}</span>}
                            {contact.email && <span>✉ {contact.email}</span>}
                            {contact.company && <span>🏢 {contact.company.name}</span>}
                        </div>
                        {contact.tags?.length > 0 && (
                            <div className="flex flex-wrap gap-1.5 mt-2">
                                {contact.tags.map(t => (
                                    <span key={t.id} className="px-2 py-0.5 rounded-full text-xs font-bold text-white" style={{ backgroundColor: t.color }}>{t.name}</span>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {leads.length > 0 && (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {leads.map(lead => (
                            <Link key={lead.id} href={route('leads.show', lead.id)} className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md hover:-translate-y-0.5 transition-all">
                                <div className="flex items-start justify-between gap-2 mb-2">
                                    <span className="font-semibold text-gray-900 text-sm truncate flex-1">{lead.title}</span>
                                    <span className="text-[10px] font-bold px-2 py-0.5 rounded-full text-white shadow-sm shrink-0" style={{ backgroundColor: lead.stage?.color }}>{lead.stage?.name}</span>
                                </div>
                                <div className="text-[10px] text-gray-400 flex items-center gap-2">
                                    <span>{lead.status === 'won' ? '🏆 Ganado' : lead.status === 'lost' ? '✕ Perdido' : '⏳ Abierto'}</span>
                                    {lead.responsible && <span>· 👤 {lead.responsible.name}</span>}
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="px-5 py-4 border-b border-gray-100">
                        <h3 className="text-base font-bold text-gray-900">Actividad completa ({unified.length})</h3>
                        <p className="text-xs text-gray-400 mt-0.5">Todos los leads, tareas, notas y mensajes de este contacto en orden cronológico</p>
                    </div>
                    <ul className="divide-y divide-gray-50 max-h-[70vh] overflow-y-auto">
                        {unified.map((item) => {
                            if (item.kind === 'event') {
                                const e = item.data;
                                const meta = EVENT_META[e.event_type] ?? { icon: '·', color: 'bg-gray-100 text-gray-600', label: e.event_type };
                                const p = e.payload ?? {};
                                return (
                                    <li key={`e-${e.id}`} className="p-4 flex gap-3 hover:bg-gray-50">
                                        <div className={`w-9 h-9 shrink-0 rounded-xl flex items-center justify-center text-lg ${meta.color}`}>{meta.icon}</div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between gap-2">
                                                <p className="text-sm font-semibold text-gray-900">{meta.label}</p>
                                                <span className="text-[11px] text-gray-400 shrink-0">{new Date(e.created_at).toLocaleString('es', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</span>
                                            </div>
                                            {p.text && <p className="text-sm text-gray-600 mt-0.5 italic">{String(p.text).substring(0, 200)}{String(p.text).length > 200 ? '…' : ''}</p>}
                                            {e.lead && (
                                                <p className="text-[10px] text-gray-400 mt-1">
                                                    <Link href={route('leads.show', e.lead.id)} className="text-emerald-600 hover:underline">→ {e.lead.title}</Link>
                                                    {e.actor && <span> · por {e.actor.name}</span>}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                );
                            }
                            if (item.kind === 'task') {
                                const t = item.data;
                                return (
                                    <li key={`t-${t.id}`} className="p-4 flex gap-3 hover:bg-gray-50">
                                        <div className={`w-9 h-9 shrink-0 rounded-xl flex items-center justify-center text-lg ${t.completed_at ? 'bg-emerald-100 text-emerald-700' : 'bg-purple-100 text-purple-700'}`}>{t.completed_at ? '✅' : '📋'}</div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between gap-2">
                                                <p className={`text-sm font-semibold ${t.completed_at ? 'line-through text-gray-400' : 'text-gray-900'}`}>{t.text}</p>
                                                <span className="text-[11px] text-gray-400 shrink-0">{new Date(t.due_at).toLocaleDateString('es', { day: 'numeric', month: 'short' })}</span>
                                            </div>
                                            <p className="text-[10px] text-gray-400 mt-1">{t.assignee?.name ?? 'Sin asignar'} {t.lead && (<> · <Link href={route('leads.show', t.lead.id)} className="text-emerald-600 hover:underline">{t.lead.title}</Link></>)}</p>
                                        </div>
                                    </li>
                                );
                            }
                            if (item.kind === 'note') {
                                const n = item.data;
                                return (
                                    <li key={`n-${n.id}`} className="p-4 flex gap-3 hover:bg-gray-50">
                                        <div className="w-9 h-9 shrink-0 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-lg">📝</div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between gap-2">
                                                <p className="text-sm font-semibold text-gray-900">Nota</p>
                                                <span className="text-[11px] text-gray-400 shrink-0">{new Date(n.created_at).toLocaleString('es', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}</span>
                                            </div>
                                            <p className="text-sm text-gray-700 mt-0.5 whitespace-pre-wrap">{n.text}</p>
                                            {n.author && <p className="text-[10px] text-gray-400 mt-1">por {n.author.name}</p>}
                                        </div>
                                    </li>
                                );
                            }
                            return null;
                        })}
                        {unified.length === 0 && <li className="p-8 text-center text-sm text-gray-400">Sin actividad todavía</li>}
                    </ul>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
