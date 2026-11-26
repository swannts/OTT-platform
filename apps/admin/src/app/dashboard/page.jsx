import { CONFIG } from 'src/config-global';

import { OttDashboardView } from 'src/sections/ott/dashboard-view';

// ----------------------------------------------------------------------

export const metadata = { title: `Dashboard - ${CONFIG.site.name}` };

export default function Page() {
  return <OttDashboardView />;
}
