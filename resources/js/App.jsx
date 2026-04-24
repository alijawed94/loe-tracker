import axios from 'axios';
import dayjs from 'dayjs';
import clsx from 'clsx';
import {
    ArrowDownTrayIcon,
    ArrowRightOnRectangleIcon,
    BellIcon,
    ChartBarIcon,
    ChatBubbleLeftRightIcon,
    ClipboardDocumentListIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    FolderIcon,
    ShieldCheckIcon,
    PencilSquareIcon,
    PlusIcon,
    Squares2X2Icon,
    TrashIcon,
    UsersIcon,
} from '@heroicons/react/24/outline';
import { useEffect, useMemo, useState } from 'react';
import Select from 'react-select';
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
    { to: '/admin/dashboard', label: 'Dashboard', icon: Squares2X2Icon },
    { to: '/admin/users', label: 'Users', icon: UsersIcon },
    { to: '/admin/projects', label: 'Projects', icon: FolderIcon },
    { to: '/admin/allocations', label: 'Allocations', icon: ClipboardDocumentListIcon },
    { to: '/admin/reports', label: 'Reports', icon: ChartBarIcon },
    { to: '/admin/notifications', label: 'Notifications', icon: BellIcon },
    { to: '/admin/activity-logs', label: 'Activity Logs', icon: ClockIcon },
];

const employeeNav = [
    { to: '/app/dashboard', label: 'My LOE', icon: ClipboardDocumentListIcon },
    { to: '/app/notifications', label: 'Notifications', icon: BellIcon },
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
    const demoCredentials = role === 'admin'
        ? { email: 'admin@example.com', password: 'Password@1' }
        : { email: 'employee@example.com', password: 'Password@1' };

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
            <div className="grid w-full max-w-6xl gap-8 lg:grid-cols-[1.08fr_0.92fr]">
                <section className="glass-panel hidden rounded-[2rem] p-8 lg:block xl:p-10">
                    <div className="space-y-5">
                        <span className="inline-flex rounded-full brand-badge px-4 py-2 text-xs uppercase tracking-[0.3em]">
                            Level of Effort Tracker
                        </span>
                        <h1 className="text-4xl font-semibold leading-tight text-white xl:text-[2.8rem]">
                            Track capacity, allocations, and monthly effort with one clean workflow.
                        </h1>
                        <p className="text-base leading-7 text-slate-300">
                            A shared workspace for employees and admins to capture monthly LOE, review allocation coverage, improve submission compliance, and keep utilization reporting reliable.
                        </p>
                        <div className="grid gap-4 pt-2">
                            <div className="rounded-3xl border border-white/10 bg-slate-950/25 p-5">
                                <div className="flex items-center gap-3">
                                    <span className="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-[rgba(0,169,158,0.14)] text-[#7ff4e4]">
                                        <ClipboardDocumentListIcon className="h-5 w-5" />
                                    </span>
                                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-white">What Employees Can Do</p>
                                </div>
                                <p className="mt-2 text-[0.92rem] leading-6 text-slate-300">
                                    Add monthly LOE submissions, review previous months, respond to admin feedback, track deadlines, and stay aligned with current project allocations.
                                </p>
                            </div>
                            <div className="rounded-3xl border border-white/10 bg-slate-950/25 p-5">
                                <div className="flex items-center gap-3">
                                    <span className="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-[rgba(0,169,158,0.14)] text-[#7ff4e4]">
                                        <ShieldCheckIcon className="h-5 w-5" />
                                    </span>
                                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-white">What Admins Can Do</p>
                                </div>
                                <p className="mt-2 text-[0.92rem] leading-6 text-slate-300">
                                    Manage users, projects, and allocations, review LOEs, monitor exceptions, inspect activity logs, and analyze project health from a central dashboard.
                                </p>
                            </div>
                            <div className="rounded-3xl border border-white/10 bg-slate-950/25 p-5">
                                <div className="flex items-center gap-3">
                                    <span className="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-[rgba(0,169,158,0.14)] text-[#7ff4e4]">
                                        <ExclamationTriangleIcon className="h-5 w-5" />
                                    </span>
                                    <p className="text-xs font-semibold uppercase tracking-[0.2em] text-white">Why It Matters</p>
                                </div>
                                <p className="mt-2 text-[0.92rem] leading-6 text-slate-300">
                                    Cleaner LOE capture means better utilization visibility, healthier project planning, stronger follow-up on missing submissions, and more dependable management reporting.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="glass-panel rounded-[2rem] p-8 md:p-10">
                    <div className="mb-8">
                        <p className="text-sm uppercase tracking-[0.25em] brand-kicker">
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

                    <div className="mt-5 rounded-3xl border border-white/10 bg-slate-950/25 p-4 text-sm text-slate-300">
                        <p className="text-xs uppercase tracking-[0.18em] text-slate-400">Demo Credentials</p>
                        <p className="mt-2">
                            <span className="text-slate-400">Email:</span>
                            {' '}
                            <span className="text-white">{demoCredentials.email}</span>
                        </p>
                        <p className="mt-1">
                            <span className="text-slate-400">Password:</span>
                            {' '}
                            <span className="text-white">{demoCredentials.password}</span>
                        </p>
                    </div>

                    <p className="mt-6 text-sm text-slate-400">
                        Switch area:
                        {' '}
                        <Link className="brand-link" to={role === 'admin' ? '/login' : '/admin/login'}>
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
            footer={<Link className="brand-link" to={role === 'admin' ? '/admin/login' : '/login'}>Back to sign in</Link>}
        >
            <form className="space-y-4" onSubmit={submit}>
                <input className="field" placeholder="Email address" type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
                {message ? <p className="rounded-2xl brand-badge px-4 py-3 text-sm">{message}</p> : null}
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
            footer={<Link className="brand-link" to={role === 'admin' ? '/admin/login' : '/login'}>Back to sign in</Link>}
        >
            <form className="space-y-4" onSubmit={submit}>
                <input className="field" placeholder="Email address" type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} />
                <input className="field" placeholder="New password" type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} />
                <input className="field" placeholder="Confirm new password" type="password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} />
                {message ? <p className="rounded-2xl brand-badge px-4 py-3 text-sm">{message}</p> : null}
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
                <p className="text-sm uppercase tracking-[0.25em] brand-kicker">{eyebrow}</p>
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
        <Shell title="Employee Workspace" user={user} navItems={employeeNav} setUser={setUser}>
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
        <Shell title="Admin Command Center" user={user} navItems={adminNav} setUser={setUser}>
            <Routes>
                <Route path="dashboard" element={<AdminDashboardPage />} />
                <Route path="users" element={<AdminUsersPage />} />
                <Route path="users/:userId/loe-reports" element={<AdminUserLoeReportsPage />} />
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

function Shell({ title, user, navItems, setUser, children }) {
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
                        <p className="text-xs uppercase tracking-[0.3em] brand-kicker">{title}</p>
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
                                <span className="flex items-center gap-3">
                                    {item.icon ? <item.icon className="h-4 w-4" /> : null}
                                    <span>{item.label}</span>
                                </span>
                                {item.label === 'Notifications' && unreadCount > 0 ? (
                                    <span className="rounded-full brand-badge px-2 py-0.5 text-xs">
                                        {unreadCount}
                                    </span>
                                ) : null}
                            </Link>
                        ))}
                    </nav>
                    <button className="btn btn-secondary mt-10 flex w-full items-center gap-2" onClick={logout} type="button">
                        <ArrowRightOnRectangleIcon className="h-4 w-4" />
                        <span>Logout</span>
                    </button>
                </aside>
                <main className="space-y-6">{children}</main>
            </div>
        </div>
    );
}

