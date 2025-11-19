import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { AppShell } from '@/components/app-shell';
import { AppHeader } from '@/components/app-header';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard() {
    return (
        <AppShell variant="header">
            <AppHeader breadcrumbs={breadcrumbs} />
            <Head title="Dashboard" />
            <div className="container-custom py-10">
                <div className="mb-8">
                    <h1 className="text-3xl font-bold text-gray-900">Dashboard Overview</h1>
                    <p className="text-gray-500">Welcome back! Here's what's happening today.</p>
                </div>

                <div className="grid auto-rows-min gap-6 md:grid-cols-3 mb-8">
                    <div className="glass-card relative aspect-video overflow-hidden rounded-2xl p-6">
                        <div className="absolute inset-0 -z-10 bg-gradient-to-br from-primary-50 to-white opacity-50"></div>
                        <h3 className="font-semibold text-gray-700">Total Saldo</h3>
                        <p className="mt-2 text-3xl font-bold text-primary-600">Rp 1.250.000</p>
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-primary-900/5 opacity-30" />
                    </div>
                    <div className="glass-card relative aspect-video overflow-hidden rounded-2xl p-6">
                        <div className="absolute inset-0 -z-10 bg-gradient-to-br from-secondary-50 to-white opacity-50"></div>
                        <h3 className="font-semibold text-gray-700">Sampah Terkumpul</h3>
                        <p className="mt-2 text-3xl font-bold text-secondary-600">45 kg</p>
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-secondary-900/5 opacity-30" />
                    </div>
                    <div className="glass-card relative aspect-video overflow-hidden rounded-2xl p-6">
                        <div className="absolute inset-0 -z-10 bg-gradient-to-br from-accent-50 to-white opacity-50"></div>
                        <h3 className="font-semibold text-gray-700">Transaksi Bulan Ini</h3>
                        <p className="mt-2 text-3xl font-bold text-accent-600">12</p>
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-accent-900/5 opacity-30" />
                    </div>
                </div>
                
                <div className="glass-card relative min-h-[50vh] flex-1 overflow-hidden rounded-2xl border border-gray-100 p-8">
                    <h3 className="mb-6 text-xl font-bold text-gray-900">Riwayat Transaksi Terakhir</h3>
                    <div className="rounded-xl border border-gray-100 bg-gray-50/50 p-8 text-center">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/10 opacity-20" />
                        <p className="relative z-10 text-gray-500">Belum ada data transaksi terbaru.</p>
                    </div>
                </div>
            </div>
        </AppShell>
    );
}
