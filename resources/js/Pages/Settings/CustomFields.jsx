import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';

const ENTITY_META = {
    lead: { label: 'Leads', gradient: 'from-[#045474] to-[#1c486c]', icon: 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22' },
    contact: { label: 'Contactos', gradient: 'from-emerald-500 to-teal-600', icon: 'M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z' },
    company: { label: 'Empresas', gradient: 'from-purple-500 to-violet-600', icon: 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18' },
};

const TYPE_LABELS = { text: 'Texto', number: 'Número', date: 'Fecha', select: 'Lista' };

function EntityCard({ entity, fields }) {
    const meta = ENTITY_META[entity];
    const { data, setData, post, processing, errors, reset } = useForm({
        entity,
        name: '',
        field_type: 'text',
        options: [],
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('custom-fields.store'), { preserveScroll: true, onSuccess: () => reset('name', 'options') });
    };

    return (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div className="p-5 border-b border-gray-100 flex items-center gap-3">
                <div className={`w-10 h-10 rounded-xl bg-gradient-to-br ${meta.gradient} flex items-center justify-center text-white shadow-lg`}>
                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path strokeLinecap="round" strokeLinejoin="round" d={meta.icon} />
                    </svg>
                </div>
                <div>
                    <h3 className="text-base font-bold text-gray-900">{meta.label}</h3>
                    <p className="text-xs text-gray-400 mt-0.5">{fields.length} campo{fields.length !== 1 ? 's' : ''} personalizado{fields.length !== 1 ? 's' : ''}</p>
                </div>
            </div>

            <ul className="divide-y divide-gray-50">
                {fields.map((field) => (
                    <li key={field.id} className="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors group">
                        <div className="flex items-center gap-3">
                            <span className="px-2 py-0.5 rounded-md bg-gray-100 text-[10px] font-bold uppercase text-gray-500">{TYPE_LABELS[field.field_type]}</span>
                            <span className="text-sm font-semibold text-gray-900">{field.name}</span>
                            {field.field_type === 'select' && (
                                <span className="text-xs text-gray-400">{(field.options ?? []).join(' / ')}</span>
                            )}
                        </div>
                        <button
                            onClick={() => { if (confirm('¿Eliminar este campo y todos sus valores?')) router.delete(route('custom-fields.destroy', field.id), { preserveScroll: true }); }}
                            className="p-1.5 text-gray-300 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors opacity-0 group-hover:opacity-100"
                        >
                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166" /></svg>
                        </button>
                    </li>
                ))}
                {fields.length === 0 && <li className="px-5 py-4 text-sm text-gray-400 text-center">Sin campos</li>}
            </ul>

            <form onSubmit={submit} className="p-4 border-t border-gray-100 bg-gray-50/50 space-y-2">
                <div className="flex gap-2">
                    <input
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        placeholder="Nombre del campo"
                        className="flex-1 px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 transition-all"
                    />
                    <select
                        value={data.field_type}
                        onChange={(e) => setData('field_type', e.target.value)}
                        className="px-2.5 py-2 border border-gray-200 rounded-xl text-sm bg-white"
                    >
                        {Object.entries(TYPE_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                    </select>
                </div>
                {data.field_type === 'select' && (
                    <input
                        value={data.options.join(', ')}
                        onChange={(e) => setData('options', e.target.value.split(',').map((o) => o.trim()).filter(Boolean))}
                        placeholder="Opciones separadas por coma — ej. Frío, Tibio, Caliente"
                        className="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 transition-all"
                    />
                )}
                {errors.name && <p className="text-xs text-red-500 font-medium">{errors.name}</p>}
                <button type="submit" disabled={processing} className="w-full px-3 py-2 text-xs font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50 transition-all shadow-lg shadow-emerald-500/20">
                    Añadir campo
                </button>
            </form>
        </div>
    );
}

export default function CustomFields({ fields }) {
    const { flash } = usePage().props;

    return (
        <AuthenticatedLayout>
            <Head title="Campos personalizados" />

            <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">
                <div>
                    <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">Campos personalizados</h1>
                    <p className="text-sm text-gray-400 mt-1">Añade los datos que tu negocio necesita en leads, contactos y empresas</p>
                </div>

                {flash?.success && <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 shadow-sm">{flash.success}</div>}

                <div className="grid gap-5 lg:grid-cols-3">
                    <EntityCard entity="lead" fields={fields.lead} />
                    <EntityCard entity="contact" fields={fields.contact} />
                    <EntityCard entity="company" fields={fields.company} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
