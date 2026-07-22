import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function money(value, currency) {
    return new Intl.NumberFormat('es', {
        style: 'currency',
        currency: currency || 'USD',
        maximumFractionDigits: 0,
    }).format(value || 0);
}

function Stat({ label, value, sub, gradient, iconPath, href, alert }) {
    const inner = (
        <div className={`bg-white rounded-2xl shadow-sm border p-5 transition hover:shadow-md ${alert ? 'border-red-200 ring-1 ring-red-100' : 'border-gray-100'}`}>
            <div className={`w-10 h-10 rounded-xl bg-gradient-to-br ${gradient} flex items-center justify-center text-white shadow-lg mb-3`}>
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                    <path strokeLinecap="round" strokeLinejoin="round" d={iconPath} />
                </svg>
            </div>
            <p className="text-xs font-semibold uppercase tracking-wider text-gray-400">{label}</p>
            <p className="text-2xl sm:text-3xl font-extrabold text-gray-900 mt-1 tabular-nums">{value}</p>
            {sub && <p className="text-xs text-gray-400 mt-1">{sub}</p>}
        </div>
    );
    return href ? <Link href={href}>{inner}</Link> : inner;
}

const STATUS_STYLES = {
    open: 'bg-sky-50 text-sky-700 ring-sky-200',
    won: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    lost: 'bg-red-50 text-red-700 ring-red-200',
};

export default function Dashboard({ stats, recentLeads, myTasks, currency }) {
    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6 sm:space-y-8">
                <div>
                    <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">Dashboard</h1>
                    <p className="text-sm text-gray-400 mt-1">Tu embudo de ventas de un vistazo</p>
                </div>

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
                    <Stat
                        label="Leads abiertos"
                        value={stats.openLeads}
                        sub={money(stats.openValue, currency) + ' en juego'}
                        gradient="from-[#045474] to-[#1c486c] shadow-[#045474]/20"
                        iconPath="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22"
                        href={route('leads.index')}
                    />
                    <Stat
                        label="Ganados este mes"
                        value={stats.wonThisMonth}
                        sub={money(stats.wonValueThisMonth, currency)}
                        gradient="from-emerald-500 to-teal-600 shadow-emerald-500/20"
                        iconPath="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"
                        href={route('leads.index')}
                    />
                    <Stat
                        label="Tareas hoy"
                        value={stats.tasksToday}
                        sub={stats.overdueTasks > 0 ? `${stats.overdueTasks} vencidas` : 'ninguna vencida'}
                        gradient="from-amber-400 to-orange-500 shadow-amber-500/20"
                        iconPath="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"
                        href={route('tasks.index')}
                        alert={stats.overdueTasks > 0}
                    />
                    <Stat
                        label="Leads sin tarea"
                        value={stats.leadsWithoutTask}
                        sub="regla: ningún lead sin tarea"
                        gradient="from-purple-500 to-violet-600 shadow-purple-500/20"
                        iconPath="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"
                        href={route('leads.index')}
                        alert={stats.leadsWithoutTask > 0}
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Leads recientes */}
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="p-5 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h3 className="text-base font-bold text-gray-900">Leads recientes</h3>
                                <p className="text-xs text-gray-400 mt-0.5">Los últimos que entraron al embudo</p>
                            </div>
                            <Link href={route('leads.index')} className="text-xs font-semibold text-emerald-600 hover:text-emerald-700">
                                Ver todos →
                            </Link>
                        </div>
                        <ul className="divide-y divide-gray-50">
                            {recentLeads.map((lead) => (
                                <li key={lead.id}>
                                    <Link href={route('leads.show', lead.id)} className="flex items-center justify-between px-5 py-3.5 hover:bg-gray-50 transition-colors">
                                        <div className="flex items-center gap-3 min-w-0">
                                            <span className="w-2.5 h-2.5 rounded-full shrink-0" style={{ backgroundColor: lead.stage?.color ?? '#94a3b8' }} />
                                            <div className="min-w-0">
                                                <p className="font-semibold text-gray-900 text-sm truncate">{lead.title}</p>
                                                <p className="text-xs text-gray-400 truncate">
                                                    {lead.contact?.name || 'Sin contacto'} · {lead.stage?.name}
                                                </p>
                                            </div>
                                        </div>
                                        <span className="text-sm font-bold text-gray-900 tabular-nums shrink-0 ml-3">
                                            {money(lead.value, lead.currency || currency)}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                            {recentLeads.length === 0 && (
                                <li className="px-5 py-10 text-center text-sm text-gray-400">
                                    Sin leads todavía — crea el primero o conecta WhatsApp
                                </li>
                            )}
                        </ul>
                    </div>

                    {/* Mis tareas */}
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div className="p-5 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h3 className="text-base font-bold text-gray-900">Mis próximas tareas</h3>
                                <p className="text-xs text-gray-400 mt-0.5">Ordenadas por vencimiento</p>
                            </div>
                            <Link href={route('tasks.index')} className="text-xs font-semibold text-emerald-600 hover:text-emerald-700">
                                Ver agenda →
                            </Link>
                        </div>
                        <ul className="divide-y divide-gray-50">
                            {myTasks.map((task) => {
                                const overdue = !task.completed_at && new Date(task.due_at) < new Date();
                                return (
                                    <li key={task.id} className="flex items-center justify-between px-5 py-3.5">
                                        <div className="min-w-0">
                                            <p className="font-medium text-gray-900 text-sm truncate">{task.text}</p>
                                            <p className="text-xs text-gray-400 truncate">
                                                {task.lead ? task.lead.title : 'Sin lead'}
                                            </p>
                                        </div>
                                        <span className={`text-xs font-semibold shrink-0 ml-3 tabular-nums ${overdue ? 'text-red-500' : 'text-gray-400'}`}>
                                            {new Date(task.due_at).toLocaleString('es', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
                                        </span>
                                    </li>
                                );
                            })}
                            {myTasks.length === 0 && (
                                <li className="px-5 py-10 text-center text-sm text-gray-400">Sin tareas pendientes 🎉</li>
                            )}
                        </ul>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
