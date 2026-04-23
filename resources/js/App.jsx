import axios from 'axios';
import dayjs from 'dayjs';
import clsx from 'clsx';
import { useEffect, useMemo, useState } from 'react';
import { Navigate, Route, Routes, Link, useLocation, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

const adminNav = [
    { to: '/admin/dashboard', label: 'Dashboard' },
    { to: '/admin/users', label: 'Users' },
    { to: '/admin/projects', label: 'Projects' },
    { to: '/admin/allocations', label: 'Allocations' },
    { to: '/admin/reports', label: 'Reports' },
    { to: '/admin/notifications', label: 'Notifications' },
    { to: '/admin/activity-logs', label: 'Activity Logs' },
];

const employeeNav = [
    { to: '/app/dashboard', label: 'My LOE' },
    { to: '/app/notifications', label: 'Notifications' },
];

export default function App() {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        axios.get('/api/auth/me')
            .then((response) => setUser(response.data.user))
            .catch(() => setUser(null))
            .finally(() => setLoading(false));
    }, []);

    if (loading) {
        return <div className="flex min-h-screen items-center justify-center text-slate-200">Loading LOE Tracker...</div>;
    }

    return (
        <Routes>
            <Route path="/" element={<HomeRedirect user={user} />} />
            <Route path="/login" element={<LoginPage role="employee" user={user} onAuth={setUser} />} />
            <Route path="/forgot-password" element={<ForgotPasswordPage role="employee" />} />
            <Route path="/reset-password/:token" element={<ResetPasswordPage role="employee" />} />
            <Route path="/admin/login" element={<LoginPage role="admin" user={user} onAuth={setUser} />} />
            <Route path="/admin/forgot-password" element={<ForgotPasswordPage role="admin" />} />
            <Route path="/admin/reset-password/:token" element={<ResetPasswordPage role="admin" />} />
            <Route path="/app/*" element={<ProtectedArea user={user} role="employee"><EmployeeShell user={user} setUser={setUser} /></ProtectedArea>} />
            <Route path="/admin/*" element={<ProtectedArea user={user} role="admin"><AdminShell user={user} setUser={setUser} /></ProtectedArea>} />
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}

function HomeRedirect({ user }) {
    if (!user) return <Navigate to="/login" replace />;
    return <Navigate to={user.roles.includes('admin') ? '/admin/dashboard' : '/app/dashboard'} replace />;
}

function ProtectedArea({ user, role, children }) {
    if (!user) {
        return <Navigate to={role === 'admin' ? '/admin/login' : '/login'} replace />;
    }

    if (!user.roles.includes(role)) {
        return <Navigate to={user.roles.includes('admin') ? '/admin/dashboard' : '/app/dashboard'} replace />;
    }

    return children;
}

function LoginPage({ role, user, onAuth }) {
    const navigate = useNavigate();
    const [form, setForm] = useState({ email: '', password: '', remember: true });
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (user?.roles.includes(role)) {
            navigate(role === 'admin' ? '/admin/dashboard' : '/app/dashboard', { replace: true });
        }
    }, [navigate, role, user]);

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setError('');

        try {
            await axios.get('/sanctum/csrf-cookie');
            const response = await axios.post(`/api/auth/${role}/login`, form);
            onAuth(response.data.user);
            navigate(response.data.redirect_to, { replace: true });
        } catch (requestError) {
            const response = requestError.response;
            if (response?.status === 409 && response.data.redirect_to) {
                onAuth(response.data.user);
                navigate(response.data.redirect_to, { replace: true });
                return;
            }

            setError(response?.data?.message ?? 'Unable to login right now.');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="flex min-h-screen items-center justify-center px-4 py-10">
            <div className="grid w-full max-w-6xl gap-8 lg:grid-cols-[1.1fr_0.9fr]">
                <section className="glass-panel hidden rounded-[2rem] p-10 lg:block">
                    <div className="max-w-xl space-y-6">
                        <span className="inline-flex rounded-full bg-white/10 px-4 py-2 text-xs uppercase tracking-[0.3em] text-amber-200">
                            Level of Effort Tracker
                        </span>
                        <h1 className="text-5xl font-semibold leading-tight text-white">
                            Track capacity, allocations, and monthly effort with one clean workflow.
                        </h1>
                        <p className="text-lg text-slate-300">
                            Separate employee and admin experiences, thoughtful dashboards, and exports for monthly reviews built into the same Laravel and React app.
                        </p>
                    </div>
                </section>

                <section className="glass-panel rounded-[2rem] p-8 md:p-10">
                    <div className="mb-8">
                        <p className="text-sm uppercase tracking-[0.25em] text-teal-200">
                            {role === 'admin' ? 'Admin Area' : 'Employee Area'}
                        </p>
                        <h2 className="mt-3 text-3xl font-semibold text-white">
                            {role === 'admin' ? 'Admin sign in' : 'Employee sign in'}
                        </h2>
                    </div>

                    <form className="space-y-4" onSubmit={submit}>
                        <input className="field" placeholder="Email address" type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} />
                        <input className="field" placeholder="Password" type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} />
                        <label className="flex items-center gap-3 text-sm text-slate-300">
                            <input type="checkbox" checked={form.remember} onChange={(event) => setForm({ ...form, remember: event.target.checked })} />
                            Keep me signed in
                        </label>
                        {error ? <p className="rounded-2xl bg-rose-500/15 px-4 py-3 text-sm text-rose-200">{error}</p> : null}
                        <button className="btn btn-primary w-full" disabled={submitting} type="submit">
                            {submitting ? 'Signing in...' : 'Sign in'}
                        </button>
                    </form>

                    <div className="mt-4 flex items-center justify-between gap-3 text-sm">
                        <Link className="text-teal-200 hover:text-teal-100" to={role === 'admin' ? '/admin/forgot-password' : '/forgot-password'}>
                            Forgot password?
                        </Link>
                    </div>

                    <p className="mt-6 text-sm text-slate-400">
                        Switch area:
                        {' '}
                        <Link className="text-amber-300 hover:text-amber-200" to={role === 'admin' ? '/login' : '/admin/login'}>
                            {role === 'admin' ? 'Employee login' : 'Admin login'}
                        </Link>
                    </p>
                </section>
            </div>
        </div>
    );
}

