import React from 'react';
import { cn } from '@/lib/utils';

interface HeadingProps extends React.HTMLAttributes<HTMLHeadingElement> {
  as?: 'h1' | 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'hero';
  gradient?: boolean;
  children: React.ReactNode;
}

const sizeClasses = {
  xs: 'text-xs sm:text-sm font-semibold tracking-normal',
  sm: 'text-sm sm:text-base font-bold tracking-tight',
  md: 'text-base sm:text-lg font-bold tracking-tight',
  lg: 'text-lg sm:text-xl font-bold tracking-tight',
  xl: 'text-xl sm:text-2xl font-extrabold tracking-tight',
  '2xl': 'text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight',
  hero: 'text-3xl sm:text-5xl lg:text-6xl font-black tracking-tight',
};

export default function Heading({
  as: Component = 'h2',
  size = 'xl',
  gradient = false,
  className,
  children,
  ...props
}: HeadingProps) {
  return (
    <Component
      className={cn(
        'text-white',
        sizeClasses[size],
        gradient &&
          'bg-gradient-to-r from-[#fff0cc] via-[#e5b869] to-[#c99540] bg-clip-text text-transparent drop-shadow-[0_2px_12px_rgba(229,184,105,0.35)]',
        className
      )}
      {...props}
    >
      {children}
    </Component>
  );
}

