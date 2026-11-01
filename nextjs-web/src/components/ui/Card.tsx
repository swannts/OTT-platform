import React from 'react';
import { cn } from '@/lib/utils';

interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: 'media' | 'glass' | 'solid' | 'panel';
  hoverEffect?: boolean;
  children: React.ReactNode;
}

export default function Card({
  variant = 'media',
  hoverEffect = true,
  className,
  children,
  ...props
}: CardProps) {
  const variantStyles = {
    media: 'bg-[#0c1218] border border-white/[0.08] shadow-lg rounded-xl sm:rounded-2xl overflow-hidden',
    glass: 'bg-[#0c1218]/80 backdrop-blur-md border border-white/10 rounded-2xl shadow-xl',
    solid: 'bg-[#0c1218] border border-white/10 rounded-2xl shadow-xl',
    panel: 'bg-white/[0.03] border border-white/5 rounded-2xl p-5 sm:p-6',
  };

  return (
    <div
      className={cn(
        variantStyles[variant],
        hoverEffect && 'transition-all duration-300 hover:border-[#E5B869]/50 hover:shadow-2xl',
        className
      )}
      {...props}
    >
      {children}
    </div>
  );
}
