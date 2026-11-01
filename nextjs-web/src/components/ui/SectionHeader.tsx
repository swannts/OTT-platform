import React from 'react';
import { cn } from '@/lib/utils';
import Heading from './Heading';
import Text from './Text';

interface SectionHeaderProps {
  badgeText?: string;
  badgeIcon?: React.ReactNode;
  title: string;
  titleIcon?: React.ReactNode;
  description?: string;
  counterText?: string;
  action?: React.ReactNode;
  className?: string;
}

export default function SectionHeader({
  badgeText,
  badgeIcon,
  title,
  titleIcon,
  description,
  counterText,
  action,
  className,
}: SectionHeaderProps) {
  return (
    <div className={cn('mb-8 border-b border-white/10 pb-6', className)}>
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          {badgeText && (
            <div className="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full bg-[#E5B869]/15 border border-[#E5B869]/30 text-[#E5B869] text-xs font-bold uppercase tracking-wider mb-2.5">
              {badgeIcon}
              <span>{badgeText}</span>
            </div>
          )}
          <Heading
            as="h1"
            size="2xl"
            className="flex items-center space-x-3 tracking-tight"
          >
            {titleIcon && <span className="text-[#E5B869] flex-shrink-0">{titleIcon}</span>}
            <span>{title}</span>
          </Heading>
          {description && (
            <Text variant="subtitle" size="sm" className="mt-1.5 max-w-2xl">
              {description}
            </Text>
          )}
        </div>

        <div className="flex items-center space-x-3 self-start sm:self-auto">
          {counterText && (
            <div className="flex items-center space-x-2 text-xs text-gray-400 bg-white/[0.04] border border-white/10 px-3.5 py-1.5 rounded-full">
              <span className="w-2 h-2 rounded-full bg-[#E5B869]" />
              <span className="font-semibold text-gray-200">{counterText}</span>
            </div>
          )}
          {action}
        </div>
      </div>
    </div>
  );
}
