import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const inputClass = 'w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 focus:bg-white transition-all';

function CompanyModal({ open, onClose, company, allTags, customFields }) {
    const isEdit = !!company;
    const { data, setData, post, patch, processing, errors, reset, clearErrors } = useForm({
        name: company?.name ?? '',
        phone: company?.phone ?? '',
        email: company?.email ?? '',
        website: company?.website ?? '',
        address: company?.address ?? '',
        tag_ids: company?.tags?.map((t) => t.id) ?? [],
        custom_values: {},
    });

    const toggleTag = (id) =>
        setData('tag_ids', data.tag_ids.includes(id) ? data.tag_ids.filter((t) => t !== id) : [...data.tag_ids, id]);

    const close = () => { reset(); clearErrors(); onClose(); };
    const submit = (e) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: close };
        isEdit ? patch(route('companies.update', company.id), opts) : post(route('companies.store'), opts);
    };

    return (
        <Modal show={open} onClose={close}>
            <form onSubmit={submit}>
                <div className="px-6 pt-6 pb-4 border-b border-gray-100 flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center text-white shadow-lg shadow-purple-500/20">
                        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18" /></svg>
                    </div>
                    <h2 className="text-base font-bold text-gray-900">{isEdit ? 'Editar empresa' : 'Nueva empresa'}</h2>
                </div>
                <div className="px-6 py-5 space-y-4">
                    {[['name', 'Nombre *', true], ['phone', 'Teléfono', false], ['email', 'Email', false], ['website', 'Sitio web', false], ['address', 'Dirección', false]].map(([field, label, required]) => (
                        <div key={field}>
                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">{label}</label>
                            <input value={data[field]} onChange={(e) => setData(field, e.target.value)} required={required} className={inputClass} />
                            {errors[field] && <p className="mt-1 text-xs text-red-500 font-medium">{errors[field]}</p>}
                        </div>
                    ))}

                    {allTags.length > 0 && (
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">Etiquetas</label>
                            <div className="flex flex-wrap gap-1.5">
                                {allTags.map((tag) => {
                                    const active = data.tag_ids.includes(tag.id);
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
                            </div>
                        </div>
                    )}

                    {customFields.map((field) => (
                        <div key={field.id}>
                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">{field.name}</label>
                            {field.field_type === 'select' ? (
                                <select
                                    value={data.custom_values[field.id] ?? ''}
                                    onChange={(e) => setData('custom_values', { ...data.custom_values, [field.id]: e.target.value })}
                                    className={inputClass}
                                >
                                    <option value="">—</option>
                                    {(field.options ?? []).map((opt) => <option key={opt} value={opt}>{opt}</option>)}
                                </select>
                            ) : (
                                <input
                                    type={field.field_type === 'number' ? 'number' : field.field_type === 'date' ? 'date' : 'text'}
                                    value={data.custom_values[field.id] ?? ''}
                                    onChange={(e) => setData('custom_values', { ...data.custom_values, [field.id]: e.target.value })}
                                    className={inputClass}
                                />
                            )}
                        </div>
                    ))}
                </div>
                <div className="px-6 py-4 bg-gray-50/80 border-t border-gray-100 rounded-b-2xl flex justify-end gap-3">
                    <button type="button" onClick={close} className="px-4 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all shadow-sm">Cancelar</button>
                    <button type="submit" disabled={processing} className="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50 transition-all shadow-lg shadow-emerald-500/20">
                        {isEdit ? 'Guardar' : 'Crear'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

export default function Index({ companies, allTags, customFields, filters }) {
    const { flash } = usePage().props;
    const [modal, setModal] = useState(null);
    const [search, setSearch] = useState(filters.q ?? '');

    const applySearch = () => router.get(route('companies.index'), { q: search || undefined }, { preserveState: true, replace: true });

    return (
        <AuthenticatedLayout>
            <Head title="Empresas" />

            <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">Empresas</h1>
                        <p className="text-sm text-gray-400 mt-1">Cuentas B2B — cada empresa agrupa contactos y leads</p>
                    </div>
                    <button onClick={() => setModal('create')} className="px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl hover:from-emerald-500 hover:to-teal-500 transition-all shadow-lg shadow-emerald-500/20 flex items-center gap-1.5 w-fit">
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Nueva empresa
                    </button>
                </div>

                {flash?.success && <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 shadow-sm">{flash.success}</div>}

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div className="p-5 border-b border-gray-100 flex gap-2">
                        <input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applySearch()}
                            placeholder="Buscar por nombre…"
                            className="flex-1 max-w-md px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:bg-white transition-all"
                        />
                        <button onClick={applySearch} className="px-4 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-all shadow-sm">Buscar</button>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-gray-50/80">
                                    <th className="text-left px-5 py-3.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Nombre</th>
                                    <th className="text-left px-5 py-3.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider hidden md:table-cell">Teléfono</th>
                                    <th className="text-left px-5 py-3.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider hidden md:table-cell">Web</th>
                                    <th className="text-right px-5 py-3.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Contactos</th>
                                    <th className="text-right px-5 py-3.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Leads abiertos</th>
                                    <th className="text-right px-5 py-3.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Acción</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {companies.data.map((company) => (
                                    <tr key={company.id} className="hover:bg-gray-50 transition-colors group">
                                        <td className="px-5 py-4">
                                            <div className="flex items-center gap-3">
                                                <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center text-white text-xs font-bold shadow-sm">
                                                    {company.name.charAt(0).toUpperCase()}
                                                </div>
                                                <span className="font-semibold text-gray-900">{company.name}</span>
                                            </div>
                                        </td>
                                        <td className="px-5 py-4 text-gray-500 tabular-nums hidden md:table-cell">{company.phone || <span className="italic text-gray-300">—</span>}</td>
                                        <td className="px-5 py-4 text-gray-500 hidden md:table-cell">{company.website || <span className="italic text-gray-300">—</span>}</td>
                                        <td className="px-5 py-4 text-right font-bold tabular-nums text-gray-700">{company.contacts_count}</td>
                                        <td className="px-5 py-4 text-right font-bold tabular-nums text-gray-700">{company.open_leads_count}</td>
                                        <td className="px-5 py-4 text-right">
                                            <div className="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button onClick={() => setModal(company)} className="p-2 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="Editar">
                                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                                                </button>
                                                <button
                                                    onClick={() => { if (confirm('¿Eliminar esta empresa?')) router.delete(route('companies.destroy', company.id), { preserveScroll: true }); }}
                                                    className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Eliminar"
                                                >
                                                    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}><path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166" /></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {companies.data.length === 0 && (
                                    <tr><td colSpan={6} className="px-5 py-14 text-center text-sm text-gray-400">Sin empresas todavía</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    {(companies.prev_page_url || companies.next_page_url) && (
                        <div className="px-5 py-4 bg-gray-50/80 border-t border-gray-100 flex justify-end gap-3 text-sm">
                            {companies.prev_page_url && <Link href={companies.prev_page_url} className="text-emerald-600 font-medium">← Anterior</Link>}
                            {companies.next_page_url && <Link href={companies.next_page_url} className="text-emerald-600 font-medium">Siguiente →</Link>}
                        </div>
                    )}
                </div>
            </div>

            <CompanyModal
                key={modal && typeof modal === 'object' ? modal.id : String(modal)}
                open={modal === 'create' || (modal && typeof modal === 'object')}
                onClose={() => setModal(null)}
                company={typeof modal === 'object' ? modal : null}
                allTags={allTags}
                customFields={customFields}
            />
        </AuthenticatedLayout>
    );
}
