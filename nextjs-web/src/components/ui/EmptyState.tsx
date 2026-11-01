import React from 'react';
import { cn } from '@/lib/utils';
import Heading from './Heading';
import Text from './Text';

interface EmptyStateProps {
  icon?: React.ReactNode;
  title: string;
  description?: string;
  action?: React.ReactNode;
  className?: string;
}

export default function EmptyState({
  icon,
  title,
  description,
  action,
  className,
}: EmptyStateProps) {
  return (
    <div
      className={cn(
        'text-center py-20 bg-white/[0.02] border border-white/5 rounded-3xl p-8 space-y-3 max-w-xl mx-auto select-none',
        className
      )}
    >
      {icon && <div className="flex justify-center text-gray-500 mb-2">{icon}</div>}
      <Heading as="h3" size="md" className="font-bold text-white">
        {title}
      </Heading>
      {description && (
        <Text variant="caption" size="sm" className="text-gray-400 max-w-sm mx-auto">
          {description}
        </Text>
      )}
      {action && <div className="pt-2">{action}</div>}
    </div>
  );
}
