'use client';

import { useRef } from 'react';
import { useScroll } from 'framer-motion';

// ----------------------------------------------------------------------

export function useScrollProgress(target = 'document') {
  const elementRef = useRef(null);

  const options = { container: elementRef };

  const { scrollYProgress, scrollXProgress } = useScroll(
    target === 'container' ? options : undefined
  );

  return [elementRef, scrollXProgress, scrollYProgress];
}
