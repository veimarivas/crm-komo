import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

function money(value, currency) {
    return new Intl.NumberFormat('es', {
        style: 'currency',
        currency: currency || 'USD',
        maximumFractionDigits: 0,
    }).format(value || 0);
}

function LeadCard({ lead, currency }) {
    return (
        <div
            draggable
            onDragStart={(e) => {
                e.dataTransfer.setData('text/lead-id', lead.id);
                e.dataTransfer.effectAllowed = 'move';
            }}
            className="group rounded-xl border border-gray-100 bg-white p-3.5 shadow-sm hover:shadow-md hover:border-gray-300 transition-all cursor-grab active:cursor-grabbing"
        >
            <Link href={route('leads.show', lead.id)} className="block">
                <p className="text-sm font-semibold text-gray-900 group-hover:text-emerald-700 transition-colors line-clamp-2">
                    {lead.title}
                </p>
                <p className="text-lg font-extrabold text-gray-900 tabular-nums mt-1.5">
                    {money(lead.value, lead.currency || currency)}
                </p>
                <div className="flex items-center justify-between mt-2 pt-2 border-t border-gray-50">
                    <span className="text-xs text-gray-500 truncate">
                        {lead.contact?.name || <span className="italic text-gray-300">Sin contacto</span>}
                    </span>
                    {lead.pending_tasks_count === 0 ? (
                        <span title="Sin tarea pendiente" className="shrink-0 w-2 h-2 rounded-full bg-red-400 ring-2 ring-red-100" />
                    ) : (
                        <span title={`${lead.pending_tasks_count} tareas pendientes`} className="shrink-0 w-2 h-2 rounded-full bg-emerald-400 ring-2 ring-emerald-100" />
                    )}
                </div>
            </Link>
        </div>
    );
}