function EmployeeDashboardPage({ user }) {
    const [data, setData] = useState(null);
    const [saving, setSaving] = useState(false);
    const [deletingReportId, setDeletingReportId] = useState(null);
    const [message, setMessage] = useState('');
    const now = dayjs();
    const [form, setForm] = useState({ month: now.month() + 1, year: now.year(), entries: [{ project_id: '', percentage: '' }] });
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalMode, setModalMode] = useState('create');
    const [editingReportId, setEditingReportId] = useState(null);
    const [feedbackReport, setFeedbackReport] = useState(null);
    const [saveIntent, setSaveIntent] = useState('submitted');
    const [deleteReportTarget, setDeleteReportTarget] = useState(null);

    const load = async () => {
        const response = await axios.get('/api/employee/dashboard');
        setData(response.data);
    };

    useEffect(() => { load(); }, []);

    const matchedReport = useMemo(() => data?.reports.find((report) => report.month === Number(form.month) && report.year === Number(form.year)), [data, form]);
    const editingReport = useMemo(
        () => data?.reports.find((report) => report.id === editingReportId) ?? null,
        [data, editingReportId],
    );
    const isEditing = modalMode === 'edit';
    const isLocked = isEditing ? (editingReport?.is_locked ?? false) : false;
    const totalPercentage = form.entries.reduce((sum, entry) => sum + (Number(entry.percentage) || 0), 0).toFixed(2);
    const allocationTotal = Number(data?.allocations?.reduce((sum, allocation) => sum + Number(allocation.percentage || 0), 0) ?? 0);
    const liveWarnings = getLoeWarnings(Number(totalPercentage), allocationTotal);

    const buildPrefillEntries = () => {
        if (data?.prefill_entries?.length) {
            return data.prefill_entries.map((entry) => ({
                project_id: entry.project_id,
                percentage: entry.percentage,
            }));
        }

        return [{ project_id: '', percentage: '' }];
    };

    const openCreateModal = () => {
        setMessage('');
        setModalMode('create');
        setEditingReportId(null);
        setSaveIntent('submitted');
        setForm({
            month: data?.current_period.month ?? now.month() + 1,
            year: data?.current_period.year ?? now.year(),
            entries: buildPrefillEntries(),
        });
        setIsModalOpen(true);
    };

    const openEditModal = (report) => {
        setMessage('');
        setModalMode('edit');
        setEditingReportId(report.id);
        setSaveIntent(report.status ?? 'submitted');
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
            setModalMode('create');
            setEditingReportId(null);
            setSaveIntent('submitted');
        }
    };

    const copyPreviousMonth = () => {
        const sourceReport = data?.reports.find((report) => report.id !== editingReportId);

        if (!sourceReport) {
            setMessage('No previous LOE found to copy.');
            return;
        }

        setForm((current) => ({
            ...current,
            entries: sourceReport.entries.map((entry) => ({
                project_id: entry.project_id,
                percentage: entry.percentage,
            })),
        }));
        setMessage(`Copied entries from ${dayjs(`${sourceReport.year}-${String(sourceReport.month).padStart(2, '0')}-01`).format('MMMM YYYY')}.`);
    };

    const updateEntry = (index, key, value) => {
        setForm((current) => ({
            ...current,
            entries: current.entries.map((entry, entryIndex) => entryIndex === index ? { ...entry, [key]: value } : entry),
        }));
    };

    const submit = async (event, nextStatus = saveIntent) => {
        event.preventDefault();
        setSaveIntent(nextStatus);
        setSaving(true);
        setMessage('');

        try {
            const payload = {
                month: Number(form.month),
                year: Number(form.year),
                status: nextStatus,
                entries: form.entries.map((entry) => ({
                    project_id: entry.project_id,
                    percentage: Number(entry.percentage),
                })),
            };

            if (isEditing && editingReport) {
                await axios.put(`/api/employee/reports/${editingReport.id}`, { status: payload.status, entries: payload.entries });
                setMessage(nextStatus === 'draft' ? 'Draft updated successfully.' : 'LOE updated successfully.');
            } else {
                await axios.post('/api/employee/reports', payload);
                setMessage(nextStatus === 'draft' ? 'Draft saved successfully.' : 'LOE submitted successfully.');
            }

            await load();
            setIsModalOpen(false);
        } catch (error) {
            setMessage(error.response?.data?.message ?? 'Unable to save LOE.');
        } finally {
            setSaving(false);
        }
    };

    const requestDeleteReport = (report) => {
        setDeleteReportTarget(report);
    };

    const confirmDeleteReport = async () => {
        if (!deleteReportTarget) {
            return;
        }

        setDeletingReportId(deleteReportTarget.id);
        setMessage('');

        try {
            await axios.delete(`/api/employee/reports/${deleteReportTarget.id}`);
            setMessage('LOE deleted successfully.');
            await load();
            setDeleteReportTarget(null);
        } catch (error) {
            setMessage(error.response?.data?.message ?? 'Unable to delete LOE.');
        } finally {
            setDeletingReportId(null);
        }
    };

    if (!data) return <PageLoader label="Loading employee dashboard..." />;

    return (
        <div className="space-y-6">
            <section className="glass-panel rounded-[2rem] p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p className="text-sm uppercase tracking-[0.3em] brand-kicker">Monthly effort</p>
                        <h2 className="mt-3 text-3xl font-semibold text-white">Submit or review your LOE</h2>
                        <p className="mt-2 text-slate-300">Deadline for your current month is {dayjs(data.current_period.deadline).format('DD MMM YYYY, hh:mm A')} ({user.timezone}).</p>
                        <DeadlineCountdown deadline={data.current_period.deadline} />
                        <div className="mt-4 flex flex-wrap items-center gap-3">
                            <span className={clsx('rounded-full px-3 py-1 text-xs uppercase tracking-[0.18em]', statusBadgeClass(data.current_period.status))}>
                                {humanizeStatus(data.current_period.status)}
                            </span>
                            <span className="text-sm text-slate-300">
                                {data.current_period.status === 'overdue' ? 'The current month still needs your attention.' : 'The current month workflow is on track.'}
                            </span>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <button className="btn btn-primary flex items-center gap-2" onClick={openCreateModal} type="button"><PlusIcon className="h-4 w-4" /> <span>Add LOE</span></button>
                        <a className="btn btn-secondary flex items-center gap-2" href="/api/employee/reports/export?format=pdf"><ArrowDownTrayIcon className="h-4 w-4" /> <span>Export PDF</span></a>
                        <a className="btn btn-secondary flex items-center gap-2" href="/api/employee/reports/export?format=xlsx"><ArrowDownTrayIcon className="h-4 w-4" /> <span>Export Excel</span></a>
                    </div>
                </div>
                {message ? <p className="mt-5 rounded-2xl bg-white/8 px-4 py-3 text-sm text-slate-200">{message}</p> : null}
            </section>

            <div className="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <section className="glass-panel rounded-[2rem] p-6">
                    <div>
                        <h3 className="text-xl font-semibold text-white">Current Month LOE</h3>
                        <p className="mt-2 text-slate-300">Use the Add LOE button to add LOE for new month or revise an already submitted one.</p>
                    </div>

                    {data.current_report ? (
                        <article className="mt-6 rounded-3xl border border-white/10 bg-slate-950/25 p-5">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="text-sm uppercase tracking-[0.2em] text-slate-400">{dayjs(`${data.current_report.year}-${data.current_report.month}-01`).format('MMMM YYYY')}</p>
                                    <p className="mt-3 text-3xl font-semibold text-white">{formatPercentage(data.current_report.total_percentage)}</p>
                                    <div className="mt-3 flex flex-wrap items-center gap-3">
                                        <span className={clsx('rounded-full px-3 py-1 text-xs uppercase tracking-[0.18em]', statusBadgeClass(data.current_report.status))}>
                                            {humanizeStatus(data.current_report.status)}
                                        </span>
                                        {data.current_report.reviewed_at ? <span className="text-sm text-slate-300">Reviewed {dayjs(data.current_report.reviewed_at).format('DD MMM YYYY, hh:mm A')}</span> : null}
                                    </div>
                                </div>
                                <div className="flex gap-3">
                                    <button className="btn btn-secondary flex items-center gap-2 py-2" onClick={() => setFeedbackReport(data.current_report)} type="button"><ChatBubbleLeftRightIcon className="h-4 w-4" /> <span>Feedback</span></button>
                                    {!data.current_report.is_locked ? (
                                        <button className="btn btn-secondary flex items-center gap-2 py-2" onClick={() => openEditModal(data.current_report)} type="button"><PencilSquareIcon className="h-4 w-4" /> <span>Edit</span></button>
                                    ) : null}
                                    <button className="btn btn-danger flex items-center gap-2 py-2" disabled={deletingReportId === data.current_report.id} onClick={() => requestDeleteReport(data.current_report)} type="button">
                                        <TrashIcon className="h-4 w-4" />
                                        {deletingReportId === data.current_report.id ? 'Deleting...' : 'Delete'}
                                    </button>
                                </div>
                            </div>
                            <ul className="mt-4 space-y-2 text-sm text-slate-300">
                                {data.current_report.entries.map((entry) => (
                                    <li className="flex items-center justify-between" key={entry.id}>
                                        <span>{entry.project_name}</span>
                                        <span>{formatPercentage(entry.percentage)}</span>
                                    </li>
                                ))}
                            </ul>
                            {data.current_report.review_notes ? (
                                <div className="mt-4 rounded-2xl bg-white/6 px-4 py-3 text-sm text-slate-200">
                                    Review note: {data.current_report.review_notes}
                                </div>
                            ) : null}
                            {data.current_report.warnings?.length ? (
                                <div className="mt-4 space-y-2">
                                    {data.current_report.warnings.map((warning, index) => (
                                        <p className={clsx('rounded-2xl px-4 py-3 text-sm', warning.level === 'critical' ? 'bg-rose-500/15 text-rose-200' : 'bg-amber-500/15 text-amber-100')} key={index}>
                                            {warning.message}
                                        </p>
                                    ))}
                                </div>
                            ) : null}
                        </article>
                    ) : (
                        <div className="mt-6 rounded-3xl border border-dashed border-white/12 bg-slate-950/20 p-6 text-slate-300">
                            No LOE submitted for the current month yet.
                        </div>
                    )}
                </section>

                <section className="glass-panel rounded-[2rem] p-6">
                    <h3 className="text-xl font-semibold text-white">Current Allocations</h3>
                    <div className="mt-5 space-y-3">
                        {data.allocations.map((allocation) => (
                            <div className="rounded-2xl border border-white/10 bg-slate-950/25 p-4" key={allocation.id}>
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <p className="font-medium text-white">{allocation.project_name}</p>
                                    </div>
                                    <span className="brand-kicker">{formatPercentage(allocation.percentage)}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            </div>

            <section className="glass-panel rounded-[2rem] p-8">
                <h3 className="text-xl font-semibold text-white">Previous Submissions</h3>
                <div className="mt-5 overflow-auto">
                    <table className="min-w-full text-left text-sm text-slate-200">
                        <thead className="text-slate-400">
                            <tr>
                                <th className="pb-4 pr-4">Month / Year</th>
                                <th className="pb-4 pr-4">Total</th>
                                <th className="pb-4 pr-4">Project</th>
                                <th className="pb-4 pr-4">Percentage</th>
                                <th className="pb-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.reports.map((report) => (
                                report.entries.map((entry, entryIndex) => (
                                    <tr className="border-t border-white/8 align-top" key={`${report.id}-${entry.id}`}>
                                        {entryIndex === 0 ? (
                                            <>
                                                <td className="py-4 pr-4" rowSpan={report.entries.length}>
                                                    <div>
                                                        <p className="font-semibold text-white">{dayjs(`${report.year}-${report.month}-01`).format('MMMM YYYY')}</p>
                                                        <div className="mt-2 flex flex-wrap items-center gap-2">
                                                            <span className={clsx('rounded-full px-3 py-1 text-xs uppercase tracking-[0.18em]', statusBadgeClass(report.status))}>
                                                                {humanizeStatus(report.status)}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="py-4 pr-4" rowSpan={report.entries.length}>
                                                    <span className="font-semibold text-white">{formatPercentage(report.total_percentage)}</span>
                                                </td>
                                            </>
                                        ) : null}
                                        <td className="py-4 pr-4">{entry.project_name}</td>
                                        <td className="py-4 pr-4">{formatPercentage(entry.percentage)}</td>
                                        {entryIndex === 0 ? (
                                            <td className="py-4" rowSpan={report.entries.length}>
                                                <div className="flex gap-2">
                                                    <button className="btn btn-secondary flex items-center gap-2 py-2" onClick={() => setFeedbackReport(report)} type="button"><ChatBubbleLeftRightIcon className="h-4 w-4" /> <span>Feedback</span></button>
                                                    {!report.is_locked ? (
                                                        <button className="btn btn-secondary flex items-center gap-2 py-2" onClick={() => openEditModal(report)} type="button"><PencilSquareIcon className="h-4 w-4" /> <span>Edit</span></button>
                                                    ) : null}
                                                    <button className="btn btn-danger flex items-center gap-2 py-2" disabled={deletingReportId === report.id} onClick={() => requestDeleteReport(report)} type="button">
                                                        <TrashIcon className="h-4 w-4" />
                                                        {deletingReportId === report.id ? 'Deleting...' : 'Delete'}
                                                    </button>
                                                </div>
                                            </td>
                                        ) : null}
                                    </tr>
                                ))
                            ))}
                        </tbody>
                    </table>
                    {!data.reports.length ? (
                        <div className="rounded-3xl border border-dashed border-white/12 bg-slate-950/20 p-6 text-slate-300">
                            No previous submissions found yet.
                        </div>
                    ) : null}
                </div>
            </section>

            <Modal
                isOpen={isModalOpen}
                title={isEditing ? `Edit LOE for ${dayjs(`${form.year}-${form.month}-01`).format('MMMM YYYY')}` : 'Add Monthly LOE'}
                onClose={closeModal}
            >
                <form className="space-y-4" onSubmit={(event) => submit(event, saveIntent)}>
                    <div className="grid gap-4 md:grid-cols-2">
                        <select
                            className="field"
                            disabled={isEditing}
                            value={String(form.month ?? '')}
                            onChange={(event) => setForm({ ...form, month: Number(event.target.value) })}
                        >
                            <option value="">Select month</option>
                            {monthOptions.map((month) => (
                                <option key={month.value} value={month.value}>{month.label}</option>
                            ))}
                        </select>
                        <select
                            className="field"
                            disabled={isEditing}
                            value={String(form.year ?? '')}
                            onChange={(event) => setForm({ ...form, year: Number(event.target.value) })}
                        >
                            <option value="">Select year</option>
                            {yearOptions.map((year) => (
                                <option key={year} value={year}>{year}</option>
                            ))}
                        </select>
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
                        {!isEditing ? (
                            <button className="btn btn-secondary" disabled={isLocked} onClick={() => {
                                setForm((current) => ({ ...current, entries: buildPrefillEntries() }));
                                setMessage('Prefilled entries from current allocations.');
                            }} type="button">Use Allocations</button>
                        ) : null}
                        <button className="btn btn-secondary" disabled={isLocked} onClick={copyPreviousMonth} type="button">Copy Last Month</button>
                        <button className="btn btn-secondary" disabled={isLocked} onClick={() => setForm({ ...form, entries: [...form.entries, { project_id: '', percentage: '' }] })} type="button">Add project</button>
                        <button className="btn btn-secondary" disabled={saving || isLocked} onClick={(event) => submit(event, 'draft')} type="button">{saving && saveIntent === 'draft' ? 'Saving...' : 'Save Draft'}</button>
                        <button className="btn btn-primary" disabled={saving || isLocked} onClick={(event) => submit(event, 'submitted')} type="button">{saving && saveIntent === 'submitted' ? 'Saving...' : isEditing ? 'Submit Update' : 'Submit LOE'}</button>
                    </div>

                    <p className="text-sm text-slate-300">Total percentage: {formatPercentage(totalPercentage)}</p>
                    {liveWarnings.length ? (
                        <div className="space-y-2">
                            {liveWarnings.map((warning, index) => (
                                <p className={clsx('rounded-2xl px-4 py-3 text-sm', warning.level === 'critical' ? 'bg-rose-500/15 text-rose-200' : 'bg-amber-500/15 text-amber-100')} key={index}>
                                    {warning.message}
                                </p>
                            ))}
                        </div>
                    ) : null}
                    {isLocked ? <p className="rounded-2xl brand-badge-soft px-4 py-3 text-sm">This report is read-only because the selected month has already closed.</p> : null}
                    {message && isModalOpen ? <p className="rounded-2xl bg-white/8 px-4 py-3 text-sm text-slate-200">{message}</p> : null}
                </form>
            </Modal>

            <FeedbackThreadModal
                isOpen={Boolean(feedbackReport)}
                report={feedbackReport}
                title={feedbackReport ? `Feedback for ${dayjs(`${feedbackReport.year}-${String(feedbackReport.month).padStart(2, '0')}-01`).format('MMMM YYYY')}` : 'Feedback'}
                onClose={() => setFeedbackReport(null)}
                onPosted={load}
            />

            <Modal isOpen={Boolean(deleteReportTarget)} title="Delete LOE" onClose={() => setDeleteReportTarget(null)}>
                {deleteReportTarget ? (
                    <div className="space-y-4">
                        <p className="text-slate-200">
                            Delete the LOE report for {dayjs(`${deleteReportTarget.year}-${String(deleteReportTarget.month).padStart(2, '0')}-01`).format('MMMM YYYY')}?
                        </p>
                        {deleteReportTarget.is_locked ? (
                            <p className="rounded-2xl bg-rose-500/15 px-4 py-3 text-sm text-rose-200">
                                This LOE is from a closed period and will be permanently removed.
                            </p>
                        ) : null}
                        <div className="flex justify-end gap-3">
                            <button className="btn btn-secondary" onClick={() => setDeleteReportTarget(null)} type="button">Cancel</button>
                            <button className="btn btn-danger" disabled={deletingReportId === deleteReportTarget.id} onClick={confirmDeleteReport} type="button">
                                {deletingReportId === deleteReportTarget.id ? 'Deleting...' : 'Delete LOE'}
                            </button>
                        </div>
                    </div>
                ) : null}
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
            <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                {Object.entries(data.metrics)
                    .filter(([key]) => key !== 'current_allocation_total')
                    .map(([key, value]) => (
                        <div className="metric-card" key={key}>
                            <p className="text-sm uppercase tracking-[0.2em] text-slate-400">{key.replaceAll('_', ' ')}</p>
                            <p className="mt-4 text-3xl font-semibold text-white">{value}</p>
                        </div>
                    ))}
            </section>
            <ChartCard title="Project Allocation Coverage">
                <ResponsiveContainer width="100%" height={Math.max(300, data.charts.project_allocation_headcount.length * 28)}>
                    <BarChart barCategoryGap={8} data={data.charts.project_allocation_headcount} layout="vertical" margin={{ top: 8, right: 24, left: 88, bottom: 8 }}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                        <XAxis allowDecimals={false} stroke="#94a3b8" type="number" />
                        <YAxis dataKey="project_name" interval={0} stroke="#94a3b8" tick={{ fontSize: 12 }} type="category" width={220} />
                        <Tooltip />
                        <Bar barSize={14} dataKey="allocated_people" fill="#2dd4bf" name="Allocated People" radius={[0, 8, 8, 0]} />
                    </BarChart>
                </ResponsiveContainer>
            </ChartCard>
            <section className="glass-panel rounded-[2rem] p-8">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h3 className="text-xl font-semibold text-white">Exceptions Needing Attention</h3>
                        <p className="mt-2 text-slate-300">Low totals, missing submissions, drafts, and pending reviews surface here first.</p>
                    </div>
                    <span className="rounded-full brand-badge px-3 py-1 text-xs uppercase tracking-[0.18em]">{data.exceptions.length} items</span>
                </div>
                <div className="mt-6 space-y-3">
                    {data.exceptions.length ? data.exceptions.map((item, index) => (
                        <div className="rounded-2xl border border-white/10 bg-slate-950/25 p-4" key={`${item.type}-${item.employee_code}-${index}`}>
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div className="flex flex-wrap items-center gap-3">
                                        <p className="font-semibold text-white">{item.employee}</p>
                                        <span className={clsx('rounded-full px-3 py-1 text-xs uppercase tracking-[0.18em]', item.severity === 'critical' ? 'bg-rose-500/15 text-rose-200' : 'bg-amber-500/15 text-amber-100')}>
                                            {item.severity}
                                        </span>
                                    </div>
                                    <p className="mt-2 text-sm text-slate-300">{item.message}</p>
                                </div>
                                <div className="flex flex-col items-end gap-3">
                                    <p className="text-sm text-slate-400">{item.employee_code}</p>
                                    {item.user_id ? (
                                        <Link className="brand-link text-sm" to={`/admin/users/${item.user_id}/loe-reports`}>
                                            Review LOE
                                        </Link>
                                    ) : null}
                                </div>
                            </div>
                        </div>
                    )) : (
                        <div className="rounded-3xl border border-dashed border-white/12 bg-slate-950/20 p-6 text-slate-300">
                            No exceptions found for the selected month.
                        </div>
                    )}
                </div>
            </section>
        </div>
    );
}