function ForgotPasswordPage({ role }) {
    const [email, setEmail] = useState('');
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setMessage('');
        setError('');

        try {
            await axios.post(`/api/auth/${role}/forgot-password`, { email });
            setMessage('If the account exists in this area, a password reset link has been sent.');
        } catch (requestError) {
            setError(requestError.response?.data?.message ?? 'Unable to send reset link right now.');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AuthCard
            eyebrow={role === 'admin' ? 'Admin Password Reset' : 'Employee Password Reset'}
            title="Forgot your password?"
            subtitle="Enter your email address and we will send you a reset link for this area."
            footer={<Link className="text-amber-300 hover:text-amber-200" to={role === 'admin' ? '/admin/login' : '/login'}>Back to sign in</Link>}
        >
            <form className="space-y-4" onSubmit={submit}>
                <input className="field" placeholder="Email address" type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
                {message ? <p className="rounded-2xl bg-teal-500/12 px-4 py-3 text-sm text-teal-100">{message}</p> : null}
                {error ? <p className="rounded-2xl bg-rose-500/15 px-4 py-3 text-sm text-rose-200">{error}</p> : null}
                <button className="btn btn-primary w-full" disabled={submitting} type="submit">
                    {submitting ? 'Sending link...' : 'Send reset link'}
                </button>
            </form>
        </AuthCard>
    );
}

function ResetPasswordPage({ role }) {
    const { token } = useParams();
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const [form, setForm] = useState({
        email: searchParams.get('email') ?? '',
        password: '',
        password_confirmation: '',
    });
    const [message, setMessage] = useState('');
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setMessage('');
        setError('');

        try {
            const response = await axios.post(`/api/auth/${role}/reset-password`, {
                ...form,
                token,
            });
            setMessage(response.data.message);
            window.setTimeout(() => {
                navigate(role === 'admin' ? '/admin/login' : '/login', { replace: true });
            }, 1200);
        } catch (requestError) {
            setError(requestError.response?.data?.message ?? 'Unable to reset password right now.');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AuthCard
            eyebrow={role === 'admin' ? 'Admin Password Reset' : 'Employee Password Reset'}
            title="Set a new password"
            subtitle="Choose a strong password for your account in this area."
            footer={<Link className="text-amber-300 hover:text-amber-200" to={role === 'admin' ? '/admin/login' : '/login'}>Back to sign in</Link>}
        >
            <form className="space-y-4" onSubmit={submit}>
                <input className="field" placeholder="Email address" type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} />
                <input className="field" placeholder="New password" type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} />
                <input className="field" placeholder="Confirm new password" type="password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} />
                {message ? <p className="rounded-2xl bg-teal-500/12 px-4 py-3 text-sm text-teal-100">{message}</p> : null}
                {error ? <p className="rounded-2xl bg-rose-500/15 px-4 py-3 text-sm text-rose-200">{error}</p> : null}
                <button className="btn btn-primary w-full" disabled={submitting} type="submit">
                    {submitting ? 'Resetting password...' : 'Reset password'}
                </button>
            </form>
        </AuthCard>
    );
}

function AuthCard({ eyebrow, title, subtitle, children, footer }) {
    return (
        <div className="flex min-h-screen items-center justify-center px-4 py-10">
            <div className="glass-panel w-full max-w-xl rounded-[2rem] p-8 md:p-10">
                <p className="text-sm uppercase tracking-[0.25em] text-teal-200">{eyebrow}</p>
                <h2 className="mt-3 text-3xl font-semibold text-white">{title}</h2>
                <p className="mt-3 text-slate-300">{subtitle}</p>
                <div className="mt-8">{children}</div>
                <div className="mt-6 text-sm text-slate-400">{footer}</div>
            </div>
        </div>
    );
}

function EmployeeShell({ user, setUser }) {
    return (
        <Shell title="Employee Workspace" user={user} navItems={employeeNav} accent="teal" setUser={setUser}>
            <Routes>
                <Route path="dashboard" element={<EmployeeDashboardPage user={user} />} />
                <Route path="notifications" element={<NotificationsPage role="employee" />} />
                <Route path="*" element={<Navigate to="dashboard" replace />} />
            </Routes>
        </Shell>
    );
}

function AdminShell({ user, setUser }) {
    return (
        <Shell title="Admin Command Center" user={user} navItems={adminNav} accent="amber" setUser={setUser}>
            <Routes>
                <Route path="dashboard" element={<AdminDashboardPage />} />
                <Route path="users" element={<AdminUsersPage />} />
                <Route path="projects" element={<AdminProjectsPage />} />
                <Route path="allocations" element={<AdminAllocationsPage />} />
                <Route path="reports" element={<AdminReportsPage />} />
                <Route path="notifications" element={<NotificationsPage role="admin" />} />
                <Route path="activity-logs" element={<AdminActivityLogsPage />} />
                <Route path="*" element={<Navigate to="dashboard" replace />} />
            </Routes>
        </Shell>
    );
}

