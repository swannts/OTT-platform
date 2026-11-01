import type { Metadata } from 'next';
import { Playfair_Display, Inter } from 'next/font/google';
import './globals.css';
import { AuthProvider } from '@/context/AuthContext';
import Navbar from '@/components/layout/Navbar';
import Footer from '@/components/layout/Footer';
import BottomPlayerBar from '@/components/player/BottomPlayerBar';

const playfair = Playfair_Display({
  subsets: ['latin'],
  variable: '--font-serif',
  display: 'swap',
});

const inter = Inter({
  subsets: ['latin'],
  variable: '--font-sans',
  display: 'swap',
});

export const metadata: Metadata = {
  title: 'NOOR - Islamic Video Streaming & Live TV',
  description: 'Stream exclusive Islamic series, documentaries, live sacred broadcasts from Makkah and Madinah, and spiritual lectures.',
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" suppressHydrationWarning className={`dark ${playfair.variable} ${inter.variable}`}>
      <body suppressHydrationWarning className="bg-[#070a0e] text-[#f8fafc] font-sans min-h-screen flex flex-col antialiased selection:bg-[#e5b869]/30 selection:text-[#e5b869]">
        <AuthProvider>
          <Navbar />
          <main className="flex-1 pb-24">
            {children}
          </main>
          <Footer />
          <BottomPlayerBar />
        </AuthProvider>
      </body>
    </html>
  );
}