function AdminUsersPage() {
    const navigate = useNavigate();

    return <CrudPage title="Users" endpoint="/api/admin/users" formFactory={() => ({ name: '', email: '', employee_code: '', designation: '', stream: 'engineering', timezone: 'Asia/Karachi', status: true, password: '', roles: ['employee'] })} fields={[
        { key: 'name', label: 'Name' },
        { key: 'email', label: 'Email', type: 'email' },
        { key: 'employee_code', label: 'Employee Code' },
        { key: 'designation', label: 'Designation' },
        {
            key: 'stream', label: 'Stream', type: 'select', options: [
                { value: 'engineering', label: 'Engineering' },
                { value: 'experience', label: 'Experience' },
                { value: 'admin', label: 'Admin' },
            ]
        },
        { key: 'timezone', label: 'Timezone' },
        { key: 'password', label: 'Password', type: 'password' },
        {
            key: 'roles', label: 'Roles', type: 'multiselect', options: [
                { value: 'employee', label: 'Employee' },
                { value: 'admin', label: 'Admin' },
            ]
        },
    ]} searchPlaceholder="Search by ID, name, email, or employee code" extraRowActions={(row) => (
        <button className="btn btn-secondary py-2" onClick={() => navigate(`/admin/users/${row.id}/loe-reports`)} type="button">
            LOEs
        </button>
    )} />;
}

