import { InvitationAcceptancePage } from 'src/features/rbac/components/InvitationAcceptancePage';

export const metadata = { title: 'Accept staff invitation - Noor Admin' };

export default async function Page({ searchParams }) {
  const params = await searchParams;
  return <InvitationAcceptancePage token={typeof params?.token === 'string' ? params.token : ''} />;
}
