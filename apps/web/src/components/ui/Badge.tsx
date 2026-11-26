import React from 'react';
import { cn } from '@/lib/utils';
import { Star, Crown } from 'lucide-react';

interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  variant?: 'vip' | 'live' | 'rating' | 'pill' | 'category' | 'default';
  rating?: string | number;
  children?: React.ReactNode;
}

export default function Badge({
  variant = 'default',
  rating,
  className,
  children,
  ...props
}: BadgeProps) {
  if (variant === 'vip') {
    return (
      <span
        className={cn(
          'inline-flex items-center space-x-1 bg-[#E5B869] text-black font-extrabold text-[10px] px-2 py-0.5 rounded-full uppercase tracking-wider shadow-sm select-none',
          className
        )}
        {...props}
      >
        <Crown className="w-2.5 h-2.5 fill-black text-black" />
        <span>{children || 'VIP'}</span>
      </span>
    );
  }

  if (variant === 'live') {
    return (
      <span
        className={cn(
          'inline-flex items-center space-x-1.5 bg-red-600/20 border border-red-500/40 text-red-400 font-bold text-[10px] px-2.5 py-0.5 rounded-full uppercase tracking-wider select-none',
          className
        )}
        {...props}
      >
        <span className="relative flex h-1.5 w-1.5">
          <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75" />
          <span className="relative inline-flex rounded-full h-1.5 w-1.5 bg-red-500" />
        </span>
        <span>{children || 'LIVE'}</span>
      </span>
    );
  }

  if (variant === 'rating') {
    return (
      <span
        className={cn(
          'inline-flex items-center text-[#E5B869] text-[10px] sm:text-[11px] font-semibold bg-black/60 px-1.5 py-0.5 rounded border border-white/10 select-none',
          className
        )}
        {...props}
      >
        <Star className="w-2.5 h-2.5 fill-[#E5B869] text-[#E5B869] mr-0.5" />
        <span>{rating || children}</span>
      </span>
    );
  }

  if (variant === 'category') {
    return (
      <span
        className={cn(
          'inline-flex items-center text-[11px] font-semibold text-gray-300 bg-white/5 border border-white/10 px-2.5 py-0.5 rounded-full select-none',
          className
        )}
        {...props}
      >
        {children}
      </span>
    );
  }

  return (
    <span
      className={cn(
        'inline-flex items-center text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-white/10 text-gray-200 select-none',
        className
      )}
      {...props}
    >
      {children}
    </span>
  );
}

