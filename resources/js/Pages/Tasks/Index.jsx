import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

const TYPE_ICONS = { call: '📞', meet: '🤝', follow_up: '🔔', email: '✉️', other: '📋' };

export default function Index({ tasks, filters, counts }) {
    const { flash } = usePage().props;

    const apply = (patch) =>
        router.get(route('tasks.index'), { ...filters, ...patch }, { preserveState: true, replace: true });

    const TABS = [
        ['pending', 'Pendientes'],
        ['today', `Hoy${counts.today ? ` (${counts.today})` : ''}`],
        ['overdue', `Vencidas${counts.overdue ? ` (${counts.overdue})` : ''}`],
        ['done', 'Completadas'],
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Tareas" />

            <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">Tareas</h1>
                        <p className="text-sm text-gray-400 mt-1">Tu agenda de seguimiento — las tareas se crean desde cada lead</p>
                    </div>
                    <label className="flex items-center gap-2 text-sm text-gray-600">
                        <input
                            type="checkbox"
                            checked={filters.mine}
                            onChange={(e) => apply({ mine: e.target.checked ? 1 : 0 })}
                            className="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        Solo mis tareas
                    </label>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 shadow-sm">{flash.success}</div>
                )}

                <div className="flex gap-1.5 flex-wrap">
                    {TABS.map(([key, label]) => (
                        <button
                            key={key}
                            onClick={() => apply({ filter: key })}
                            className={`px-3.5 py-2 rounded-xl text-sm font-semibold transition-all ${
                                filters.filter === key
                                    ? 'bg-gradient-to-r from-[#045474] to-[#1c486c] text-white shadow-lg shadow-[#045474]/20'
                                    : 'bg-white text-gray-600 border border-gray-200 hover:border-gray-300 shadow-sm'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <ul className="divide-y divide-gray-50">
                        {tasks.data.map((task) => {
                            const overdue = !task.completed_at && new Date(task.due_at) < new Date();
                            return (
                                <li key={task.id} className={`flex items-center gap-4 px-5 py-4 ${overdue ? 'bg-red-50/40' : ''}`}>
                                    {!task.completed_at ? (
                                        <button
                                            onClick={() => {
                                                const note = prompt('Resultado (opcional):');
                                                if (note !== null) router.post(route('tasks.complete', task.id), { result_note: note || null }, { preserveScroll: true });
                                            }}
                                            className="w-5 h-5 shrink-0 rounded-full border-2 border-gray-300 hover:border-emerald-500 hover:bg-emerald-50 transition-all"
                                            title="Completar"
                                        />
                                    ) : (
                                        <span className="w-5 h-5 shrink-0 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px]">✓</span>
                                    )}
                                    <span className="text-lg shrink-0">{TYPE_ICONS[task.task_type] ?? '📋'}</span>
                                    <div className="flex-1 min-w-0">
                                        <p className={`text-sm font-medium ${task.completed_at ? 'line-through text-gray-400' : 'text-gray-900'}`}>{task.text}</p>
                                        <p className="text-xs text-gray-400 truncate">
                                            {task.lead ? (
                                                <Link href={route('leads.show', task.lead.id)} className="text-emerald-600 hover:underline font-medium">{task.lead.title}</Link>
                                            ) : (task.contact?.name ?? 'Sin lead')}
                                            {' · '}{task.assignee?.name ?? '—'}
                                            {task.result_note && ` · ${task.result_note}`}
                                        </p>
                                    </div>
                                    <div className="text-right shrink-0">
                                        <p className={`text-xs font-bold tabular-nums ${overdue ? 'text-red-500' : 'text-gray-500'}`}>
                                            {new Date(task.due_at).toLocaleString('es', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                                        </p>
                                        {overdue && <p className="text-[10px] font-bold text-red-400 uppercase">vencida</p>}
                                    </div>
                                    <button
                                        onClick={() => { if (confirm('¿Eliminar esta tarea?')) router.delete(route('tasks.destroy', task.id), { preserveScroll: true }); }}
                                        className="p-1.5 text-gray-300 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors shrink-0"
                                    >
                                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                            <path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166" />
                                        </svg>
                                    </button>
                                </li>
                            );
                        })}
                        {tasks.data.length === 0 && (
                            <li className="px-5 py-14 text-center text-sm text-gray-400">Nada por aquí 🎉</li>
                        )}
                    </ul>
                    {(tasks.prev_page_url || tasks.next_page_url) && (
                        <div className="px-5 py-4 bg-gray-50/80 border-t border-gray-100 flex justify-end gap-3 text-sm">
                            {tasks.prev_page_url && <Link href={tasks.prev_page_url} className="text-emerald-600 font-medium">← Anterior</Link>}
                            {tasks.next_page_url && <Link href={tasks.next_page_url} className="text-emerald-600 font-medium">Siguiente →</Link>}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
