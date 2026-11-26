import React from 'react';
import { cn } from '@/lib/utils';

interface TextProps extends React.HTMLAttributes<HTMLParagraphElement> {
  as?: 'p' | 'span' | 'div';
  variant?: 'body' | 'caption' | 'subtitle' | 'muted';
  size?: 'xs' | 'sm' | 'base' | 'lg';
  children: React.ReactNode;
}

const variantClasses = {
  body: 'text-gray-300 leading-relaxed',
  caption: 'text-gray-400 text-xs leading-normal',
  subtitle: 'text-gray-400 leading-relaxed',
  muted: 'text-gray-500 leading-normal',
};

const sizeClasses = {
  xs: 'text-[11px] sm:text-xs',
  sm: 'text-xs sm:text-sm',
  base: 'text-sm sm:text-base',
  lg: 'text-base sm:text-lg',
};

export default function Text({
  as: Component = 'p',
  variant = 'body',
  size = 'sm',
  className,
  children,
  ...props
}: TextProps) {
  return (
    <Component
      className={cn(variantClasses[variant], sizeClasses[size], className)}
      {...props}
    >
      {children}
    </Component>
  );
}

