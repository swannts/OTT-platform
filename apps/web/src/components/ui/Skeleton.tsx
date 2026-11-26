import React from 'react';
import { cn } from '@/lib/utils';

export interface SkeletonProps extends React.HTMLAttributes<HTMLDivElement> {
  shimmer?: boolean;
}

export const Skeleton: React.FC<SkeletonProps> = ({
  className,
  shimmer = true,
  ...props
}) => {
  return (
    <div
      aria-hidden="true"
      className={cn(
        'bg-white/[0.06] rounded-xl',
        shimmer && 'animate-shimmer',
        className
      )}
      {...props}
    />
  );
};

export default Skeleton;