function AdminUserLoeReportsPage() {
    const { userId } = useParams();
    const navigate = useNavigate();
    const [data, setData] = useState(null);
    const [feedbackReport, setFeedbackReport] = useState(null);
    const [reviewingReportId, setReviewingReportId] = useState(null);
    const [reviewTarget, setReviewTarget] = useState(null);
    const [reviewNotes, setReviewNotes] = useState('');

    const load = async () => {
        const response = await axios.get(`/api/admin/users/${userId}/loe-reports`);
        setData(response.data);
    };

    useEffect(() => {
        load();
    }, [userId]);

    if (!data) return <PageLoader label="Loading user LOEs..." />;

    return (
        <div className="space-y-6">
            <section className="glass-panel rounded-[2rem] p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <button className="btn btn-secondary py-2" onClick={() => navigate('/admin/users')} type="button">Back to users</button>
                        <h2 className="mt-4 text-2xl font-semibold text-white">{data.user.name} LOEs</h2>
                        <p className="mt-2 text-slate-300">
                            {[
                                data.user.employee_code,
                                data.user.email,
                                data.user.stream_label,
                            ].filter(Boolean).join(' • ')}
                        </p>
                    </div>
                    <div className="flex gap-3">
                        <a className="btn btn-secondary" href={`/api/admin/users/${userId}/loe-reports/export?format=pdf`}>Export PDF</a>
                        <a className="btn btn-primary" href={`/api/admin/users/${userId}/loe-reports/export?format=xlsx`}>Export Excel</a>
                    </div>
                </div>
            </section>

            <section className="glass-panel rounded-[2rem] p-8">
                <h3 className="text-xl font-semibold text-white">Submitted LOEs</h3>
                <div className="mt-5 overflow-auto">
                    {data.reports.length ? (
                        <table className="min-w-full text-left text-sm text-slate-200">
                            <thead className="text-slate-400">
                                <tr>
                                    <th className="pb-4 pr-4">Month / Year</th>
                                    <th className="pb-4 pr-4">Total</th>
                                    <th className="pb-4 pr-4">Project</th>
                                    <th className="pb-4 pr-4">Engagement Type</th>
                                    <th className="pb-4 pr-4">Percentage</th>
                                    <th className="pb-4 pr-4">Submitted At</th>
                                    <th className="pb-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.reports.map((report) => (
                                    report.entries.map((entry, entryIndex) => (
                                        <tr className="border-t border-white/8 align-top" key={`${report.id}-${entry.id}`}>
                                            {entryIndex === 0 ? (
                                                <>
                                                    <td className="py-4 pr-4" rowSpan={report.entries.length}>
                                                        <p className="font-semibold text-white">{dayjs(`${report.year}-${String(report.month).padStart(2, '0')}-01`).format('MMMM YYYY')}</p>
                                                        <div className="mt-2 flex flex-wrap items-center gap-2">
                                                            <span className={clsx('rounded-full px-3 py-1 text-xs uppercase tracking-[0.18em]', statusBadgeClass(report.status))}>
                                                                {humanizeStatus(report.status)}
                                                            </span>
                                                        </div>
                                                        {report.warnings?.length ? (
                                                            <div className="mt-3 space-y-2">
                                                                {report.warnings.map((warning, warningIndex) => (
                                                                    <p className={clsx('rounded-2xl px-3 py-2 text-xs', warning.level === 'critical' ? 'bg-rose-500/15 text-rose-200' : 'bg-amber-500/15 text-amber-100')} key={warningIndex}>
                                                                        {warning.message}
                                                                    </p>
                                                                ))}
                                                            </div>
                                                        ) : null}
                                                    </td>
                                                    <td className="py-4 pr-4" rowSpan={report.entries.length}>
                                                        <span className="font-semibold text-white">{formatPercentage(report.total_percentage)}</span>
                                                    </td>
                                                </>
                                            ) : null}
                                            <td className="py-4 pr-4">{entry.project_name}</td>
                                            <td className="py-4 pr-4">{entry.engagement_type_label ?? entry.engagement_type}</td>
                                            <td className="py-4 pr-4">{formatPercentage(entry.percentage)}</td>
                                            {entryIndex === 0 ? (
                                                <td className="py-4 pr-4" rowSpan={report.entries.length}>
                                                    {report.submitted_at ? dayjs(report.submitted_at).format('DD MMM YYYY, hh:mm A') : ''}
                                                    {report.review_notes ? <p className="mt-3 text-sm text-slate-300">Note: {report.review_notes}</p> : null}
                                                </td>
                                            ) : null}
                                            {entryIndex === 0 ? (
                                                <td className="py-4" rowSpan={report.entries.length}>
                                                    <div className="flex flex-col gap-2">
                                                        <button className="btn btn-secondary py-2" onClick={() => setFeedbackReport(report)} type="button">Feedback</button>
                                                        {report.status !== 'approved' ? (
                                                            <button
                                                                className="btn btn-primary py-2"
                                                                disabled={reviewingReportId === report.id}
                                                                onClick={() => {
                                                                    setReviewTarget(report);
                                                                    setReviewNotes(report.review_notes ?? '');
                                                                }}
                                                                type="button"
                                                            >
                                                                {reviewingReportId === report.id ? 'Approving...' : 'Approve'}
                                                            </button>
                                                        ) : null}
                                                    </div>
                                                </td>
                                            ) : null}
                                        </tr>
                                    ))
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <div className="rounded-3xl border border-dashed border-white/12 bg-slate-950/20 p-6 text-slate-300">
                            No LOEs found for this user.
                        </div>
                    )}
                </div>
            </section>

            <FeedbackThreadModal
                isOpen={Boolean(feedbackReport)}
                report={feedbackReport}
                title={feedbackReport ? `Feedback for ${data.user.name} • ${dayjs(`${feedbackReport.year}-${String(feedbackReport.month).padStart(2, '0')}-01`).format('MMMM YYYY')}` : 'Feedback'}
                onClose={() => setFeedbackReport(null)}
                onPosted={load}
            />
            <Modal isOpen={Boolean(reviewTarget)} title="Approve LOE" onClose={() => setReviewTarget(null)}>
                {reviewTarget ? (
                    <div className="space-y-4">
                        <p className="text-slate-200">
                            Approve the LOE for {dayjs(`${reviewTarget.year}-${String(reviewTarget.month).padStart(2, '0')}-01`).format('MMMM YYYY')}?
                        </p>
                        <textarea
                            className="field min-h-28 resize-y"
                            placeholder="Add an optional review note"
                            value={reviewNotes}
                            onChange={(event) => setReviewNotes(event.target.value)}
                        />
                        <div className="flex justify-end gap-3">
                            <button className="btn btn-secondary" onClick={() => setReviewTarget(null)} type="button">Cancel</button>
                            <button
                                className="btn btn-primary"
                                disabled={reviewingReportId === reviewTarget.id}
                                onClick={async () => {
                                    setReviewingReportId(reviewTarget.id);

                                    try {
                                        await axios.patch(`/api/admin/users/${userId}/loe-reports/${reviewTarget.id}/review`, {
                                            status: 'approved',
                                            review_notes: reviewNotes,
                                        });
                                        await load();
                                        setReviewTarget(null);
                                    } finally {
                                        setReviewingReportId(null);
                                    }
                                }}
                                type="button"
                            >
                                {reviewingReportId === reviewTarget.id ? 'Approving...' : 'Approve LOE'}
                            </button>
                        </div>
                    </div>
                ) : null}
            </Modal>
        </div>
    );
}

