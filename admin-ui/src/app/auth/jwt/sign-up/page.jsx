import { CONFIG } from 'src/config-global';

import { redirect } from 'next/navigation';

// ----------------------------------------------------------------------

export const metadata = { title: `Sign up | Jwt - ${CONFIG.site.name}` };

export default function Page() {
  redirect('/login');
}