function Shell({ title, user, navItems, accent, setUser, children }) {
    const location = useLocation();
    const navigate = useNavigate();
    const [unreadCount, setUnreadCount] = useState(0);

    useEffect(() => {
        axios.get('/api/notifications')
            .then((response) => setUnreadCount(response.data.unread_count ?? 0))
            .catch(() => setUnreadCount(0));
    }, [location.pathname]);

    const logout = async () => {
        await axios.post('/api/auth/logout');
        setUser(null);
        navigate(location.pathname.startsWith('/admin') ? '/admin/login' : '/login', { replace: true });
    };

    return (
        <div className="min-h-screen px-4 py-6 md:px-6">
            <div className="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[260px_1fr]">
                <aside className="glass-panel rounded-[2rem] p-6">
                    <div className="mb-10">
                        <p className={clsx('text-xs uppercase tracking-[0.3em]', accent === 'amber' ? 'text-amber-200' : 'text-teal-200')}>
                            {title}
                        </p>
                        <h1 className="mt-3 text-2xl font-semibold text-white">{user.name}</h1>
                        <p className="text-sm text-slate-400">{user.email}</p>
                    </div>
                    <nav className="space-y-2">
                        {navItems.map((item) => (
                            <Link
                                  className={clsx(
                                      'flex items-center justify-between rounded-2xl px-4 py-3 text-sm transition',
                                      location.pathname === item.to ? 'bg-white/12 text-white' : 'text-slate-300 hover:bg-white/6 hover:text-white',
                                  )}
                                key={item.to}
                                to={item.to}
                              >
                                  <span>{item.label}</span>
                                  {item.label === 'Notifications' && unreadCount > 0 ? (
                                      <span className="rounded-full bg-amber-400/20 px-2 py-0.5 text-xs text-amber-200">
                                          {unreadCount}
                                      </span>
                                  ) : null}
                              </Link>
                          ))}
                      </nav>
                    <button className="btn btn-secondary mt-10 w-full" onClick={logout} type="button">Logout</button>
                </aside>
                <main className="space-y-6">{children}</main>
            </div>
        </div>
    );
}