function AdminProjectsPage() {
    return <CrudPage title="Projects" endpoint="/api/admin/projects" formFactory={() => ({ name: '', engagement: '', description: '', engagement_type: 'project', status: true })} fields={[
        { key: 'name', label: 'Name' },
        { key: 'engagement', label: 'Engagement' },
        { key: 'description', label: 'Description' },
        {
            key: 'engagement_type', label: 'Engagement Type', type: 'select', options: [
                { value: 'project', label: 'Project' },
                { value: 'product', label: 'Product' },
                { value: 'marketing', label: 'Marketing' },
                { value: 'admin', label: 'Admin' },
            ]
        },
    ]} searchPlaceholder="Search by ID, name, or engagement" listKeys={['id', 'name', 'engagement', 'engagement_type']} />;
}

function AdminAllocationsPage() {
    const [allocations, setAllocations] = useState([]);
    const [users, setUsers] = useState([]);
    const [projects, setProjects] = useState([]);
    const [form, setForm] = useState({ user_id: '', project_id: '', percentage: '' });
    const [editingId, setEditingId] = useState(null);
    const [message, setMessage] = useState('');
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [selectedProjectFilters, setSelectedProjectFilters] = useState([]);
    const groupedAllocations = useMemo(() => (
        allocations.reduce((groups, allocation) => {
            const key = allocation.user_id;

            if (!groups[key]) {
                groups[key] = {
                    user_id: allocation.user_id,
                    employee_name: allocation.user?.name,
                    rows: [],
                    total_percentage: 0,
                };
            }

            groups[key].rows.push(allocation);
            groups[key].total_percentage += Number(allocation.percentage ?? 0);

            return groups;
        }, {})
    ), [allocations]);

    const load = async () => {
        const params = new URLSearchParams();
        if (search.trim()) {
            params.set('search', search.trim());
        }
        selectedProjectFilters.forEach((projectId) => params.append('project_ids[]', projectId));

        const [allocationResponse, userResponse, projectResponse] = await Promise.all([
            axios.get(`/api/admin/allocations?${params.toString()}`),
            axios.get('/api/admin/users'),
            axios.get('/api/admin/projects'),
        ]);
        setAllocations(allocationResponse.data);
        setUsers(userResponse.data);
        setProjects(projectResponse.data.filter((project) => !project.deleted_at));
    };

    useEffect(() => { load(); }, [search, selectedProjectFilters]);

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
                <div className="mt-5 grid gap-3 lg:grid-cols-[1fr_320px_auto]">
                    <input className="field" placeholder="Search by employee name, email, employee code, or project" value={search} onChange={(event) => setSearch(event.target.value)} />
                    <Select
                        className="react-select-container"
                        classNamePrefix="react-select"
                        closeMenuOnSelect={false}
                        isMulti
                        options={projects.map((project) => ({ value: project.id, label: project.name }))}
                        placeholder="Filter by projects"
                        styles={selectStyles}
                        value={projects
                            .filter((project) => selectedProjectFilters.includes(project.id))
                            .map((project) => ({ value: project.id, label: project.name }))}
                        onChange={(selectedOptions) => setSelectedProjectFilters((selectedOptions ?? []).map((option) => option.value))}
                    />
                    <button className="btn btn-secondary" onClick={() => {
                        setSearch('');
                        setSelectedProjectFilters([]);
                    }} type="button">Clear</button>
                </div>
                <div className="mt-6 overflow-auto">
                    {allocations.length ? (
                        <table className="min-w-full text-left text-sm text-slate-200">
                            <thead className="text-slate-400">
                                <tr>
                                    <th className="pb-3">Employee</th>
                                    <th className="pb-3">Project</th>
                                    <th className="pb-3">Percentage</th>
                                    <th className="pb-3">Total Allocation</th>
                                    <th className="pb-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {Object.values(groupedAllocations).map((group) => (
                                    group.rows.map((allocation, index) => (
                                        <tr className="border-t border-white/8 align-top" key={allocation.id}>
                                            {index === 0 ? (
                                                <td className="py-3" rowSpan={group.rows.length}>{group.employee_name}</td>
                                            ) : null}
                                            <td className="py-3">{allocation.project?.name}</td>
                                            <td className="py-3">{formatPercentage(allocation.percentage)}</td>
                                            {index === 0 ? (
                                                <td className="py-3 font-medium text-white" rowSpan={group.rows.length}>
                                                    {formatPercentage(group.total_percentage)}
                                                </td>
                                            ) : null}
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
                                    ))
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <div className="rounded-3xl border border-dashed border-white/12 bg-slate-950/20 p-6 text-slate-300">
                            No records found.
                        </div>
                    )}
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
    const [selectedMonthlyReport, setSelectedMonthlyReport] = useState(null);

    useEffect(() => {
        axios.get('/api/admin/reports').then((response) => setData(response.data));
    }, []);

    if (!data) return <PageLoader label="Loading reports..." />;

    const lastMonthReference = data.employee_monthly[0]
        ? dayjs(`${data.employee_monthly[0].year}-${String(data.employee_monthly[0].month).padStart(2, '0')}-01`)
        : dayjs().subtract(1, 'month');
    const employeeMonthlyRows = data.employee_monthly.map((row) => ({
        employee: row.employee,
        employee_code: row.employee_code,
        stream: row.stream,
        stream_label: row.stream_label,
        total_percentage: row.total_percentage,
        loe_status: row.loe_status,
        loe_status_tone: row.loe_status_tone,
        submitted_at: row.submitted_at,
        entries: row.entries,
    }));

    return (
        <div className="space-y-6">
            <section className="glass-panel rounded-[2rem] p-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-2xl font-semibold text-white">Reporting center</h2>
                        <p className="mt-2 text-slate-300">Employee monthly summary shows the latest submitted LOE from last month for each employee. User-specific exports are available inside each employee LOE history page.</p>
                    </div>
                </div>
            </section>

            <ReportTable
                title={`Last Month LOEs (${lastMonthReference.format('MMMM YYYY')})`}
                rows={employeeMonthlyRows}
                hiddenHeaders={['entries', 'loe_status_tone']}
                getRowActions={(row) => (
                    <button className="btn btn-secondary py-2" onClick={() => setSelectedMonthlyReport(row)} type="button">
                        Details
                    </button>
                )}
            />
            <ReportTable title="Project Summary" rows={data.project_summary} />
            <ReportTable title="Missing Submissions" rows={data.missing_submissions} />

            <Modal
                isOpen={Boolean(selectedMonthlyReport)}
                title={selectedMonthlyReport ? `LOE Breakdown for ${selectedMonthlyReport.employee}` : 'LOE Breakdown'}
                onClose={() => setSelectedMonthlyReport(null)}
            >
                {selectedMonthlyReport ? (
                    <div className="space-y-5">
                        <div className="rounded-3xl border border-white/10 bg-slate-950/25 p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm uppercase tracking-[0.2em] text-slate-400">{lastMonthReference.format('MMMM YYYY')}</p>
                                    <h4 className="mt-2 text-xl font-semibold text-white">{selectedMonthlyReport.employee}</h4>
                                    <p className="mt-1 text-sm text-slate-300">{selectedMonthlyReport.employee_code}</p>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm text-slate-400">Total LOE</p>
                                    <p className="mt-2 text-2xl font-semibold text-white">{formatPercentage(selectedMonthlyReport.total_percentage)}</p>
                                </div>
                            </div>
                        </div>
                        <div className="overflow-auto">
                            <table className="min-w-full text-left text-sm text-slate-200">
                                <thead className="text-slate-400">
                                    <tr>
                                        <th className="pb-3 pr-4">Project</th>
                                        <th className="pb-3 pr-4">Engagement Type</th>
                                        <th className="pb-3 pr-4">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {selectedMonthlyReport.entries?.map((entry) => (
                                        <tr className="border-t border-white/8" key={entry.id}>
                                            <td className="py-3 pr-4">{entry.project}</td>
                                            <td className="py-3 pr-4">{entry.engagement_type_label ?? entry.engagement_type}</td>
                                            <td className="py-3 pr-4">{formatPercentage(entry.percentage)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ) : null}
            </Modal>
        </div>
    );
}

function AdminActivityLogsPage() {
    const [data, setData] = useState(null);
    const [filters, setFilters] = useState({ module: '', event: '', subject: '', actor: '' });
    const [selectedActivity, setSelectedActivity] = useState(null);

    const load = async (nextFilters = filters) => {
        const params = new URLSearchParams();
        if (nextFilters.module) params.set('log_name', nextFilters.module);
        if (nextFilters.event) params.set('event', nextFilters.event);
        if (nextFilters.subject) params.set('subject', nextFilters.subject);
        if (nextFilters.actor) params.set('causer_id', nextFilters.actor);

        const response = await axios.get(`/api/admin/activity-logs?${params.toString()}`);
        setData(response.data);
    };

    useEffect(() => { load(); }, []);

    if (!data) return <PageLoader label="Loading activity logs..." />;

    return (
        <div className="space-y-6">
            <section className="glass-panel rounded-[2rem] p-8">
                <h2 className="text-2xl font-semibold text-white">Activity logs</h2>
                <div className="mt-6 grid gap-3 lg:grid-cols-4">
                    <select className="field" value={filters.module} onChange={(event) => setFilters({ ...filters, module: event.target.value })}>
                        <option value="">Filter by module</option>
                        {activityModuleOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                    <select className="field" value={filters.event} onChange={(event) => setFilters({ ...filters, event: event.target.value })}>
                        <option value="">Filter by event</option>
                        {activityEventOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                    <select className="field" value={filters.subject} onChange={(event) => setFilters({ ...filters, subject: event.target.value })}>
                        <option value="">Filter by subject</option>
                        {activitySubjectOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                    <select className="field" value={filters.actor} onChange={(event) => setFilters({ ...filters, actor: event.target.value })}>
                        <option value="">Filter by actor</option>
                        {(data.filter_options?.actors ?? []).map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                    </select>
                </div>
                <div className="mt-4 mb-2 flex justify-end gap-3">
                    <button className="btn btn-primary" onClick={() => load(filters)} type="button">Apply</button>
                    <button className="btn btn-secondary" onClick={() => {
                        const clearedFilters = { module: '', event: '', subject: '', actor: '' };
                        setFilters(clearedFilters);
                        load(clearedFilters);
                    }} type="button">Clear Filters</button>
                </div>
            </section>

            <section className="glass-panel rounded-[2rem] p-8">
                {data.data.length ? (
                    <div className="overflow-hidden">
                        <table className="w-full table-fixed text-left text-sm text-slate-200">
                            <colgroup>
                                <col className="w-[13%]" />
                                <col className="w-[11%]" />
                                <col className="w-[11%]" />
                                <col className="w-[28%]" />
                                <col className="w-[25%]" />
                                <col className="w-[12%]" />
                            </colgroup>
                            <thead className="text-slate-400">
                                <tr>
                                    <th className="pb-3 pr-4">When</th>
                                    <th className="pb-3 pr-4">Module</th>
                                    <th className="pb-3 pr-4">Event</th>
                                    <th className="pb-3 pr-4">Summary</th>
                                    <th className="pb-3 pr-4">Actor</th>
                                    <th className="pb-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.data.map((activity) => (
                                    <tr className="border-t border-white/8 align-top" key={activity.id}>
                                        <td className="py-3 pr-4 break-words text-xs leading-5">{dayjs(activity.created_at).format('DD MMM YYYY, hh:mm A')}</td>
                                        <td className="py-3 pr-4 break-words">{humanizeLogName(activity.log_name)}</td>
                                        <td className="py-3 pr-4 break-words">{humanizeActivityEvent(activity.event)}</td>
                                        <td className="py-3 pr-4 break-words leading-6">{summarizeActivity(activity)}</td>
                                        <td className="py-3 pr-4 break-words leading-6">{activity.causer ? `${activity.causer.name} (${activity.causer.email})` : 'System'}</td>
                                        <td className="py-3 align-middle">
                                            <button className="btn btn-secondary py-2" onClick={() => setSelectedActivity(activity)} type="button">Details</button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="rounded-3xl border border-dashed border-white/12 bg-slate-950/20 p-6 text-slate-300">
                        No activity logs found.
                    </div>
                )}
            </section>

            <section className="hidden glass-panel rounded-[2rem] p-8">
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

            <Modal isOpen={Boolean(selectedActivity)} title="Activity Details" onClose={() => setSelectedActivity(null)}>
                {selectedActivity ? (
                    <div className="space-y-5">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="rounded-2xl bg-slate-900/60 p-4">
                                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Action</p>
                                <p className="mt-2 text-lg font-semibold text-white">{selectedActivity.description}</p>
                                <p className="mt-2 text-sm text-slate-300">
                                    {humanizeLogName(selectedActivity.log_name)} • {humanizeActivityEvent(selectedActivity.event)} • {dayjs(selectedActivity.created_at).format('DD MMM YYYY, hh:mm A')}
                                </p>
                            </div>
                            <div className="rounded-2xl bg-slate-900/60 p-4">
                                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Context</p>
                                <p className="mt-2 text-sm text-slate-200">Actor: {selectedActivity.causer ? `${selectedActivity.causer.name} (${selectedActivity.causer.email})` : 'System'}</p>
                                <p className="mt-2 text-sm text-slate-200">Subject: {selectedActivity.subject ? `${selectedActivity.subject.type} ${selectedActivity.subject.id}` : 'None'}</p>
                            </div>
                        </div>

                        <div className="rounded-2xl bg-slate-900/60 p-4">
                            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">What Happened</p>
                            <div className="mt-3 space-y-3">
                                {renderActivityDetails(selectedActivity)}
                            </div>
                        </div>
                    </div>
                ) : null}
            </Modal>
        </div>
    );
}

function renderActivityDetails(activity) {
    const details = getActivityDetails(activity);

    if (!details.length) {
        return <p className="text-sm text-slate-300">No additional change details were recorded for this activity.</p>;
    }

    return details.map((detail, index) => (
        <div className="rounded-2xl border border-white/8 bg-slate-950/25 p-4" key={`${detail.label}-${index}`}>
            <p className="text-xs uppercase tracking-[0.18em] text-slate-400">{detail.label}</p>
            {detail.type === 'text' ? (
                <p className="mt-2 text-sm text-slate-200">{detail.value}</p>
            ) : (
                <div className="mt-3 overflow-auto">
                    <table className="min-w-full text-left text-sm text-slate-200">
                        <thead className="text-slate-400">
                            <tr>
                                <th className="pb-2 pr-4">Field</th>
                                {detail.type === 'changes' ? <th className="pb-2 pr-4">Previous</th> : null}
                                <th className="pb-2 pr-4">{detail.type === 'changes' ? 'Current' : 'Value'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {detail.items.map((item) => (
                                <tr className="border-t border-white/8" key={item.field}>
                                    <td className="py-2 pr-4">{item.field}</td>
                                    {detail.type === 'changes' ? <td className="py-2 pr-4">{item.previous}</td> : null}
                                    <td className="py-2 pr-4">{item.current}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    ));
}

function getActivityDetails(activity) {
    const properties = activity.properties ?? {};
    const attributes = normalizeActivityValues(properties.attributes);
    const previous = normalizeActivityValues(properties.old);
    const details = [];

    const changedFields = Object.keys(attributes).filter((field) => previous[field] !== undefined || activity.event !== 'created');

    if (activity.event === 'updated' && changedFields.length) {
        details.push({
            label: 'Changed Fields',
            type: 'changes',
            items: changedFields.map((field) => ({
                field: humanizeKey(field),
                previous: formatActivityValue(previous[field]),
                current: formatActivityValue(attributes[field]),
            })),
        });
    } else if (Object.keys(attributes).length) {
        details.push({
            label: activity.event === 'created' ? 'Captured Values' : 'Details',
            type: 'values',
            items: Object.entries(attributes).map(([field, value]) => ({
                field: humanizeKey(field),
                current: formatActivityValue(value),
            })),
        });
    }

    const metadata = Object.entries(properties)
        .filter(([key]) => !['attributes', 'old'].includes(key))
        .map(([key, value]) => ({
            field: humanizeKey(key),
            current: formatActivityValue(value),
        }));

    if (metadata.length) {
        details.push({
            label: 'Additional Context',
            type: 'values',
            items: metadata,
        });
    }

    if (!details.length && activity.subject) {
        details.push({
            label: 'Summary',
            type: 'text',
            value: `${humanizeLogName(activity.log_name)} ${humanizeActivityEvent(activity.event).toLowerCase()} for ${activity.subject.type} ${activity.subject.id}.`,
        });
    }

    return details;
}

function summarizeActivity(activity) {
    const details = getActivityDetails(activity);
    const firstTable = details.find((detail) => detail.items?.length);

    if (firstTable?.items?.length) {
        const firstItem = firstTable.items[0];

        if (firstTable.type === 'changes') {
            return `${firstItem.field}: ${firstItem.previous} -> ${firstItem.current}`;
        }

        return `${firstItem.field}: ${firstItem.current}`;
    }

    return activity.description;
}

function humanizeLogName(value) {
    return humanizeKey(String(value ?? '').replace(/_/g, ' '));
}

function humanizeActivityEvent(value) {
    if (!value) {
        return 'Recorded';
    }

    return humanizeKey(String(value));
}

function normalizeActivityValues(value) {
    if (!value) {
        return {};
    }

    if (typeof value === 'object' && !Array.isArray(value)) {
        return value;
    }

    return {};
}

function formatActivityValue(value) {
    if (value === null || value === undefined || value === '') {
        return 'None';
    }

    if (Array.isArray(value)) {
        return value.map((item) => formatActivityValue(item)).join(', ');
    }

    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }

    if (typeof value === 'object') {
        return Object.entries(value).map(([key, itemValue]) => `${humanizeKey(key)}: ${formatActivityValue(itemValue)}`).join(', ');
    }

    return String(value);
}

function CrudPage({ title, endpoint, formFactory, fields, searchPlaceholder = 'Search records', listKeys = null, extraRowActions = null }) {
    const [rows, setRows] = useState([]);
    const [form, setForm] = useState(formFactory());
    const [editingId, setEditingId] = useState(null);
    const [message, setMessage] = useState('');
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [search, setSearch] = useState('');
    const singularTitle = singularize(title);

    const load = async () => {
        const params = new URLSearchParams();
        if (search.trim()) {
            params.set('search', search.trim());
        }

        const response = await axios.get(`${endpoint}?${params.toString()}`);
        setRows(response.data);
    };

    useEffect(() => { load(); }, [endpoint, search]);

    const submit = async (event) => {
        event.preventDefault();
        setMessage('');

        const payload = {
            ...form,
            status: true,
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
                        <h2 className="text-2xl font-semibold text-white">{title}</h2>
                    </div>
                    <button className="btn btn-primary" onClick={() => {
                        setEditingId(null);
                        setForm(formFactory());
                        setMessage('');
                        setIsModalOpen(true);
                    }} type="button">Add {singularTitle}</button>
                </div>
                {message ? <p className="mt-5 rounded-2xl bg-white/8 px-4 py-3 text-sm text-slate-200">{message}</p> : null}
                <div className="mt-5 flex flex-wrap gap-3">
                    <input className="field flex-1 min-w-72" placeholder={searchPlaceholder} value={search} onChange={(event) => setSearch(event.target.value)} />
                    <button className="btn btn-secondary" onClick={() => setSearch('')} type="button">Clear</button>
                </div>
                <div className="mt-6 overflow-auto">
                    {rows.length ? (
                        <table className="min-w-full text-left text-sm text-slate-200">
                            <thead className="text-slate-400">
                                <tr>
                                    {(listKeys ?? Object.keys(rows[0] ?? {}).slice(0, 4)).map((key) => <th className="pb-3" key={key}>{humanizeKey(key)}</th>)}
                                    <th className="pb-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr className="border-t border-white/8" key={row.id}>
                                        {(listKeys ?? Object.keys(row).slice(0, 4)).map((key) => {
                                            const value = row[key];
                                            const displayValue = key.endsWith('_type') && row[`${key}_label`]
                                                ? row[`${key}_label`]
                                                : key === 'stream' && row.stream_label
                                                    ? row.stream_label
                                                    : Array.isArray(value)
                                                        ? value.map((item) => item.name ?? item.label ?? item).join(', ')
                                                        : String(value ?? '');

                                            return <td className="py-3" key={key}>{displayValue}</td>;
                                        })}
                                        <td className="py-3">
                                            <div className="flex gap-2">
                                                {extraRowActions ? extraRowActions(row) : null}
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
                    ) : (
                        <div className="rounded-3xl border border-dashed border-white/12 bg-slate-950/20 p-6 text-slate-300">
                            No records found.
                        </div>
                    )}
                </div>
            </section>

            <Modal isOpen={isModalOpen} title={`${editingId ? 'Edit' : 'Add'} ${singularTitle}`} onClose={() => setIsModalOpen(false)}>
                <form className="space-y-4" onSubmit={submit}>
                    {fields.map((field) => (
                        field.type === 'select' ? (
                            <select className="field" key={field.key} value={form[field.key] ?? ''} onChange={(event) => setForm({ ...form, [field.key]: event.target.value })}>
                                {field.options.map((option) => (
                                    <option key={typeof option === 'string' ? option : option.value} value={typeof option === 'string' ? option : option.value}>
                                        {typeof option === 'string' ? option : option.label}
                                    </option>
                                ))}
                            </select>
                        ) : field.type === 'multiselect' ? (
                            <Select
                                className="react-select-container"
                                classNamePrefix="react-select"
                                closeMenuOnSelect={false}
                                isMulti
                                key={field.key}
                                options={field.options.map((option) => typeof option === 'string' ? { value: option, label: option } : option)}
                                styles={selectStyles}
                                value={field.options
                                    .map((option) => typeof option === 'string' ? { value: option, label: option } : option)
                                    .filter((option) => (Array.isArray(form[field.key]) ? form[field.key] : []).includes(option.value))}
                                onChange={(selectedOptions) => setForm({
                                    ...form,
                                    [field.key]: (selectedOptions ?? []).map((option) => option.value),
                                })}
                            />
                        ) : (
                            <input
                                className="field"
                                key={field.key}
                                placeholder={field.label}
                                type={field.type === 'password' ? 'password' : field.type === 'email' ? 'email' : 'text'}
                                value={form[field.key] ?? ''}
                                onChange={(event) => setForm({ ...form, [field.key]: event.target.value })}
                            />
                        )
                    ))}
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
                        <p className="text-sm uppercase tracking-[0.3em] brand-kicker">
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
                        <article className={clsx('rounded-3xl border p-5', notification.read_at ? 'border-white/8 bg-slate-950/20' : 'border-[rgba(127,244,228,0.18)] bg-[rgba(0,169,158,0.08)]')} key={notification.id}>
                            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <p className="text-lg font-semibold text-white">{notification.data.title ?? notification.type}</p>
                                    <p className="mt-2 text-sm text-slate-300">{notification.data.message ?? 'Notification received.'}</p>
                                    <p className="mt-3 text-xs uppercase tracking-[0.2em] text-slate-400">{dayjs(notification.created_at).format('DD MMM YYYY, hh:mm A')}</p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className={clsx('rounded-full px-3 py-1 text-xs uppercase tracking-[0.2em]', notification.read_at ? 'brand-badge-soft' : 'brand-badge')}>
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

function DeadlineCountdown({ deadline }) {
    const [nowValue, setNowValue] = useState(Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNowValue(Date.now()), 1000);

        return () => window.clearInterval(timer);
    }, []);

    const remainingMs = Math.max(dayjs(deadline).valueOf() - nowValue, 0);
    const totalSeconds = Math.floor(remainingMs / 1000);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return (
        <div className="mt-4 flex items-center gap-2 overflow-x-auto pb-1">
            <span className="inline-flex shrink-0 items-center gap-2 rounded-full brand-badge px-3 py-1.5 text-xs uppercase tracking-[0.18em]">
                <ClockIcon className="h-4 w-4" />
                <span>Deadline Countdown</span>
            </span>
            {[
                { label: 'Days', value: days },
                { label: 'Hours', value: hours },
                { label: 'Minutes', value: minutes },
                { label: 'Seconds', value: seconds },
            ].map((item) => (
                <div className="shrink-0 rounded-2xl border border-white/10 bg-slate-950/25 px-2.5 py-2 text-center" key={item.label}>
                    <p className="text-base font-semibold text-white">{String(item.value).padStart(2, '0')}</p>
                    <p className="text-[0.65rem] uppercase tracking-[0.18em] text-slate-400">{item.label}</p>
                </div>
            ))}
        </div>
    );
}

function ReportTable({ title, rows, hiddenHeaders = [], getRowActions = null }) {
    const headers = Object.keys(rows[0] ?? {}).filter((header) => !header.endsWith('_label') && !hiddenHeaders.includes(header));

    return (
        <section className="glass-panel rounded-[2rem] p-8">
            <h3 className="text-xl font-semibold text-white">{title}</h3>
            <div className="mt-6 overflow-auto">
                {rows.length ? (
                    <table className="min-w-full text-left text-sm text-slate-200">
                        <thead className="text-slate-400">
                            <tr>
                                {headers.map((header) => <th className="pb-3 pr-4" key={header}>{humanizeKey(header)}</th>)}
                                {getRowActions ? <th className="pb-3 pr-4">Actions</th> : null}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr className="border-t border-white/8" key={index}>
                                    {headers.map((header) => {
                                        const value = header.endsWith('_type') && row[`${header}_label`]
                                            ? row[`${header}_label`]
                                            : header === 'stream' && row.stream_label
                                                ? row.stream_label
                                                : row[header];

                                        if (header === 'loe_status') {
                                            return (
                                                <td className="py-3 pr-4" key={header}>
                                                    <span className={clsx('report-status-badge', `report-status-${row.loe_status_tone ?? 'good'}`)}>
                                                        {String(value ?? '')}
                                                    </span>
                                                </td>
                                            );
                                        }

                                        if (header === 'submitted_at') {
                                            return <td className="py-3 pr-4" key={header}>{value ? dayjs(value).format('DD MMM YYYY, hh:mm A') : ''}</td>;
                                        }

                                        return <td className="py-3 pr-4" key={header}>{formatDisplayValue(header, value)}</td>;
                                    })}
                                    {getRowActions ? <td className="py-3 pr-4">{getRowActions(row)}</td> : null}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                ) : (
                    <div className="rounded-3xl border border-dashed border-white/12 bg-slate-950/20 p-6 text-slate-300">
                        No records found.
                    </div>
                )}
            </div>
        </section>
    );
}

function FeedbackThreadModal({ isOpen, report, title, onClose, onPosted }) {
    const [feedback, setFeedback] = useState([]);
    const [message, setMessage] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!isOpen || !report) {
            setFeedback([]);
            setMessage('');
            setError('');
            return;
        }

        setFeedback(report.feedback ?? []);
        setLoading(true);
        axios.get(`/api/loe-reports/${report.id}/feedback`)
            .then((response) => setFeedback(response.data.feedback ?? []))
            .catch(() => setError('Unable to load feedback right now.'))
            .finally(() => setLoading(false));
    }, [isOpen, report]);

    if (!report) {
        return null;
    }

    const submit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setError('');

        try {
            const response = await axios.post(`/api/loe-reports/${report.id}/feedback`, { message });
            setFeedback((current) => [...current, response.data]);
            setMessage('');
            await onPosted?.();
        } catch (requestError) {
            setError(requestError.response?.data?.message ?? 'Unable to send feedback right now.');
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <Modal isOpen={isOpen} title={title} onClose={onClose}>
            <div className="space-y-5">
                <div className="rounded-3xl border border-white/10 bg-slate-950/25 p-5">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-sm uppercase tracking-[0.2em] text-slate-400">LOE Feedback Thread</p>
                            <p className="mt-2 text-lg font-semibold text-white">{dayjs(`${report.year}-${String(report.month).padStart(2, '0')}-01`).format('MMMM YYYY')}</p>
                        </div>
                        <div className="text-right">
                            <p className="text-sm text-slate-400">Total LOE</p>
                            <p className="mt-2 text-lg font-semibold text-white">{formatPercentage(report.total_percentage ?? 0)}</p>
                        </div>
                    </div>
                </div>

                <div className="space-y-3">
                    {loading ? <div className="rounded-3xl border border-white/10 bg-slate-950/20 p-5 text-slate-300">Loading feedback...</div> : null}
                    {!loading && feedback.length ? feedback.map((item) => {
                        const isAdminAuthor = item.author?.roles?.includes('admin');

                        return (
                            <article className={clsx('rounded-3xl border p-5', isAdminAuthor ? 'border-[rgba(127,244,228,0.18)] bg-[rgba(0,169,158,0.08)]' : 'border-white/10 bg-slate-950/25')} key={item.id}>
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="font-semibold text-white">{item.author?.name}</p>
                                        <p className="mt-1 text-xs uppercase tracking-[0.18em] text-slate-400">{isAdminAuthor ? 'Admin' : 'Employee'}</p>
                                    </div>
                                    <p className="text-xs uppercase tracking-[0.18em] text-slate-400">{item.created_at ? dayjs(item.created_at).format('DD MMM YYYY, hh:mm A') : ''}</p>
                                </div>
                                <p className="mt-3 whitespace-pre-wrap text-sm text-slate-200">{item.message}</p>
                            </article>
                        );
                    }) : null}
                    {!loading && !feedback.length ? (
                        <div className="rounded-3xl border border-dashed border-white/12 bg-slate-950/20 p-6 text-slate-300">
                            No feedback yet. Start the conversation here.
                        </div>
                    ) : null}
                </div>

                <form className="space-y-3" onSubmit={submit}>
                    <textarea
                        className="field min-h-32 resize-y"
                        placeholder="Write your feedback or reply"
                        value={message}
                        onChange={(event) => setMessage(event.target.value)}
                    />
                    {error ? <p className="rounded-2xl bg-rose-500/15 px-4 py-3 text-sm text-rose-200">{error}</p> : null}
                    <div className="flex justify-end">
                        <button className="btn btn-primary" disabled={submitting || !message.trim()} type="submit">
                            {submitting ? 'Sending...' : 'Send feedback'}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
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

function humanizeStatus(value) {
    if (!value) {
        return 'Unknown';
    }

    return humanizeKey(String(value));
}

function statusBadgeClass(status) {
    switch (status) {
        case 'approved':
            return 'bg-emerald-500/15 text-emerald-200';
        case 'submitted':
            return 'bg-sky-500/15 text-sky-200';
        case 'draft':
            return 'bg-amber-500/15 text-amber-100';
        case 'overdue':
            return 'bg-rose-500/15 text-rose-200';
        default:
            return 'brand-badge-soft';
    }
}

function getLoeWarnings(totalPercentage, allocationTotal = 0) {
    const warnings = [];

    if (totalPercentage < 50 || totalPercentage > 110) {
        warnings.push({
            level: 'critical',
            message: `Total LOE is ${formatPercentage(totalPercentage)}, which is outside the safe 50%-110% range.`,
        });
    } else if (totalPercentage < 90) {
        warnings.push({
            level: 'medium',
            message: `Total LOE is ${formatPercentage(totalPercentage)}, which is below the preferred 90%-110% range.`,
        });
    }

    if (allocationTotal > 0 && Math.abs(allocationTotal - totalPercentage) >= 20) {
        warnings.push({
            level: 'medium',
            message: `LOE total differs from current allocations by ${formatPercentage(Math.abs(allocationTotal - totalPercentage))}.`,
        });
    }

    return warnings;
}

function formatPercentage(value) {
    const numericValue = Number(value);

    if (Number.isNaN(numericValue)) {
        return value === null || value === undefined || value === '' ? '' : `${value}%`;
    }

    return `${numericValue % 1 === 0 ? numericValue.toFixed(0) : numericValue.toFixed(2)}%`;
}

function formatDisplayValue(header, value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (header.includes('percentage') || header === 'variance') {
        return formatPercentage(value);
    }

    return String(value);
}

function humanizeKey(value) {
    return String(value)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (match) => match.toUpperCase());
}

const selectStyles = {
    control: (base, state) => ({
        ...base,
        minHeight: 42,
        backgroundColor: 'rgba(2, 16, 20, 0.62)',
        borderColor: state.isFocused ? 'rgba(127, 244, 228, 0.55)' : 'rgba(0, 169, 158, 0.18)',
        boxShadow: state.isFocused ? '0 0 0 3px rgba(0, 169, 158, 0.18)' : 'none',
        borderRadius: 16,
        padding: '2px 4px',
        '&:hover': {
            borderColor: 'rgba(127, 244, 228, 0.55)',
        },
    }),
    menu: (base) => ({
        ...base,
        backgroundColor: '#092126',
        border: '1px solid rgba(127, 244, 228, 0.16)',
        borderRadius: 16,
        overflow: 'hidden',
    }),
    menuList: (base) => ({
        ...base,
        padding: 6,
    }),
    option: (base, state) => ({
        ...base,
        backgroundColor: state.isSelected
            ? 'rgba(0, 169, 158, 0.28)'
            : state.isFocused
                ? 'rgba(127, 244, 228, 0.12)'
                : 'transparent',
        color: '#f3fffd',
        borderRadius: 10,
        cursor: 'pointer',
    }),
    placeholder: (base) => ({
        ...base,
        color: '#94a3b8',
    }),
    singleValue: (base) => ({
        ...base,
        color: '#f3fffd',
    }),
    input: (base) => ({
        ...base,
        color: '#f3fffd',
    }),
    multiValue: (base) => ({
        ...base,
        backgroundColor: 'rgba(0, 169, 158, 0.16)',
        borderRadius: 999,
    }),
    multiValueLabel: (base) => ({
        ...base,
        color: '#d7fff9',
        fontSize: '0.8rem',
        padding: '4px 8px',
    }),
    multiValueRemove: (base) => ({
        ...base,
        color: '#d7fff9',
        borderRadius: 999,
        ':hover': {
            backgroundColor: 'rgba(127, 244, 228, 0.18)',
            color: '#ffffff',
        },
    }),
    dropdownIndicator: (base, state) => ({
        ...base,
        color: state.isFocused ? '#7ff4e4' : '#94a3b8',
        ':hover': {
            color: '#7ff4e4',
        },
    }),
    clearIndicator: (base) => ({
        ...base,
        color: '#94a3b8',
        ':hover': {
            color: '#7ff4e4',
        },
    }),
    indicatorSeparator: (base) => ({
        ...base,
        backgroundColor: 'rgba(127, 244, 228, 0.12)',
    }),
};

const monthOptions = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const yearOptions = Array.from({ length: 81 }, (_, index) => 2020 + index);

const activityEventOptions = [
    { value: 'created', label: 'Created' },
    { value: 'updated', label: 'Updated' },
    { value: 'deleted', label: 'Deleted' },
    { value: 'role_synced', label: 'Role Synced' },
    { value: 'exported', label: 'Exported' },
];

const activityModuleOptions = [
    { value: 'users', label: 'Users' },
    { value: 'projects', label: 'Projects' },
    { value: 'allocations', label: 'Allocations' },
    { value: 'loe_reports', label: 'LOE Reports' },
    { value: 'loe_entries', label: 'LOE Entries' },
    { value: 'loe_feedback', label: 'LOE Feedback' },
    { value: 'exports', label: 'Exports' },
    { value: 'roles', label: 'Roles' },
];

const activitySubjectOptions = [
    { value: 'User', label: 'User' },
    { value: 'Project', label: 'Project' },
    { value: 'Allocation', label: 'Allocation' },
    { value: 'LoeReport', label: 'LOE Report' },
    { value: 'LoeEntry', label: 'LOE Entry' },
    { value: 'LoeFeedback', label: 'LOE Feedback' },
    { value: 'Role', label: 'Role' },
];
