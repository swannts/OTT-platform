import React from 'react';
import { cn } from '@/lib/utils';

export interface SpinnerProps extends React.HTMLAttributes<HTMLDivElement> {
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
  variant?: 'gold' | 'white' | 'muted';
  label?: string;
  fullScreen?: boolean;
}

const sizeClasses: Record<NonNullable<SpinnerProps['size']>, { spinner: string; text: string }> = {
  xs: { spinner: 'w-3.5 h-3.5 border-2', text: 'text-xs' },
  sm: { spinner: 'w-5 h-5 border-2', text: 'text-xs' },
  md: { spinner: 'w-8 h-8 border-[3px]', text: 'text-sm' },
  lg: { spinner: 'w-11 h-11 border-4', text: 'text-base' },
  xl: { spinner: 'w-16 h-16 border-[5px]', text: 'text-lg' },
};

const variantClasses: Record<NonNullable<SpinnerProps['variant']>, string> = {
  gold: 'border-[#E5B869]/25 border-t-[#E5B869] drop-shadow-[0_0_12px_rgba(229,184,105,0.45)]',
  white: 'border-white/20 border-t-white',
  muted: 'border-white/10 border-t-white/60',
};

export const Spinner: React.FC<SpinnerProps> = ({
  size = 'md',
  variant = 'gold',
  label,
  fullScreen = false,
  className,
  ...props
}) => {
  const { spinner: spinnerSize, text: textSize } = sizeClasses[size];

  const content = (
    <div
      role="status"
      aria-live="polite"
      className={cn('inline-flex flex-col items-center justify-center gap-3', className)}
      {...props}
    >
      <div
        className={cn(
          'rounded-full animate-spin transition-all',
          spinnerSize,
          variantClasses[variant]
        )}
      />
      {label ? (
        <p className={cn('text-gray-300 font-medium tracking-wide', textSize)}>{label}</p>
      ) : (
        <span className="sr-only">Loading...</span>
      )}
    </div>
  );

  if (fullScreen) {
    return (
      <div className="min-h-[60vh] w-full flex items-center justify-center p-6">
        {content}
      </div>
    );
  }

  return content;
};

export default Spinner;

