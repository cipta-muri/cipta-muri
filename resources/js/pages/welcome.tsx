import { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Leaf, Recycle, TrendingUp, Users } from 'lucide-react';
import Button from '@/components/Button';
import { AppHeader } from '@/components/app-header';

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Bank Sampah Digital Cipta Muri" />

            <div className="min-h-screen bg-white font-sans text-gray-900">
                <AppHeader />

                {/* Hero Section */}
                <section className="relative overflow-hidden pt-32 pb-20 lg:pt-48 lg:pb-32">
                    <div className="absolute top-0 left-0 -z-10 h-full w-full bg-white bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] [background-size:16px_16px] opacity-50"></div>
                    <div className="absolute top-0 right-0 -z-10 h-[500px] w-[500px] rounded-full bg-primary-100/50 blur-3xl filter"></div>
                    <div className="absolute bottom-0 left-0 -z-10 h-[500px] w-[500px] rounded-full bg-accent-100/50 blur-3xl filter"></div>

                    <div className="container-custom relative z-10">
                        <div className="mx-auto max-w-4xl text-center">
                            <div className="mb-6 inline-flex items-center rounded-full border border-primary-200 bg-primary-50 px-3 py-1 text-sm font-medium text-primary-700 shadow-sm animate-fade-in">
                                <span className="mr-2 flex h-2 w-2 rounded-full bg-primary-500"></span>
                                Solusi Lingkungan Masa Depan
                            </div>
                            <h1 className="mb-8 text-5xl font-bold tracking-tight text-gray-900 sm:text-6xl lg:text-7xl animate-slide-up">
                                Ubah Sampah Menjadi <br />
                                <span className="text-gradient">Tabungan Berharga</span>
                            </h1>
                            <p className="mb-10 text-xl text-gray-600 sm:px-16 lg:px-24 animate-slide-up animation-delay-200">
                                Bergabunglah dengan gerakan Bank Sampah Digital Cipta Muri. Kelola sampah rumah tangga Anda, dukung ekonomi sirkular, dan dapatkan keuntungan finansial nyata.
                            </p>
                            <div className="flex flex-col items-center justify-center gap-4 sm:flex-row animate-slide-up animation-delay-400">
                                {auth.user ? (
                                    <Button href={route('dashboard')} size="lg" className="w-full sm:w-auto">
                                        Ke Dashboard
                                        <ArrowRight className="ml-2 h-5 w-5" />
                                    </Button>
                                ) : (
                                    <>
                                        <Button href={route('register')} size="lg" className="w-full sm:w-auto">
                                            Mulai Sekarang
                                            <ArrowRight className="ml-2 h-5 w-5" />
                                        </Button>
                                        <Button href="#learn-more" variant="outline" size="lg" className="w-full sm:w-auto">
                                            Pelajari Lebih Lanjut
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </section>

                {/* Stats Section */}
                <section className="border-y border-gray-100 bg-white/50 py-12 backdrop-blur-sm">
                    <div className="container-custom">
                        <div className="grid grid-cols-2 gap-8 md:grid-cols-4">
                            {[
                                { label: 'Nasabah Aktif', value: '1,200+', icon: Users },
                                { label: 'Sampah Terkelola', value: '50 Ton', icon: Recycle },
                                { label: 'Total Transaksi', value: 'Rp 500jt+', icon: TrendingUp },
                                { label: 'Mitra Pengepul', value: '25+', icon: Leaf },
                            ].map((stat, index) => (
                                <div key={index} className="flex flex-col items-center text-center">
                                    <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-50 text-primary-600">
                                        <stat.icon className="h-6 w-6" />
                                    </div>
                                    <dt className="text-3xl font-bold text-gray-900">{stat.value}</dt>
                                    <dd className="text-sm font-medium text-gray-500">{stat.label}</dd>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section id="learn-more" className="section-padding bg-gray-50">
                    <div className="container-custom">
                        <div className="mb-16 text-center">
                            <h2 className="mb-4 text-3xl font-bold text-gray-900 sm:text-4xl">Kenapa Memilih Cipta Muri?</h2>
                            <p className="mx-auto max-w-2xl text-lg text-gray-600">
                                Platform kami dirancang untuk memudahkan Anda dalam berpartisipasi menjaga lingkungan sambil mendapatkan manfaat ekonomi.
                            </p>
                        </div>

                        <div className="grid gap-8 md:grid-cols-3">
                            {[
                                {
                                    title: 'Mudah & Cepat',
                                    description: 'Proses pendaftaran dan penyetoran sampah yang simpel dan cepat melalui aplikasi digital.',
                                    icon: CheckCircle2,
                                },
                                {
                                    title: 'Transparan',
                                    description: 'Pantau saldo tabungan dan riwayat transaksi Anda secara real-time kapan saja.',
                                    icon: TrendingUp,
                                },
                                {
                                    title: 'Ramah Lingkungan',
                                    description: 'Setiap kilogram sampah yang Anda setor berkontribusi langsung pada kelestarian bumi.',
                                    icon: Leaf,
                                },
                            ].map((feature, index) => (
                                <div key={index} className="glass-card rounded-2xl p-8">
                                    <div className="mb-6 inline-flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-lg shadow-primary-500/30">
                                        <feature.icon className="h-7 w-7" />
                                    </div>
                                    <h3 className="mb-3 text-xl font-bold text-gray-900">{feature.title}</h3>
                                    <p className="text-gray-600 leading-relaxed">{feature.description}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* CTA Section */}
                <section className="section-padding relative overflow-hidden bg-gray-900 py-24 text-white">
                    <div className="absolute inset-0 -z-10 bg-[url('https://images.unsplash.com/photo-1532996122724-e3c354a0b15b?q=80&w=2070&auto=format&fit=crop')] bg-cover bg-center opacity-20 mix-blend-overlay"></div>
                    <div className="absolute inset-0 -z-10 bg-gradient-to-br from-primary-900/90 to-gray-900/90"></div>

                    <div className="container-custom text-center">
                        <h2 className="mb-6 text-4xl font-bold tracking-tight sm:text-5xl">Siap Menjadi Pahlawan Lingkungan?</h2>
                        <p className="mx-auto mb-10 max-w-2xl text-xl text-gray-300">
                            Jangan biarkan sampah menumpuk. Ubah menjadi peluang dan berkontribusi untuk masa depan yang lebih hijau hari ini.
                        </p>
                        <div className="flex flex-col justify-center gap-4 sm:flex-row">
                            <Button href={route('register')} size="lg" className="bg-white text-primary-700 hover:bg-gray-100 hover:text-primary-800 shadow-none border-0">
                                Daftar Sekarang Gratis
                            </Button>
                            <Button href={route('login')} variant="outline" size="lg" className="border-white text-white hover:bg-white/10 hover:text-white">
                                Masuk Akun
                            </Button>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="bg-white pt-16 pb-8 border-t border-gray-100">
                    <div className="container-custom">
                        <div className="grid gap-12 md:grid-cols-4 mb-12">
                            <div className="col-span-1 md:col-span-2">
                                <Link href="/" className="mb-6 flex items-center gap-2">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-600 text-white">
                                        <Recycle className="h-6 w-6" />
                                    </div>
                                    <span className="text-xl font-bold text-gray-900">Cipta Muri</span>
                                </Link>
                                <p className="max-w-md text-gray-500 leading-relaxed">
                                    Bank Sampah Digital Cipta Muri adalah platform inovatif yang menghubungkan masyarakat dengan solusi pengelolaan sampah berkelanjutan.
                                </p>
                            </div>
                            <div>
                                <h4 className="mb-6 text-sm font-bold uppercase tracking-wider text-gray-900">Menu</h4>
                                <ul className="space-y-4 text-gray-500">
                                    <li><a href="#" className="hover:text-primary-600 transition-colors">Beranda</a></li>
                                    <li><a href="#" className="hover:text-primary-600 transition-colors">Tentang Kami</a></li>
                                    <li><a href="#" className="hover:text-primary-600 transition-colors">Layanan</a></li>
                                    <li><a href="#" className="hover:text-primary-600 transition-colors">Kontak</a></li>
                                </ul>
                            </div>
                            <div>
                                <h4 className="mb-6 text-sm font-bold uppercase tracking-wider text-gray-900">Legal</h4>
                                <ul className="space-y-4 text-gray-500">
                                    <li><a href="#" className="hover:text-primary-600 transition-colors">Kebijakan Privasi</a></li>
                                    <li><a href="#" className="hover:text-primary-600 transition-colors">Syarat & Ketentuan</a></li>
                                </ul>
                            </div>
                        </div>
                        <div className="border-t border-gray-100 pt-8 text-center text-sm text-gray-400">
                            &copy; {new Date().getFullYear()} Bank Sampah Digital Cipta Muri. All rights reserved.
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
