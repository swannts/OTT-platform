'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Spinner } from '@/components/ui';

export default function SecurityRedirectPage() {
  const router = useRouter();

  useEffect(() => {
    router.replace('/account?tab=security');
  }, [router]);

  return (
    <div className="min-h-screen pt-32 text-center text-white space-y-4">
      <Spinner size="lg" label="Loading security & PIN..." />
    </div>
  );
}
