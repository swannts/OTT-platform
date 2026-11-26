'use client';

import { useLocaleText } from '@/providers';

interface LocalizedTextProps {
  text: string;
}

/** Renders a localized literal from a Server Component without moving its tree client-side. */
export function LocalizedText({ text }: LocalizedTextProps) {
  const tr = useLocaleText();
  return tr(text);
}