function EmployeeDashboardPage({ user }) {
    const [data, setData] = useState(null);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');
    const now = dayjs();
    const [form, setForm] = useState({ month: now.month() + 1, year: now.year(), entries: [{ project_id: '', percentage: '' }] });
    const [isModalOpen, setIsModalOpen] = useState(false);

    const load = async () => {
        const response = await axios.get('/api/employee/dashboard');
        setData(response.data);
    };

    useEffect(() => { load(); }, []);

    const matchedReport = useMemo(() => data?.reports.find((report) => report.month === Number(form.month) && report.year === Number(form.year)), [data, form]);
    const isLocked = matchedReport?.is_locked ?? false;
    const totalPercentage = form.entries.reduce((sum, entry) => sum + (Number(entry.percentage) || 0), 0).toFixed(2);

    const openCreateModal = () => {
        setMessage('');
        setForm({
            month: data?.current_period.month ?? now.month() + 1,
            year: data?.current_period.year ?? now.year(),
            entries: [{ project_id: '', percentage: '' }],
        });
        setIsModalOpen(true);
    };

    const openEditModal = (report) => {
        setMessage('');
        setForm({
            month: report.month,
            year: report.year,
            entries: report.entries.length
                ? report.entries.map((entry) => ({ project_id: entry.project_id, percentage: entry.percentage }))
                : [{ project_id: '', percentage: '' }],
        });
        setIsModalOpen(true);
    };

    const closeModal = () => {
        if (!saving) {
            setIsModalOpen(false);
        }
    };

    const updateEntry = (index, key, value) => {
        setForm((current) => ({
            ...current,
            entries: current.entries.map((entry, entryIndex) => entryIndex === index ? { ...entry, [key]: value } : entry),
        }));
    };

    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setMessage('');

        try {
            const payload = {
                month: Number(form.month),
                year: Number(form.year),
                entries: form.entries.map((entry) => ({
                    project_id: entry.project_id,
                    percentage: Number(entry.percentage),
                })),
            };

            if (matchedReport) {
                await axios.put(`/api/employee/reports/${matchedReport.id}`, { entries: payload.entries });
                setMessage('LOE updated successfully.');
            } else {
                await axios.post('/api/employee/reports', payload);
                setMessage('LOE submitted successfully.');
            }

            await load();
            setIsModalOpen(false);
        } catch (error) {
            setMessage(error.response?.data?.message ?? 'Unable to save LOE.');
        } finally {
            setSaving(false);
        }
    };

    if (!data) return <PageLoader label="Loading employee dashboard..." />;

    return (
        <div className="space-y-6">
            <section className="glass-panel rounded-[2rem] p-8">
                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm uppercase tracking-[0.3em] text-teal-200">Monthly effort</p>
                        <h2 className="mt-3 text-3xl font-semibold text-white">Submit or review your LOE</h2>
                        <p className="mt-2 text-slate-300">Deadline for your current month is {dayjs(data.current_period.deadline).format('DD MMM YYYY, hh:mm A')} ({user.timezone}).</p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <button className="btn btn-primary" onClick={openCreateModal} type="button">Add LOE</button>
                        <a className="btn btn-secondary" href="/api/employee/reports/export?format=pdf">Export PDF</a>
                        <a className="btn btn-secondary" href="/api/employee/reports/export?format=xlsx">Export Excel</a>
                    </div>
                </div>
                {message ? <p className="mt-5 rounded-2xl bg-white/8 px-4 py-3 text-sm text-slate-200">{message}</p> : null}
            </section>

            <div className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <section className="glass-panel rounded-[2rem] p-8">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h3 className="text-xl font-semibold text-white">Latest submission</h3>
                            <p className="mt-2 text-slate-300">Open the LOE modal whenever you need to add a new month or revise an unlocked one.</p>
                        </div>
                        {data.current_report && !data.current_report.is_locked ? (
                            <button className="btn btn-secondary" onClick={() => openEditModal(data.current_report)} type="button">Edit current month</button>
                        ) : null}
                    </div>

                    {data.current_report ? (
                        <article className="mt-6 rounded-3xl border border-white/10 bg-slate-950/25 p-5">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="text-sm uppercase tracking-[0.2em] text-slate-400">{dayjs(`${data.current_report.year}-${data.current_report.month}-01`).format('MMMM YYYY')}</p>
                                    <p className="mt-3 text-3xl font-semibold text-white">{data.current_report.total_percentage}%</p>
                                </div>
                                <div className="flex flex-wrap gap-3">
                                    <span className={clsx('rounded-full px-3 py-1 text-xs uppercase tracking-[0.2em]', data.current_report.is_locked ? 'bg-amber-500/15 text-amber-200' : 'bg-teal-500/15 text-teal-200')}>
                                        {data.current_report.is_locked ? 'Read only' : 'Editable'}
                                    </span>
                                    {!data.current_report.is_locked ? (
                                        <button className="btn btn-secondary py-2" onClick={() => openEditModal(data.current_report)} type="button">Edit</button>
                                    ) : null}
                                </div>
                            </div>
                            <ul className="mt-4 space-y-2 text-sm text-slate-300">
                                {data.current_report.entries.map((entry) => (
                                    <li className="flex items-center justify-between" key={entry.id}>
                                        <span>{entry.project_name}</span>
                                        <span>{entry.percentage}%</span>
                                    </li>
                                ))}
                            </ul>
                        </article>
                    ) : (
                        <div className="mt-6 rounded-3xl border border-dashed border-white/12 bg-slate-950/20 p-6 text-slate-300">
                            No LOE submitted for the current month yet.
                        </div>
                    )}
                </section>

                <section className="glass-panel rounded-[2rem] p-8">
                    <h3 className="text-xl font-semibold text-white">Current allocations</h3>
                    <div className="mt-5 space-y-3">
                        {data.allocations.map((allocation) => (
                            <div className="rounded-2xl border border-white/10 bg-slate-950/25 p-4" key={allocation.id}>
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <p className="font-medium text-white">{allocation.project_name}</p>
                                    </div>
                                    <span className="text-amber-300">{allocation.percentage}%</span>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </div>

            <section className="glass-panel rounded-[2rem] p-8">
                <h3 className="text-xl font-semibold text-white">Previous submissions</h3>
                <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {data.reports.map((report) => (
                        <article className="rounded-3xl border border-white/10 bg-slate-950/25 p-5" key={report.id}>
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm uppercase tracking-[0.2em] text-slate-400">{dayjs(`${report.year}-${report.month}-01`).format('MMMM YYYY')}</p>
                                    <p className="mt-3 text-3xl font-semibold text-white">{report.total_percentage}%</p>
                                </div>
                                {!report.is_locked ? <button className="btn btn-secondary py-2" onClick={() => openEditModal(report)} type="button">Edit</button> : null}
                            </div>
                            <ul className="mt-4 space-y-2 text-sm text-slate-300">
                                {report.entries.map((entry) => (
                                    <li className="flex items-center justify-between" key={entry.id}>
                                        <span>{entry.project_name}</span>
                                        <span>{entry.percentage}%</span>
                                    </li>
                                ))}
                            </ul>
                        </article>
                    ))}
                </div>
            </section>

            <Modal
                isOpen={isModalOpen}
                title={matchedReport ? `Edit LOE for ${dayjs(`${form.year}-${form.month}-01`).format('MMMM YYYY')}` : 'Add monthly LOE'}
                onClose={closeModal}
            >
                <form className="space-y-4" onSubmit={submit}>
                    <div className="grid gap-4 md:grid-cols-2">
                        <input className="field" disabled={Boolean(matchedReport)} min="1" max="12" type="number" value={form.month} onChange={(event) => setForm({ ...form, month: event.target.value })} />
                        <input className="field" disabled={Boolean(matchedReport)} min="2020" max="2100" type="number" value={form.year} onChange={(event) => setForm({ ...form, year: event.target.value })} />
                    </div>

                    {form.entries.map((entry, index) => (
                        <div className="grid gap-3 md:grid-cols-[1fr_160px_110px]" key={index}>
                            <select className="field" disabled={isLocked} value={entry.project_id} onChange={(event) => updateEntry(index, 'project_id', event.target.value)}>
                                <option value="">Select a project</option>
                                {data.projects.map((project) => (
                                    <option key={project.id} value={project.id}>{project.name}</option>
                                ))}
                            </select>
                            <input className="field" disabled={isLocked} min="0.01" max="100" placeholder="Percentage" step="0.01" type="number" value={entry.percentage} onChange={(event) => updateEntry(index, 'percentage', event.target.value)} />
                            <button className="btn btn-secondary" disabled={isLocked || form.entries.length === 1} onClick={() => setForm({ ...form, entries: form.entries.filter((_, rowIndex) => rowIndex !== index) })} type="button">Remove</button>
                        </div>
                    ))}

                    <div className="flex flex-wrap gap-3">
                        <button className="btn btn-secondary" disabled={isLocked} onClick={() => setForm({ ...form, entries: [...form.entries, { project_id: '', percentage: '' }] })} type="button">Add project</button>
                        <button className="btn btn-primary" disabled={saving || isLocked} type="submit">{saving ? 'Saving...' : matchedReport ? 'Update LOE' : 'Submit LOE'}</button>
                    </div>

                    <p className="text-sm text-slate-300">Total percentage: {totalPercentage}%</p>
                    {isLocked ? <p className="rounded-2xl bg-amber-500/10 px-4 py-3 text-sm text-amber-100">This report is read-only because the selected month has already closed.</p> : null}
                    {message && isModalOpen ? <p className="rounded-2xl bg-white/8 px-4 py-3 text-sm text-slate-200">{message}</p> : null}
                </form>
            </Modal>
        </div>
    );
}

function AdminDashboardPage() {
    const [data, setData] = useState(null);

    useEffect(() => {
        axios.get('/api/admin/dashboard').then((response) => setData(response.data));
    }, []);

    if (!data) return <PageLoader label="Loading admin dashboard..." />;

    return (
        <div className="space-y-6">
            <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                {Object.entries(data.metrics).map(([key, value]) => (
                    <div className="metric-card" key={key}>
                        <p className="text-sm uppercase tracking-[0.2em] text-slate-400">{key.replaceAll('_', ' ')}</p>
                        <p className="mt-4 text-3xl font-semibold text-white">{value}</p>
                    </div>
                ))}
            </section>
            <section className="grid gap-6 xl:grid-cols-2">
                <ChartCard title="Submission Trend">
                    <ResponsiveContainer width="100%" height={280}>
                        <AreaChart data={data.charts.submission_trend}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                            <XAxis dataKey="month" stroke="#94a3b8" />
                            <YAxis stroke="#94a3b8" />
                            <Tooltip />
                            <Area type="monotone" dataKey="reports" stroke="#2dd4bf" fill="#2dd4bf33" />
                        </AreaChart>
                    </ResponsiveContainer>
                </ChartCard>
                <ChartCard title="Engagement Distribution">
                    <ResponsiveContainer width="100%" height={280}>
                        <PieChart>
                            <Pie data={data.charts.engagement_distribution} dataKey="total" nameKey="engagement_type" outerRadius={100} fill="#f59e0b" />
                            <Tooltip />
                        </PieChart>
                    </ResponsiveContainer>
                </ChartCard>
                <ChartCard title="Allocation vs Actual">
                    <ResponsiveContainer width="100%" height={320}>
                        <BarChart data={data.charts.allocation_vs_actual}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                            <XAxis dataKey="project_name" stroke="#94a3b8" />
                            <YAxis stroke="#94a3b8" />
                            <Tooltip />
                            <Bar dataKey="allocation_total" fill="#f59e0b" />
                            <Bar dataKey="actual_total" fill="#2dd4bf" />
                        </BarChart>
                    </ResponsiveContainer>
                </ChartCard>
            </section>
        </div>
    );
}

function AdminUsersPage() {
    return <CrudPage title="Users" endpoint="/api/admin/users" formFactory={() => ({ name: '', email: '', employee_code: '', designation: '', stream: 'engineering', timezone: 'Asia/Karachi', status: true, password: '', roles: ['employee'] })} fields={[
        { key: 'name', label: 'Name' },
        { key: 'email', label: 'Email', type: 'email' },
        { key: 'employee_code', label: 'Employee Code' },
        { key: 'designation', label: 'Designation' },
        { key: 'stream', label: 'Stream', type: 'select', options: ['engineering', 'experience', 'admin'] },
        { key: 'timezone', label: 'Timezone' },
        { key: 'password', label: 'Password', type: 'password' },
        { key: 'roles', label: 'Roles (comma separated)', type: 'roles' },
    ]} />;
}

function AdminProjectsPage() {
    return <CrudPage title="Projects" endpoint="/api/admin/projects" formFactory={() => ({ name: '', engagement: '', description: '', engagement_type: 'project', status: true })} fields={[
        { key: 'name', label: 'Name' },
        { key: 'engagement', label: 'Engagement' },
        { key: 'description', label: 'Description' },
        { key: 'engagement_type', label: 'Engagement Type', type: 'select', options: ['project', 'product', 'marketing', 'admin'] },
    ]} />;
}

function AdminAllocationsPage() {
    const [allocations, setAllocations] = useState([]);
    const [users, setUsers] = useState([]);
    const [projects, setProjects] = useState([]);
    const [form, setForm] = useState({ user_id: '', project_id: '', percentage: '' });
    const [editingId, setEditingId] = useState(null);
    const [message, setMessage] = useState('');
    const [isModalOpen, setIsModalOpen] = useState(false);

    const load = async () => {
        const [allocationResponse, userResponse, projectResponse] = await Promise.all([
            axios.get('/api/admin/allocations'),
            axios.get('/api/admin/users'),
            axios.get('/api/admin/projects'),
        ]);
        setAllocations(allocationResponse.data);
        setUsers(userResponse.data);
        setProjects(projectResponse.data.filter((project) => !project.deleted_at));
    };

    useEffect(() => { load(); }, []);

    const submit = async (event) => {
        event.preventDefault();
        setMessage('');

        try {
            if (editingId) {
                await axios.put(`/api/admin/allocations/${editingId}`, { ...form, percentage: Number(form.percentage) });
            } else {
                await axios.post('/api/admin/allocations', { ...form, percentage: Number(form.percentage) });
            }
            setForm({ user_id: '', project_id: '', percentage: '' });
            setEditingId(null);
            setIsModalOpen(false);
            setMessage('Allocation saved.');
            await load();
        } catch (error) {
            setMessage(error.response?.data?.message ?? 'Unable to save allocation.');
        }
    };

    return (
        <div className="space-y-6">
            <section className="glass-panel rounded-[2rem] p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="text-2xl font-semibold text-white">Assigned allocations</h2>
                        <p className="mt-2 text-slate-300">Create and update allocations in a dedicated modal so the list stays focused.</p>
                    </div>
                    <button className="btn btn-primary" onClick={() => {
                        setEditingId(null);
                        setForm({ user_id: '', project_id: '', percentage: '' });
                        setMessage('');
                        setIsModalOpen(true);
                    }} type="button">Add allocation</button>
                </div>
                {message ? <p className="mt-5 rounded-2xl bg-white/8 px-4 py-3 text-sm text-slate-200">{message}</p> : null}
                <div className="mt-6 overflow-auto">
                    <table className="min-w-full text-left text-sm text-slate-200">
                        <thead className="text-slate-400">
                            <tr>
                                <th className="pb-3">Employee</th>
                                <th className="pb-3">Project</th>
                                <th className="pb-3">Percentage</th>
                                <th className="pb-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {allocations.map((allocation) => (
                                <tr className="border-t border-white/8" key={allocation.id}>
                                    <td className="py-3">{allocation.user?.name}</td>
                                    <td className="py-3">{allocation.project?.name}</td>
                                    <td className="py-3">{allocation.percentage}%</td>
                                    <td className="py-3">
                                        <div className="flex gap-2">
                                            <button className="btn btn-secondary py-2" onClick={() => {
                                                setEditingId(allocation.id);
                                                setForm({ user_id: allocation.user_id, project_id: allocation.project_id, percentage: allocation.percentage });
                                                setMessage('');
                                                setIsModalOpen(true);
                                            }} type="button">Edit</button>
                                            <button className="btn btn-danger py-2" onClick={async () => { await axios.delete(`/api/admin/allocations/${allocation.id}`); await load(); }} type="button">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <Modal isOpen={isModalOpen} title={editingId ? 'Edit allocation' : 'Create allocation'} onClose={() => setIsModalOpen(false)}>
                <form className="space-y-4" onSubmit={submit}>
                    <select className="field" value={form.user_id} onChange={(event) => setForm({ ...form, user_id: event.target.value })}>
                        <option value="">Select employee</option>
                        {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
                    </select>
                    <select className="field" value={form.project_id} onChange={(event) => setForm({ ...form, project_id: event.target.value })}>
                        <option value="">Select project</option>
                        {projects.map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}
                    </select>
                    <input className="field" type="number" min="0.01" max="100" step="0.01" value={form.percentage} onChange={(event) => setForm({ ...form, percentage: event.target.value })} />
                    <button className="btn btn-primary w-full" type="submit">{editingId ? 'Update allocation' : 'Create allocation'}</button>
                    {message && isModalOpen ? <p className="text-sm text-slate-300">{message}</p> : null}
                </form>
            </Modal>
        </div>
    );
}

function AdminReportsPage() {
    const [data, setData] = useState(null);

    useEffect(() => {
        axios.get('/api/admin/reports').then((response) => setData(response.data));
    }, []);

    if (!data) return <PageLoader label="Loading reports..." />;

    return (
        <div className="space-y-6">
            <section className="glass-panel rounded-[2rem] p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-2xl font-semibold text-white">Reporting center</h2>
                        <p className="mt-2 text-slate-300">Admin exports are available in branded PDF and Excel formats.</p>
                    </div>
                    <div className="flex gap-3">
                        <a className="btn btn-secondary" href="/api/admin/reports/export?type=employee-monthly&format=pdf">Monthly PDF</a>
                        <a className="btn btn-primary" href="/api/admin/reports/export?type=employee-monthly&format=xlsx">Monthly Excel</a>
                    </div>
                </div>
            </section>

            <ReportTable title="Employee Monthly" rows={flattenRows(data.employee_monthly, 'entries')} />
            <ReportTable title="Project Summary" rows={data.project_summary} />
            <ReportTable title="Missing Submissions" rows={data.missing_submissions} />
        </div>
    );
}

function AdminActivityLogsPage() {
    const [data, setData] = useState(null);
    const [filters, setFilters] = useState({ log_name: '', event: '' });

    const load = async (nextFilters = filters) => {
        const params = new URLSearchParams();
        if (nextFilters.log_name) params.set('log_name', nextFilters.log_name);
        if (nextFilters.event) params.set('event', nextFilters.event);

        const response = await axios.get(`/api/admin/activity-logs?${params.toString()}`);
        setData(response.data);
    };

    useEffect(() => { load(); }, []);

    if (!data) return <PageLoader label="Loading activity logs..." />;

    return (
        <div className="space-y-6">
            <section className="glass-panel rounded-[2rem] p-8">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end">
                    <div className="flex-1">
                        <h2 className="text-2xl font-semibold text-white">Activity logs</h2>
                        <p className="mt-2 text-slate-300">A running audit trail of changes and notable actions across admin and employee features.</p>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2">
                        <input className="field" placeholder="Filter by log name" value={filters.log_name} onChange={(event) => setFilters({ ...filters, log_name: event.target.value })} />
                        <input className="field" placeholder="Filter by event" value={filters.event} onChange={(event) => setFilters({ ...filters, event: event.target.value })} />
                    </div>
                    <button className="btn btn-primary" onClick={() => load(filters)} type="button">Apply</button>
                </div>
            </section>

            <section className="glass-panel rounded-[2rem] p-8">
                <div className="space-y-4">
                    {data.data.map((activity) => (
                        <article className="rounded-3xl border border-white/10 bg-slate-950/25 p-5" key={activity.id}>
                            <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p className="text-lg font-semibold text-white">{activity.description}</p>
                                    <p className="text-sm text-slate-400">
                                        {activity.log_name} • {activity.event || 'eventless'} • {dayjs(activity.created_at).format('DD MMM YYYY, hh:mm A')}
                                    </p>
                                </div>
                                <div className="text-sm text-slate-300">
                                    {activity.causer ? `${activity.causer.name} (${activity.causer.email})` : 'System'}
                                </div>
                            </div>
                            <div className="mt-4 grid gap-4 md:grid-cols-2">
                                <div className="rounded-2xl bg-slate-900/60 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Subject</p>
                                    <p className="mt-2 text-sm text-slate-200">
                                        {activity.subject ? `${activity.subject.type} • ${activity.subject.id}` : 'None'}
                                    </p>
                                </div>
                                <div className="rounded-2xl bg-slate-900/60 p-4">
                                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Properties</p>
                                    <pre className="mt-2 overflow-auto text-xs text-slate-200">{JSON.stringify(activity.properties, null, 2)}</pre>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            </section>
        </div>
    );
}

function CrudPage({ title, endpoint, formFactory, fields }) {
    const [rows, setRows] = useState([]);
    const [form, setForm] = useState(formFactory());
    const [editingId, setEditingId] = useState(null);
    const [message, setMessage] = useState('');
    const [isModalOpen, setIsModalOpen] = useState(false);
    const singularTitle = singularize(title);

    const load = async () => {
        const response = await axios.get(endpoint);
        setRows(response.data);
    };

    useEffect(() => { load(); }, [endpoint]);

    const submit = async (event) => {
        event.preventDefault();
        setMessage('');

        const payload = {
            ...form,
            roles: typeof form.roles === 'string' ? form.roles.split(',').map((role) => role.trim()).filter(Boolean) : form.roles,
        };

        try {
            if (editingId) {
                await axios.put(`${endpoint}/${editingId}`, payload);
            } else {
                await axios.post(endpoint, payload);
            }
            setForm(formFactory());
            setEditingId(null);
            setIsModalOpen(false);
            setMessage(`${singularTitle} saved successfully.`);
            await load();
        } catch (error) {
            setMessage(error.response?.data?.message ?? `Unable to save ${title.toLowerCase()}.`);
        }
    };

    return (
        <div className="space-y-6">
            <section className="glass-panel rounded-[2rem] p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="text-2xl font-semibold text-white">{title} list</h2>
                        <p className="mt-2 text-slate-300">Create and update {title.toLowerCase()} from a dedicated modal.</p>
                    </div>
                    <button className="btn btn-primary" onClick={() => {
                        setEditingId(null);
                        setForm(formFactory());
                        setMessage('');
                        setIsModalOpen(true);
                    }} type="button">Add {singularTitle}</button>
                </div>
                {message ? <p className="mt-5 rounded-2xl bg-white/8 px-4 py-3 text-sm text-slate-200">{message}</p> : null}
                <div className="mt-6 overflow-auto">
                    <table className="min-w-full text-left text-sm text-slate-200">
                        <thead className="text-slate-400">
                            <tr>
                                {Object.keys(rows[0] ?? {}).slice(0, 4).map((key) => <th className="pb-3" key={key}>{key}</th>)}
                                <th className="pb-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr className="border-t border-white/8" key={row.id}>
                                    {Object.entries(row).slice(0, 4).map(([key, value]) => (
                                        <td className="py-3" key={key}>{Array.isArray(value) ? value.map((item) => item.name ?? item).join(', ') : String(value ?? '')}</td>
                                    ))}
                                    <td className="py-3">
                                        <div className="flex gap-2">
                                            <button className="btn btn-secondary py-2" onClick={() => {
                                                setEditingId(row.id);
                                                setForm({
                                                    ...formFactory(),
                                                    ...row,
                                                    roles: row.roles ? row.roles.map((role) => role.name) : formFactory().roles,
                                                    password: '',
                                                });
                                                setMessage('');
                                                setIsModalOpen(true);
                                            }} type="button">Edit</button>
                                            <button className="btn btn-danger py-2" onClick={async () => { await axios.delete(`${endpoint}/${row.id}`); await load(); }} type="button">Archive</button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <Modal isOpen={isModalOpen} title={`${editingId ? 'Edit' : 'Add'} ${singularTitle}`} onClose={() => setIsModalOpen(false)}>
                <form className="space-y-4" onSubmit={submit}>
                    {fields.map((field) => (
                        field.type === 'select' ? (
                            <select className="field" key={field.key} value={form[field.key] ?? ''} onChange={(event) => setForm({ ...form, [field.key]: event.target.value })}>
                                {field.options.map((option) => <option key={option} value={option}>{option}</option>)}
                            </select>
                        ) : (
                            <input
                                className="field"
                                key={field.key}
                                placeholder={field.label}
                                type={field.type === 'password' ? 'password' : field.type === 'email' ? 'email' : 'text'}
                                value={field.type === 'roles' ? (Array.isArray(form[field.key]) ? form[field.key].join(', ') : form[field.key] ?? '') : form[field.key] ?? ''}
                                onChange={(event) => setForm({ ...form, [field.key]: event.target.value })}
                            />
                        )
                    ))}
                    <label className="flex items-center gap-3 text-sm text-slate-300">
                        <input checked={Boolean(form.status)} onChange={(event) => setForm({ ...form, status: event.target.checked })} type="checkbox" />
                        Active
                    </label>
                    <button className="btn btn-primary w-full" type="submit">{editingId ? 'Update' : 'Create'} {singularTitle}</button>
                    {message && isModalOpen ? <p className="text-sm text-slate-300">{message}</p> : null}
                </form>
            </Modal>
        </div>
    );
}

function NotificationsPage({ role }) {
    const [data, setData] = useState(null);
    const [busyId, setBusyId] = useState(null);

    const load = async () => {
        const response = await axios.get('/api/notifications');
        setData(response.data);
    };

    useEffect(() => { load(); }, []);

    const markAllAsRead = async () => {
        setBusyId('all');
        try {
            await axios.post('/api/notifications/read-all');
            await load();
        } finally {
            setBusyId(null);
        }
    };

    const markAsRead = async (notificationId) => {
        setBusyId(notificationId);
        try {
            await axios.post(`/api/notifications/${notificationId}/read`);
            await load();
        } finally {
            setBusyId(null);
        }
    };

    if (!data) return <PageLoader label="Loading notifications..." />;

    return (
        <div className="space-y-6">
            <section className="glass-panel rounded-[2rem] p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className={clsx('text-sm uppercase tracking-[0.3em]', role === 'admin' ? 'text-amber-200' : 'text-teal-200')}>
                            Notification center
                        </p>
                        <h2 className="mt-3 text-3xl font-semibold text-white">Updates that need your attention</h2>
                        <p className="mt-2 text-slate-300">Submission reminders, confirmation alerts, and admin digests all show up here.</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className="rounded-full bg-white/8 px-4 py-2 text-sm text-slate-200">{data.unread_count} unread</span>
                        <button className="btn btn-primary" disabled={busyId === 'all' || data.unread_count === 0} onClick={markAllAsRead} type="button">
                            {busyId === 'all' ? 'Marking...' : 'Mark all as read'}
                        </button>
                    </div>
                </div>
            </section>

            <section className="glass-panel rounded-[2rem] p-8">
                <div className="space-y-4">
                    {data.notifications.length ? data.notifications.map((notification) => (
                        <article className={clsx('rounded-3xl border p-5', notification.read_at ? 'border-white/8 bg-slate-950/20' : 'border-teal-400/20 bg-teal-500/8')} key={notification.id}>
                            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <p className="text-lg font-semibold text-white">{notification.data.title ?? notification.type}</p>
                                    <p className="mt-2 text-sm text-slate-300">{notification.data.message ?? 'Notification received.'}</p>
                                    <p className="mt-3 text-xs uppercase tracking-[0.2em] text-slate-400">{dayjs(notification.created_at).format('DD MMM YYYY, hh:mm A')}</p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className={clsx('rounded-full px-3 py-1 text-xs uppercase tracking-[0.2em]', notification.read_at ? 'bg-white/8 text-slate-300' : 'bg-amber-500/15 text-amber-200')}>
                                        {notification.read_at ? 'Read' : 'Unread'}
                                    </span>
                                    {!notification.read_at ? (
                                        <button className="btn btn-secondary py-2" disabled={busyId === notification.id} onClick={() => markAsRead(notification.id)} type="button">
                                            {busyId === notification.id ? 'Marking...' : 'Mark as read'}
                                        </button>
                                    ) : null}
                                </div>
                            </div>
                        </article>
                    )) : (
                        <div className="rounded-3xl border border-dashed border-white/12 bg-slate-950/20 p-6 text-slate-300">
                            No notifications yet.
                        </div>
                    )}
                </div>
            </section>
        </div>
    );
}

function ChartCard({ title, children }) {
    return (
        <section className="glass-panel rounded-[2rem] p-8">
            <h3 className="text-xl font-semibold text-white">{title}</h3>
            <div className="mt-6">{children}</div>
        </section>
    );
}

function ReportTable({ title, rows }) {
    const headers = Object.keys(rows[0] ?? {});

    return (
        <section className="glass-panel rounded-[2rem] p-8">
            <h3 className="text-xl font-semibold text-white">{title}</h3>
            <div className="mt-6 overflow-auto">
                <table className="min-w-full text-left text-sm text-slate-200">
                    <thead className="text-slate-400">
                        <tr>{headers.map((header) => <th className="pb-3 pr-4" key={header}>{header}</th>)}</tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => (
                            <tr className="border-t border-white/8" key={index}>
                                {headers.map((header) => <td className="py-3 pr-4" key={header}>{String(row[header] ?? '')}</td>)}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function PageLoader({ label }) {
    return <div className="glass-panel rounded-[2rem] p-10 text-slate-200">{label}</div>;
}

function Modal({ isOpen, title, onClose, children }) {
    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 px-4 py-8">
            <div className="glass-panel max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-[2rem] p-8">
                <div className="flex items-start justify-between gap-4">
                    <h3 className="text-2xl font-semibold text-white">{title}</h3>
                    <button className="btn btn-secondary py-2" onClick={onClose} type="button">Close</button>
                </div>
                <div className="mt-6">{children}</div>
            </div>
        </div>
    );
}

function singularize(value) {
    return value.endsWith('s') ? value.slice(0, -1) : value;
}

function flattenRows(rows, nestedKey) {
    return rows.flatMap((row) => row[nestedKey].map((nested) => ({
        employee: row.employee,
        employee_code: row.employee_code,
        project: nested.project,
        engagement_type: nested.engagement_type,
        percentage: nested.percentage,
    })));
}
