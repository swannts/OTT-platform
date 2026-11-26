import { GuestGuard } from 'src/auth/guard';
import { AuthSplitLayout } from 'src/layouts/auth-split';

export default function Layout({ children }) {
  return (
    <GuestGuard>
      <AuthSplitLayout section={{ title: 'Hi, Welcome back' }}>{children}</AuthSplitLayout>
    </GuestGuard>
  );
}
