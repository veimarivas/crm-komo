import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

function money(value, currency) {
    return new Intl.NumberFormat('es', { style: 'currency', currency: currency || 'USD', maximumFractionDigits: 0 }).format(value || 0);
}

const EVENT_META = {
    created: { label: 'Lead creado', icon: '✨', color: 'bg-emerald-100 text-emerald-700' },
    stage_changed: { label: 'Cambio de etapa', icon: '➡️', color: 'bg-blue-100 text-blue-700' },
    won: { label: 'Ganado', icon: '🏆', color: 'bg-emerald-100 text-emerald-700' },
    lost: { label: 'Perdido', icon: '✕', color: 'bg-red-100 text-red-700' },
    reopened: { label: 'Reabierto', icon: '🔄', color: 'bg-sky-100 text-sky-700' },
    note_added: { label: 'Nota', icon: '📝', color: 'bg-amber-100 text-amber-700' },
    task_created: { label: 'Tarea creada', icon: '📋', color: 'bg-purple-100 text-purple-700' },
    task_completed: { label: 'Tarea completada', icon: '✅', color: 'bg-emerald-100 text-emerald-700' },
    message_in: { label: 'WhatsApp recibido', icon: '💬', color: 'bg-teal-100 text-teal-700' },
    message_out: { label: 'WhatsApp enviado', icon: '📤', color: 'bg-[#e6f0f4] text-[#045474]' },
    value_changed: { label: 'Valor actualizado', icon: '💰', color: 'bg-amber-100 text-amber-700' },
};

const inputClass = 'w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 focus:bg-white transition-all';

const AVATAR_COLORS = [
    'from-emerald-500 to-teal-600',
    'from-blue-500 to-indigo-600',
    'from-purple-500 to-pink-600',
    'from-amber-500 to-orange-600',
    'from-rose-500 to-red-600',
    'from-cyan-500 to-sky-600',
    'from-lime-500 to-green-600',
    'from-fuchsia-500 to-purple-600',
];

function avatarFor(name) {
    const label = (name || '?').trim();
    const initials = label.split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || '?';
    let hash = 0;
    for (let i = 0; i < label.length; i++) hash = (hash * 31 + label.charCodeAt(i)) | 0;
    const gradient = AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
    return { initials, gradient };
}

function Avatar({ name, size = 'md' }) {
    const { initials, gradient } = avatarFor(name);
    const sizes = { sm: 'w-8 h-8 text-xs', md: 'w-10 h-10 text-sm', lg: 'w-12 h-12 text-base' };
    return (
        <div className={`${sizes[size]} rounded-full bg-gradient-to-br ${gradient} flex items-center justify-center font-bold text-white shadow-sm shrink-0`}>
            {initials}
        </div>
    );
}

function dayLabel(iso) {
    const d = new Date(iso);
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    const same = (a, b) => a.toDateString() === b.toDateString();
    if (same(d, today)) return 'Hoy';
    if (same(d, yesterday)) return 'Ayer';
    return d.toLocaleDateString('es', { weekday: 'long', day: 'numeric', month: 'long' });
}

function DateSeparator({ label }) {
    return (
        <div className="flex items-center gap-3 py-2">
            <div className="flex-1 h-px bg-gray-200" />
            <span className="text-[11px] font-bold uppercase tracking-wider text-gray-400 bg-white px-3 py-1 rounded-full shadow-sm">{label}</span>
            <div className="flex-1 h-px bg-gray-200" />
        </div>
    );
}

