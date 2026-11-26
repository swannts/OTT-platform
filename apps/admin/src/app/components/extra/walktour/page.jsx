import { CONFIG } from 'src/config-global';

import { redirect } from 'next/navigation';

// ----------------------------------------------------------------------

export const metadata = { title: `Walktour | Components - ${CONFIG.site.name}` };

export default function Page() {
  redirect('/dashboard/');
}
