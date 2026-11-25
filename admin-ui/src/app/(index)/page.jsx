import { redirect } from 'next/navigation';

// ----------------------------------------------------------------------

export const metadata = {
  title: 'Noor Islamic Cinema Admin',
  description: 'Administration for the Noor Islamic Cinema OTT platform.',
};

export default function Page() {
  redirect('/login');
}
