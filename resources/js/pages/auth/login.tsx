import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
};

type SocialProvider = {
    name: string;
    label: string;
};

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    socialProviders?: SocialProvider[];
    socialiteError?: string;
}

export default function Login({
    status,
    canResetPassword,
    socialProviders = [],
    socialiteError,
}: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<Required<LoginForm>>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const renderProviderIcon = (providerName: string) => {
        if (providerName === 'google') {
            return (
                <svg
                    className="h-4 w-4"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    focusable="false"
                >
                    <path
                        fill="#EA4335"
                        d="M12 10.2v4.25h6.02c-.26 1.35-.97 2.49-2.07 3.24l3.35 2.6c1.95-1.8 3.08-4.45 3.08-7.84 0-.74-.07-1.45-.21-2.13H12z"
                    />
                    <path
                        fill="#34A853"
                        d="M5.27 14.32 4.38 15l-2.66 2.07C3.78 19.34 7.57 21.5 12 21.5c3.24 0 5.96-1.07 7.95-2.82l-3.35-2.6c-.9.6-2.05.96-3.6.96-2.77 0-5.13-1.87-5.97-4.45z"
                    />
                    <path
                        fill="#4A90E2"
                        d="M2 7.5C1.05 9.18.5 11.04.5 13s.55 3.82 1.5 5.5l3.77-2.96A6.94 6.94 0 0 1 4.5 13c0-.85.15-1.67.27-2.5z"
                    />
                    <path
                        fill="#FBBC05"
                        d="M12 5.5c1.76 0 3.34.61 4.58 1.8l3.4-3.4C17.94 1.76 15.23.5 12 .5 7.57.5 3.78 2.66 1.72 5.93L5.5 8.9C6.35 6.37 9.23 5.5 12 5.5z"
                    />
                </svg>
            );
        }

        return null;
    };

    return (
        <AuthLayout title="Log in to your account" description="Enter your email and password below to log in">
            <Head title="Log in" />

            {(status || socialiteError) && (
                <div className="mb-4 flex flex-col gap-3">
                    {status && (
                        <div className="rounded-md border border-green-500/30 bg-green-100/80 p-3 text-sm text-green-700">
                            {status}
                        </div>
                    )}
                    {socialiteError && (
                        <div className="rounded-md border border-red-500/30 bg-red-100/80 p-3 text-sm text-red-700">
                            {socialiteError}
                        </div>
                    )}
                </div>
            )}

            {socialProviders.length > 0 && (
                <div className="mb-6 flex flex-col gap-3">
                    {socialProviders.map((provider) => (
                        <Button
                            key={provider.name}
                            type="button"
                            variant="outline"
                            className="w-full"
                            asChild
                        >
                            <a href={route('socialite.redirect', { provider: provider.name })} className="flex items-center justify-center gap-2">
                                {renderProviderIcon(provider.name)}
                                Continue with {provider.label}
                            </a>
                        </Button>
                    ))}

                    <div className="relative py-1 text-center text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        <div className="absolute inset-0 flex items-center" aria-hidden="true">
                            <span className="h-px w-full bg-border" />
                        </div>
                        <span className="relative bg-background px-3">or continue with email</span>
                    </div>
                </div>
            )}

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            autoFocus
                            tabIndex={1}
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            placeholder="email@example.com"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center">
                            <Label htmlFor="password">Password</Label>
                            {canResetPassword && (
                                <TextLink href={route('password.request')} className="ml-auto text-sm" tabIndex={5}>
                                    Forgot password?
                                </TextLink>
                            )}
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="Password"
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center space-x-3">
                        <Checkbox
                            id="remember"
                            name="remember"
                            checked={data.remember}
                            onClick={() => setData('remember', !data.remember)}
                            tabIndex={3}
                        />
                        <Label htmlFor="remember">Remember me</Label>
                    </div>

                    <Button type="submit" className="mt-4 w-full" tabIndex={4} disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Log in
                    </Button>
                </div>

                <div className="text-center text-sm text-muted-foreground">
                    Don't have an account?{' '}
                    <TextLink href={route('register')} tabIndex={5}>
                        Sign up
                    </TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