function ChatBubble({ event, contactName }) {
    const isCustomer = event.event_type === 'message_in';
    const p = event.payload ?? {};
    const time = new Date(event.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    return (
        <div className={`flex items-end gap-2 ${isCustomer ? 'justify-start' : 'justify-end'}`}>
            {isCustomer && <Avatar name={contactName} size="sm" />}
            <div
                className={`max-w-[70%] rounded-2xl px-3.5 py-2.5 text-sm shadow-sm ${
                    isCustomer
                        ? 'bg-white text-gray-900 rounded-bl-md border border-gray-100'
                        : 'bg-gradient-to-br from-[#045474] to-[#1c486c] text-white rounded-br-md shadow-md shadow-[#045474]/20'
                }`}
            >
                <p className="whitespace-pre-wrap break-words leading-relaxed">{p.text || '[sin texto]'}</p>
                <div className={`mt-1 flex items-center gap-1 text-[10px] ${isCustomer ? 'text-gray-400' : 'text-white/70'}`}>
                    <span>{time}</span>
                    {!isCustomer && <span>✓✓</span>}
                </div>
            </div>
        </div>
    );
}

function SystemEvent({ event }) {
    const meta = EVENT_META[event.event_type] ?? { label: event.event_type, icon: '·', color: 'bg-gray-100 text-gray-600' };
    const p = event.payload ?? {};
    let description = null;
    if (event.event_type === 'stage_changed') description = <>{p.from} → <span className="font-semibold">{p.to}</span></>;
    else if (event.event_type === 'value_changed') description = <>{p.from} → <span className="font-semibold">{p.to}</span></>;
    else if (['note_added', 'task_created', 'task_completed'].includes(event.event_type) && p.text) description = <span className="italic">"{p.text}"{p.result ? ` — ${p.result}` : ''}</span>;

    return (
        <div className="flex items-center justify-center gap-2 py-1">
            <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-medium ${meta.color}`}>
                <span>{meta.icon}</span>
                <span>{meta.label}</span>
                {description && <span className="text-[10px] opacity-80">· {description}</span>}
            </span>
        </div>
    );
}

function TimelineEvent({ event }) {
    const meta = EVENT_META[event.event_type] ?? { label: event.event_type, icon: '·', color: 'bg-gray-100 text-gray-600' };
    const p = event.payload ?? {};

    return (
        <div className="flex gap-3">
            <div className={`w-8 h-8 shrink-0 rounded-xl flex items-center justify-center text-sm ${meta.color}`}>{meta.icon}</div>
            <div className="flex-1 min-w-0 pb-4 border-b border-gray-50">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-sm font-semibold text-gray-900">{meta.label}</p>
                    <span className="text-[11px] text-gray-400 shrink-0">
                        {new Date(event.created_at).toLocaleString('es', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                    </span>
                </div>
                {event.event_type === 'stage_changed' && (
                    <p className="text-xs text-gray-500 mt-0.5">{p.from} → <span className="font-semibold">{p.to}</span></p>
                )}
                {(event.event_type === 'message_in' || event.event_type === 'message_out') && p.text && (
                    <p className={`text-sm mt-1.5 rounded-xl px-3 py-2 ${event.event_type === 'message_in' ? 'bg-gray-50 text-gray-700' : 'bg-emerald-50 text-emerald-900'}`}>
                        {p.text}
                    </p>
                )}
                {(event.event_type === 'note_added' || event.event_type === 'task_created' || event.event_type === 'task_completed') && p.text && (
                    <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">{p.text}{p.result ? ` — ${p.result}` : ''}</p>
                )}
                {event.event_type === 'value_changed' && (
                    <p className="text-xs text-gray-500 mt-0.5">{p.from} → <span className="font-semibold">{p.to}</span></p>
                )}
                {event.actor && <p className="text-[11px] text-gray-300 mt-1">por {event.actor.name}</p>}
            </div>
        </div>
    );
}

export default function Show({ lead, stages, events, tasks, notes, members, contacts, companies, allTags, customFields, customValues, whatsappEnabled }) {
    const { flash, auth } = usePage().props;
    const isAdmin = auth?.user?.account_role === 'owner' || auth?.user?.account_role === 'admin';
    const [tab, setTab] = useState('chat');
    const [newTag, setNewTag] = useState(null);
    const bottomRef = useRef(null);

    const editForm = useForm({
        title: lead.title,
        value: lead.value,
        contact_id: lead.contact_id ?? '',
        company_id: lead.company_id ?? '',
        responsible_user_id: lead.responsible_user_id ?? '',
        custom_values: customValues ?? {},
    });

    const noteForm = useForm({ text: '' });
    const taskForm = useForm({ lead_id: lead.id, task_type: 'call', text: '', due_at: '', assigned_to: '' });
    const waForm = useForm({ text: '' });

    const saveEdit = (e) => { e.preventDefault(); editForm.patch(route('leads.update', lead.id), { preserveScroll: true }); };
    const moveTo = (stageId) => router.patch(route('leads.move', lead.id), { stage_id: stageId }, { preserveScroll: true });
    const toggleTag = (tagId) => {
        const current = (lead.tags ?? []).map((t) => t.id);
        const next = current.includes(tagId) ? current.filter((id) => id !== tagId) : [...current, tagId];
        router.patch(route('leads.tags', lead.id), { tag_ids: next }, { preserveScroll: true });
    };

    const wonStage = stages.find((s) => s.stage_type === 'won');
    const lostStage = stages.find((s) => s.stage_type === 'lost');
    const pendingTasks = tasks.filter((t) => !t.completed_at);
    const contactName = lead.contact?.name || lead.contact?.phone || 'Contacto';

    // Cronología del chat: eventos en orden ascendente para leer como conversación
    const chatItems = useMemo(() => {
        const arr = [...events].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        const out = [];
        let currentDay = null;
        for (const e of arr) {
            const dayKey = new Date(e.created_at).toDateString();
            if (dayKey !== currentDay) {
                out.push({ kind: 'day', id: `d-${dayKey}`, label: dayLabel(e.created_at) });
                currentDay = dayKey;
            }
            if (e.event_type === 'message_in' || e.event_type === 'message_out') {
                out.push({ kind: 'bubble', event: e });
            } else {
                out.push({ kind: 'system', event: e });
            }
        }
        return out;
    }, [events]);

    useEffect(() => { if (tab === 'chat') bottomRef.current?.scrollIntoView({ behavior: 'smooth' }); }, [tab, chatItems.length]);

    // Polling en tiempo real (5s) mientras la pestaña Chat esté activa y visible.
    // Solo refetch los props que cambian (events + tasks + notes), preservando
    // scroll y estado local del formulario.
    useEffect(() => {
        if (tab !== 'chat') return;
        const tick = () => {
            if (document.hidden) return; // no consume batería con la pestaña en segundo plano
            router.reload({ only: ['events', 'tasks', 'notes', 'lead'], preserveScroll: true, preserveState: true });
        };
        const id = setInterval(tick, 5000);
        return () => clearInterval(id);
    }, [tab]);

    return (
        <AuthenticatedLayout>
            <Head title={lead.title} />

            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div className="min-w-0">
                        <Link href={route('leads.index')} className="text-sm text-emerald-600 hover:text-emerald-700 font-medium inline-flex items-center gap-1">
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                            Volver a leads
                        </Link>
                        <div className="flex flex-wrap items-center gap-3 mt-1">
                            <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">{lead.title}</h1>
                            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold text-white shadow-sm" style={{ backgroundColor: lead.stage?.color }}>
                                {lead.stage?.name}
                            </span>
                        </div>
                        <p className="text-sm text-gray-400 mt-1">
                            {money(lead.value, lead.currency)} · pipeline {lead.pipeline?.name}
                            {lead.source === 'whatsapp' && ' · 💬 llegó por WhatsApp'}
                            {lead.source === 'lead_ad' && ' · 📣 llegó por Lead Ad'}
                        </p>
                        {lead.source_ref && (
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-1 mt-2 rounded-full text-xs font-bold ring-1 bg-blue-50 text-blue-800 ring-blue-200">
                                <span className="w-1.5 h-1.5 rounded-full bg-blue-500" />
                                Vino del anuncio {lead.source_ref}
                                {lead.source_url && <a href={lead.source_url} target="_blank" rel="noreferrer" className="underline hover:text-blue-600 font-semibold">ver anuncio ↗</a>}
                            </span>
                        )}
                    </div>

                    <div className="flex items-center gap-2 flex-wrap shrink-0">
                        <select value={lead.stage_id} onChange={(e) => moveTo(e.target.value)} className="px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm font-medium bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 transition-all shadow-sm">
                            {stages.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                        {lead.status === 'open' && wonStage && (
                            <button onClick={() => moveTo(wonStage.id)} className="px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl hover:from-emerald-500 hover:to-teal-500 transition-all shadow-lg shadow-emerald-500/20">
                                🏆 Ganado
                            </button>
                        )}
                        {lead.status === 'open' && lostStage && (
                            <button onClick={() => moveTo(lostStage.id)} className="px-4 py-2.5 text-sm font-semibold text-red-600 bg-white border border-red-200 rounded-xl hover:bg-red-50 transition-all shadow-sm">
                                Perdido
                            </button>
                        )}
                    </div>
                </div>

                {flash?.success && <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 shadow-sm">{flash.success}</div>}
                {pendingTasks.length === 0 && lead.status === 'open' && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 shadow-sm">
                        ⚠️ Este lead no tiene ninguna tarea pendiente — agenda el siguiente paso para que no se enfríe.
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Columna izquierda: datos del lead */}
                    <div className="space-y-6">
                        <form onSubmit={saveEdit} className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 space-y-4">
                            <h3 className="text-sm font-bold text-gray-900">Datos del lead</h3>
                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Título</label>
                                <input value={editForm.data.title} onChange={(e) => editForm.setData('title', e.target.value)} className={inputClass} />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Valor ({lead.currency})</label>
                                <input type="number" step="0.01" min="0" value={editForm.data.value} onChange={(e) => editForm.setData('value', e.target.value)} className={inputClass} />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Contacto</label>
                                <select value={editForm.data.contact_id} onChange={(e) => editForm.setData('contact_id', e.target.value)} className={inputClass}>
                                    <option value="">— Sin contacto —</option>
                                    {contacts.map((c) => <option key={c.id} value={c.id}>{c.name || c.phone}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Empresa</label>
                                <select value={editForm.data.company_id} onChange={(e) => editForm.setData('company_id', e.target.value)} className={inputClass}>
                                    <option value="">— Sin empresa —</option>
                                    {companies.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Responsable</label>
                                {isAdmin ? (
                                    <select value={editForm.data.responsible_user_id} onChange={(e) => editForm.setData('responsible_user_id', e.target.value)} className={inputClass}>
                                        <option value="">— Nadie —</option>
                                        {members.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                                    </select>
                                ) : (
                                    <div className="flex items-center gap-2 px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50 text-gray-700">
                                        <svg className="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                        {lead.responsible?.name || 'Sin asignar'}
                                    </div>
                                )}
                            </div>
                            {customFields.map((field) => (
                                <div key={field.id}>
                                    <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">{field.name}</label>
                                    {field.field_type === 'select' ? (
                                        <select
                                            value={editForm.data.custom_values[field.id] ?? ''}
                                            onChange={(e) => editForm.setData('custom_values', { ...editForm.data.custom_values, [field.id]: e.target.value })}
                                            className={inputClass}
                                        >
                                            <option value="">—</option>
                                            {(field.options ?? []).map((opt) => <option key={opt} value={opt}>{opt}</option>)}
                                        </select>
                                    ) : (
                                        <input
                                            type={field.field_type === 'number' ? 'number' : field.field_type === 'date' ? 'date' : 'text'}
                                            value={editForm.data.custom_values[field.id] ?? ''}
                                            onChange={(e) => editForm.setData('custom_values', { ...editForm.data.custom_values, [field.id]: e.target.value })}
                                            className={inputClass}
                                        />
                                    )}
                                </div>
                            ))}

                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1.5">Etiquetas</label>
                                <div className="flex flex-wrap gap-1.5">
                                    {allTags.map((tag) => {
                                        const active = (lead.tags ?? []).some((t) => t.id === tag.id);
                                        return (
                                            <button
                                                key={tag.id}
                                                type="button"
                                                onClick={() => toggleTag(tag.id)}
                                                className={`rounded-full px-2.5 py-1 text-xs font-medium transition-all ${active ? 'text-white shadow-md' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'}`}
                                                style={active ? { backgroundColor: tag.color } : {}}
                                            >
                                                {tag.name}
                                            </button>
                                        );
                                    })}
                                    {newTag === null ? (
                                        <button type="button" onClick={() => setNewTag('')} className="rounded-full px-2.5 py-1 text-xs font-medium border border-dashed border-gray-300 text-gray-400 hover:border-emerald-400 hover:text-emerald-600 transition-all">
                                            + Nueva
                                        </button>
                                    ) : (
                                        <span className="inline-flex items-center gap-1">
                                            <input
                                                autoFocus
                                                value={newTag}
                                                onChange={(e) => setNewTag(e.target.value)}
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                        if (newTag.trim()) router.post(route('tags.store'), { name: newTag.trim() }, { preserveScroll: true, onSuccess: () => setNewTag(null) });
                                                    }
                                                    if (e.key === 'Escape') setNewTag(null);
                                                }}
                                                placeholder="nombre + Enter"
                                                className="w-28 px-2 py-1 border border-emerald-300 rounded-full text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/30"
                                            />
                                        </span>
                                    )}
                                </div>
                            </div>

                            <button type="submit" disabled={editForm.processing} className="w-full px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#045474] to-[#1c486c] rounded-xl hover:opacity-90 disabled:opacity-50 transition-all shadow-lg shadow-[#045474]/20">
                                Guardar cambios
                            </button>
                            <button type="button" onClick={() => { if (confirm('¿Eliminar este lead y su historial?')) router.delete(route('leads.destroy', lead.id)); }} className="w-full text-xs text-red-500 hover:text-red-700 font-medium">
                                Eliminar lead
                            </button>
                        </form>
                    </div>

                    {/* Columna central+derecha: tabs (chat/tareas/notas/timeline) */}
                    <div className="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col" style={{ minHeight: '70vh' }}>
                        <div className="flex border-b border-gray-100 bg-white">
                            {[
                                ['chat', '💬 Chat'],
                                ['tasks', `Tareas (${pendingTasks.length})`],
                                ['notes', `Notas (${notes.length})`],
                                ['timeline', `Timeline (${events.length})`],
                            ].map(([key, label]) => (
                                <button
                                    key={key}
                                    onClick={() => setTab(key)}
                                    className={`px-5 py-3.5 text-sm font-semibold transition-all border-b-2 ${tab === key ? 'border-emerald-500 text-emerald-700' : 'border-transparent text-gray-400 hover:text-gray-600'}`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>

                        {tab === 'chat' && (
                            <>
                                {/* Header del chat: avatar + nombre + tel */}
                                <div className="flex items-center gap-3 px-5 py-3 border-b border-gray-100 bg-gradient-to-r from-[#045474]/5 to-transparent">
                                    <Avatar name={contactName} size="lg" />
                                    <div className="min-w-0 flex-1">
                                        <p className="font-bold text-gray-900 truncate">{contactName}</p>
                                        <p className="text-xs text-gray-500 font-mono truncate">{lead.contact?.phone || 'sin teléfono'}</p>
                                    </div>
                                    {whatsappEnabled && lead.wacrm_conversation_id && (
                                        (isAdmin || !lead.responsible_user_id || lead.responsible_user_id === auth?.user?.id) ? (
                                            <button
                                                type="button"
                                                onClick={() => router.patch(route('leads.ai-mode', lead.id), { ai_enabled: !lead.ai_enabled }, { preserveScroll: true })}
                                                title={lead.ai_enabled ? 'Cambiar a Humano (silenciar IA)' : 'Cambiar a IA (auto-respuesta)'}
                                                className={`inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold border transition-all shadow-sm ${
                                                    lead.ai_enabled
                                                        ? 'border-violet-300 bg-gradient-to-br from-violet-50 to-purple-50 text-violet-700 shadow-violet-500/10'
                                                        : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'
                                                }`}
                                            >
                                                {lead.ai_enabled ? (
                                                    <>
                                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                                        </svg>
                                                        IA activa
                                                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                                                    </>
                                                ) : (
                                                    <>
                                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                            <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                        </svg>
                                                        Humano
                                                    </>
                                                )}
                                            </button>
                                        ) : (
                                            <span
                                                title="Solo el responsable o el admin pueden cambiar el modo IA"
                                                className={`inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold border ${
                                                    lead.ai_enabled ? 'border-violet-200 bg-violet-50 text-violet-700' : 'border-gray-200 bg-gray-50 text-gray-600'
                                                }`}
                                            >
                                                {lead.ai_enabled ? '✨ IA activa' : '👤 Humano'}
                                            </span>
                                        )
                                    )}
                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold ring-1 bg-emerald-50 text-emerald-700 ring-emerald-200">
                                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                                        {lead.status === 'open' ? 'Activo' : lead.status}
                                    </span>
                                </div>

                                {/* Hilo */}
                                <div className="flex-1 overflow-y-auto px-5 py-4 space-y-2 bg-gradient-to-b from-gray-50 to-gray-100">
                                    {chatItems.length === 0 && (
                                        <p className="py-8 text-center text-sm text-gray-400">Sin conversación todavía. Envía el primer mensaje ↓</p>
                                    )}
                                    {chatItems.map((it) => {
                                        if (it.kind === 'day') return <DateSeparator key={it.id} label={it.label} />;
                                        if (it.kind === 'bubble') return <ChatBubble key={it.event.id} event={it.event} contactName={contactName} />;
                                        return <SystemEvent key={it.event.id} event={it.event} />;
                                    })}
                                    <div ref={bottomRef} />
                                </div>

                                {/* Composer */}
                                <form
                                    onSubmit={(e) => { e.preventDefault(); if (whatsappEnabled) waForm.post(route('leads.whatsapp', lead.id), { preserveScroll: true, onSuccess: () => waForm.reset() }); }}
                                    className="border-t border-gray-100 bg-white p-3"
                                >
                                    {!whatsappEnabled ? (
                                        <p className="text-xs text-gray-400 text-center py-2">
                                            {lead.contact?.phone
                                                ? <>Activa la <Link href={route('settings.integration')} className="text-emerald-600 font-semibold underline">integración con el CRM de WhatsApp</Link> para escribirle desde aquí.</>
                                                : 'Asigna un contacto con teléfono para enviarle WhatsApp.'}
                                        </p>
                                    ) : (
                                        <>
                                            {waForm.errors.text && <p className="mb-2 text-xs text-red-500 font-medium">{waForm.errors.text}</p>}
                                            <div className="flex items-end gap-2">
                                                <textarea
                                                    rows={1}
                                                    value={waForm.data.text}
                                                    onChange={(e) => waForm.setData('text', e.target.value)}
                                                    onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); if (waForm.data.text.trim()) waForm.post(route('leads.whatsapp', lead.id), { preserveScroll: true, onSuccess: () => waForm.reset() }); } }}
                                                    placeholder={`Mensaje para ${contactName}…`}
                                                    className="flex-1 resize-none px-4 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#045474]/20 focus:border-[#045474] focus:bg-white transition-all max-h-32"
                                                />
                                                <button type="submit" disabled={waForm.processing || !waForm.data.text.trim()} className="rounded-xl px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-br from-[#045474] to-[#1c486c] hover:opacity-90 disabled:opacity-50 transition-all shadow-lg shadow-[#045474]/20 flex items-center gap-1.5">
                                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                                    </svg>
                                                    {waForm.processing ? '…' : 'Enviar'}
                                                </button>
                                            </div>
                                        </>
                                    )}
                                </form>
                            </>
                        )}

                        {tab === 'tasks' && (
                            <div className="p-5 space-y-4 overflow-y-auto">
                                <form
                                    onSubmit={(e) => { e.preventDefault(); taskForm.post(route('tasks.store'), { preserveScroll: true, onSuccess: () => taskForm.reset('text', 'due_at') }); }}
                                    className="rounded-xl bg-gray-50 border border-gray-100 p-4 space-y-3"
                                >
                                    <p className="text-xs font-bold uppercase tracking-wider text-gray-500">Nueva tarea</p>
                                    <div className="grid sm:grid-cols-3 gap-3">
                                        <select value={taskForm.data.task_type} onChange={(e) => taskForm.setData('task_type', e.target.value)} className={inputClass.replace('bg-gray-50', 'bg-white')}>
                                            <option value="call">📞 Llamar</option>
                                            <option value="meet">🤝 Reunión</option>
                                            <option value="follow_up">🔔 Seguimiento</option>
                                            <option value="email">✉️ Email</option>
                                            <option value="other">Otra</option>
                                        </select>
                                        <input type="datetime-local" value={taskForm.data.due_at} onChange={(e) => taskForm.setData('due_at', e.target.value)} required className={inputClass.replace('bg-gray-50', 'bg-white')} />
                                        <select value={taskForm.data.assigned_to} onChange={(e) => taskForm.setData('assigned_to', e.target.value)} className={inputClass.replace('bg-gray-50', 'bg-white')}>
                                            <option value="">Yo</option>
                                            {members.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                                        </select>
                                    </div>
                                    <div className="flex gap-2">
                                        <input value={taskForm.data.text} onChange={(e) => taskForm.setData('text', e.target.value)} placeholder="¿Qué hay que hacer?" required className={inputClass.replace('bg-gray-50', 'bg-white')} />
                                        <button type="submit" disabled={taskForm.processing} className="shrink-0 px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50 transition-all shadow-lg shadow-emerald-500/20">
                                            Crear
                                        </button>
                                    </div>
                                </form>

                                <ul className="space-y-2">
                                    {tasks.map((task) => {
                                        const overdue = !task.completed_at && new Date(task.due_at) < new Date();
                                        return (
                                            <li key={task.id} className={`flex items-center gap-3 rounded-xl border p-3.5 ${task.completed_at ? 'border-gray-100 bg-gray-50/50 opacity-60' : overdue ? 'border-red-200 bg-red-50/50' : 'border-gray-100 bg-white'}`}>
                                                {!task.completed_at ? (
                                                    <button
                                                        onClick={() => { const note = prompt('Resultado (opcional):'); if (note !== null) router.post(route('tasks.complete', task.id), { result_note: note || null }, { preserveScroll: true }); }}
                                                        className="w-5 h-5 shrink-0 rounded-full border-2 border-gray-300 hover:border-emerald-500 hover:bg-emerald-50 transition-all"
                                                        title="Completar"
                                                    />
                                                ) : (
                                                    <span className="w-5 h-5 shrink-0 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px]">✓</span>
                                                )}
                                                <div className="flex-1 min-w-0">
                                                    <p className={`text-sm font-medium ${task.completed_at ? 'line-through text-gray-400' : 'text-gray-900'}`}>{task.text}</p>
                                                    <p className="text-xs text-gray-400">
                                                        {task.assignee?.name ?? '—'} · {new Date(task.due_at).toLocaleString('es', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                                                        {overdue && <span className="text-red-500 font-semibold ml-1">· vencida</span>}
                                                        {task.result_note && <span className="ml-1">· {task.result_note}</span>}
                                                    </p>
                                                </div>
                                            </li>
                                        );
                                    })}
                                    {tasks.length === 0 && <p className="py-6 text-center text-sm text-gray-400">Sin tareas</p>}
                                </ul>
                            </div>
                        )}

                        {tab === 'notes' && (
                            <div className="p-5 space-y-4 overflow-y-auto">
                                <form
                                    onSubmit={(e) => { e.preventDefault(); noteForm.post(route('leads.notes.add', lead.id), { preserveScroll: true, onSuccess: () => noteForm.reset() }); }}
                                    className="flex gap-2"
                                >
                                    <input value={noteForm.data.text} onChange={(e) => noteForm.setData('text', e.target.value)} placeholder="Escribe una nota interna…" required className={inputClass} />
                                    <button type="submit" disabled={noteForm.processing} className="shrink-0 px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-amber-500 to-orange-500 rounded-xl hover:from-amber-400 hover:to-orange-400 disabled:opacity-50 transition-all shadow-lg shadow-amber-500/20">
                                        Añadir
                                    </button>
                                </form>
                                <ul className="space-y-2">
                                    {notes.map((note) => (
                                        <li key={note.id} className="rounded-xl bg-amber-50/60 border border-amber-100 p-3.5">
                                            <p className="text-sm text-gray-800 whitespace-pre-wrap">{note.text}</p>
                                            <p className="text-[11px] text-gray-400 mt-1.5">{note.author?.name ?? '—'} · {new Date(note.created_at).toLocaleString('es', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}</p>
                                        </li>
                                    ))}
                                    {notes.length === 0 && <p className="py-6 text-center text-sm text-gray-400">Sin notas</p>}
                                </ul>
                            </div>
                        )}

                        {tab === 'timeline' && (
                            <div className="p-5 space-y-4 overflow-y-auto">
                                {events.map((event) => <TimelineEvent key={event.id} event={event} />)}
                                {events.length === 0 && <p className="py-8 text-center text-sm text-gray-400">Sin actividad todavía</p>}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
