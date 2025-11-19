import { Link } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { MouseEventHandler, ReactNode } from 'react';

interface ButtonProps {
    children: ReactNode;
    variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
    size?: 'sm' | 'md' | 'lg' | 'icon';
    href?: string;
    onClick?: MouseEventHandler<HTMLButtonElement>;
    disabled?: boolean;
    loading?: boolean;
    className?: string;
    [key: string]: any; // for other props
}

const Button = ({ children, variant = 'primary', size = 'md', href, onClick, disabled = false, loading = false, className = '', ...props }: ButtonProps) => {
    const baseClasses =
        'inline-flex items-center justify-center font-medium transition-all duration-200 focus:outline-none focus:ring-4 disabled:opacity-50 disabled:cursor-not-allowed active:scale-95';

    const variants = {
        primary: 'bg-gradient-to-r from-primary-600 to-primary-500 text-white shadow-lg shadow-primary-500/30 hover:-translate-y-0.5 hover:shadow-primary-500/40 focus:ring-primary-500/20',
        secondary: 'bg-white text-gray-800 shadow-md hover:-translate-y-0.5 hover:bg-gray-50 hover:shadow-lg focus:ring-gray-200',
        outline: 'border-2 border-primary-500 text-primary-600 hover:bg-primary-50 focus:ring-primary-500/20',
        ghost: 'text-gray-600 hover:bg-gray-100 hover:text-primary-600 focus:ring-primary-500/20',
        danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500/20 shadow-sm',
    };

    const sizes = {
        sm: 'px-3 py-1.5 text-sm rounded-lg',
        md: 'px-6 py-2.5 text-base rounded-xl',
        lg: 'px-8 py-3.5 text-lg rounded-2xl',
        icon: 'p-2 rounded-full aspect-square',
    };

    const classes = `${baseClasses} ${variants[variant]} ${sizes[size]} ${className}`;

    const content = (
        <>
            {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {children}
        </>
    );

    if (href) {
        return (
            <Link href={href} className={classes} {...props}>
                {content}
            </Link>
        );
    }

    return (
        <button className={classes} onClick={onClick} disabled={disabled || loading} {...props}>
            {content}
        </button>
    );
};

export default Button;
