import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

const ROLE_LABELS = { admin: 'Admin', agent: 'Agente', viewer: 'Solo lectura' };
const inputClass = 'w-full px-3.5 py-2.5 border border-gray-200 rounded-xl text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 focus:bg-white transition-all';

export default function Accept({ invalid, token, accountName, role, isLoggedIn }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    if (invalid) {
        return (
            <GuestLayout>
                <Head title="Invitación" />
                <p className="text-center text-sm text-gray-600">
                    Esta invitación no es válida o ya expiró. Pide un link nuevo a quien te invitó.
                </p>
            </GuestLayout>
        );
    }

    const submit = (e) => {
        e.preventDefault();
        post(route('invitations.redeem', token));
    };

    return (
        <GuestLayout>
            <Head title="Unirse al equipo" />

            <div className="mb-5 text-center">
                <h1 className="text-lg font-bold text-gray-900">
                    Te invitaron a <span className="text-emerald-600">{accountName}</span>
                </h1>
                <p className="text-sm text-gray-400 mt-1">Rol: {ROLE_LABELS[role] ?? role}</p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                {!isLoggedIn && (
                    <>
                        {[['name', 'Tu nombre', 'text'], ['email', 'Email', 'email'], ['password', 'Contraseña', 'password'], ['password_confirmation', 'Confirmar contraseña', 'password']].map(([field, label, type]) => (
                            <div key={field}>
                                <label className="block text-sm font-semibold text-gray-700 mb-1.5">{label}</label>
                                <input type={type} value={data[field]} onChange={(e) => setData(field, e.target.value)} required className={inputClass} />
                                {errors[field] && <p className="mt-1 text-xs text-red-500 font-medium">{errors[field]}</p>}
                            </div>
                        ))}
                    </>
                )}

                {errors.invite && <p className="text-xs text-red-500 font-medium">{errors.invite}</p>}

                <button type="submit" disabled={processing} className="w-full px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50 transition-all shadow-lg shadow-emerald-500/20">
                    {isLoggedIn ? `Unirme a ${accountName}` : 'Crear cuenta y unirme'}
                </button>
            </form>
        </GuestLayout>
    );
}