function NewLeadModal({ open, onClose, pipeline, contacts, members }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        pipeline_id: pipeline?.id ?? '',
        stage_id: '',
        title: '',
        value: '',
        contact_id: '',
        responsible_user_id: '',
    });

    const close = () => {
        reset();
        onClose();
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('leads.store'), { onSuccess: close });
    };

    const inputClass = 'w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 focus:bg-white transition-all';

    return (
        <Modal show={open} onClose={close}>
            <form onSubmit={submit}>
                <div className="px-6 pt-6 pb-4 border-b border-gray-100 flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white shadow-lg shadow-emerald-500/20">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </div>
                    <div>
                        <h2 className="text-base font-bold text-gray-900">Nuevo lead</h2>
                        <p className="text-xs text-gray-400 mt-0.5">Entra en la primera etapa del pipeline</p>
                    </div>
                </div>
                <div className="px-6 py-5 space-y-4">
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">Título <span className="text-red-500">*</span></label>
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)} required className={inputClass} placeholder="ej. Cotización sistema web" />
                        {errors.title && <p className="mt-1 text-xs text-red-500 font-medium">{errors.title}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">Valor</label>
                            <input type="number" step="0.01" min="0" value={data.value} onChange={(e) => setData('value', e.target.value)} className={inputClass} />
                        </div>
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">Etapa</label>
                            <select value={data.stage_id} onChange={(e) => setData('stage_id', e.target.value)} className={inputClass}>
                                <option value="">Primera etapa</option>
                                {pipeline?.stages?.filter((s) => s.stage_type === 'open').map((s) => (
                                    <option key={s.id} value={s.id}>{s.name}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">Contacto</label>
                            <select value={data.contact_id} onChange={(e) => setData('contact_id', e.target.value)} className={inputClass}>
                                <option value="">— Sin contacto —</option>
                                {contacts.map((c) => <option key={c.id} value={c.id}>{c.name || c.phone}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">Responsable</label>
                            <select value={data.responsible_user_id} onChange={(e) => setData('responsible_user_id', e.target.value)} className={inputClass}>
                                <option value="">Yo</option>
                                {members.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                            </select>
                        </div>
                    </div>
                </div>
                <div className="px-6 py-4 bg-gray-50/80 border-t border-gray-100 rounded-b-2xl flex justify-end gap-3">
                    <button type="button" onClick={close} className="px-4 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all shadow-sm">
                        Cancelar
                    </button>
                    <button type="submit" disabled={processing} className="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50 transition-all shadow-lg shadow-emerald-500/20">
                        Crear lead
                    </button>
                </div>
            </form>
        </Modal>
    );
}

export default function Index({ pipelines, pipeline, leads, members, contacts, currency }) {
    const { flash } = usePage().props;
    const [showNew, setShowNew] = useState(false);
    const [dragOver, setDragOver] = useState(null);

    const openLeads = leads.filter((l) => l.status === 'open');
    const totalOpen = openLeads.reduce((sum, l) => sum + Number(l.value || 0), 0);

    const dropOnStage = (e, stageId) => {
        e.preventDefault();
        setDragOver(null);
        const leadId = e.dataTransfer.getData('text/lead-id');
        const lead = leads.find((l) => l.id === leadId);
        if (lead && lead.stage_id !== stageId) {
            router.patch(route('leads.move', leadId), { stage_id: stageId }, { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Leads" />

            <div className="mx-auto max-w-full px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">Leads</h1>
                        <p className="text-sm text-gray-400 mt-1">
                            {openLeads.length} abiertos · <span className="font-semibold text-gray-600">{money(totalOpen, currency)}</span> en juego
                            · el punto rojo = lead sin tarea pendiente
                        </p>
                    </div>
                    <div className="flex items-center gap-2 flex-wrap">
                        {pipelines.length > 1 && (
                            <select
                                value={pipeline?.id ?? ''}
                                onChange={(e) => router.get(route('leads.index'), { pipeline: e.target.value })}
                                className="px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm font-medium bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 transition-all shadow-sm"
                            >
                                {pipelines.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </select>
                        )}
                        {pipeline && (
                            <Link
                                href={route('pipelines.automations', pipeline.id)}
                                className="px-3.5 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm flex items-center gap-1.5"
                                title="Digital Pipeline: acciones automáticas por etapa"
                            >
                                ⚡ Automatizar
                            </Link>
                        )}
                        <button
                            onClick={() => setShowNew(true)}
                            className="px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl hover:from-emerald-500 hover:to-teal-500 transition-all shadow-lg shadow-emerald-500/20 flex items-center gap-1.5"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Nuevo lead
                        </button>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 shadow-sm">
                        {flash.success}
                    </div>
                )}

                {!pipeline ? (
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center text-sm text-gray-400">
                        No hay pipeline configurado.
                    </div>
                ) : (
                    <div className="flex gap-4 overflow-x-auto pb-4 -mx-4 px-4 sm:mx-0 sm:px-0">
                        {pipeline.stages.map((stage) => {
                            const stageLeads = leads.filter((l) => l.stage_id === stage.id);
                            const stageTotal = stageLeads.reduce((sum, l) => sum + Number(l.value || 0), 0);
                            const isTerminal = stage.stage_type !== 'open';

                            return (
                                <div
                                    key={stage.id}
                                    onDragOver={(e) => { e.preventDefault(); setDragOver(stage.id); }}
                                    onDragLeave={() => setDragOver(null)}
                                    onDrop={(e) => dropOnStage(e, stage.id)}
                                    className={`flex w-72 shrink-0 flex-col rounded-2xl border-2 transition-all ${
                                        isTerminal ? 'bg-gray-50/70' : 'bg-gray-50'
                                    } ${dragOver === stage.id ? 'border-emerald-400 bg-emerald-50/50 scale-[1.02]' : 'border-transparent'}`}
                                >
                                    <div className="rounded-t-2xl px-4 py-3 text-white" style={{ background: `linear-gradient(135deg, ${stage.color} 0%, ${stage.color}dd 100%)` }}>
                                        <div className="flex items-center justify-between">
                                            <span className="font-bold text-sm flex items-center gap-1.5">
                                                {stage.stage_type === 'won' && '🏆'}
                                                {stage.stage_type === 'lost' && '✕'}
                                                {stage.name}
                                            </span>
                                            <span className="text-[10px] font-bold bg-white/25 rounded-full px-2 py-0.5">{stageLeads.length}</span>
                                        </div>
                                        <p className="text-xs font-medium text-white/80 mt-1 tabular-nums">{money(stageTotal, currency)}</p>
                                    </div>
                                    <div className="flex flex-1 flex-col gap-2 p-2.5 min-h-[180px]">
                                        {stageLeads.map((lead) => <LeadCard key={lead.id} lead={lead} currency={currency} />)}
                                        {stageLeads.length === 0 && (
                                            <p className="py-8 text-center text-xs text-gray-400 font-medium">
                                                {isTerminal ? '—' : 'Arrastra leads aquí'}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            <NewLeadModal open={showNew} onClose={() => setShowNew(false)} pipeline={pipeline} contacts={contacts} members={members} />
        </AuthenticatedLayout>
    );
}
