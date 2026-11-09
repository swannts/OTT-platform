import type { Metadata } from 'next';
import { Playfair_Display, Inter } from 'next/font/google';
import './globals.css';
import { AuthProvider, StoreProvider, LanguageProvider, RuntimeErrorGuard } from '@/providers';
import Navbar from '@/components/layout/Navbar';
import Footer from '@/components/layout/Footer';
import NavigationProgress from '@/components/layout/NavigationProgress';
import { api } from '@/lib/api';

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

export async function generateMetadata(): Promise<Metadata> {
  let title = 'NOOR - Islamic Video Streaming & Live TV';
  let description =
    'Stream exclusive Islamic series, documentaries, live sacred broadcasts from Makkah and Madinah, and spiritual lectures.';
  let keywords: string[] = [
    'Islamic movie',
    'Halal cinema',
    'Makkah live TV',
    'Madinah live TV',
    'Islamic streaming',
    'NOOR Cinema',
  ];
  let imageUrl: string | undefined;
  let canonicalUrl: string | undefined;
  let googleVerification: string | undefined;

  try {
    const seo = await api.getSeoSettings();
    if (seo) {
      if (seo.meta_title) title = seo.meta_title;
      if (seo.meta_description) description = seo.meta_description;
      if (Array.isArray(seo.meta_keywords)) {
        keywords = seo.meta_keywords;
      } else if (typeof seo.meta_keywords === 'string' && seo.meta_keywords.trim()) {
        keywords = seo.meta_keywords
          .split(',')
          .map((k) => k.trim())
          .filter(Boolean);
      }
      if (seo.seo_image) imageUrl = seo.seo_image;
      if (seo.canonical_url) canonicalUrl = seo.canonical_url;
      if (seo.google_site_verification) googleVerification = seo.google_site_verification;
    }
  } catch {
    // Graceful fallback to default brand tokens if API is unreachable
  }

  return {
    title,
    description,
    keywords,
    openGraph: {
      title,
      description,
      ...(imageUrl ? { images: [imageUrl] } : {}),
      ...(canonicalUrl ? { url: canonicalUrl } : {}),
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description,
      ...(imageUrl ? { images: [imageUrl] } : {}),
    },
    alternates: {
      ...(canonicalUrl ? { canonical: canonicalUrl } : {}),
    },
    verification: {
      ...(googleVerification ? { google: googleVerification } : {}),
    },
  };
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="bn" suppressHydrationWarning className={`dark ${playfair.variable} ${inter.variable}`}>
      <head>
        <script
          dangerouslySetInnerHTML={{
            __html: `
              (function() {
                if (typeof window === 'undefined') return;
                function isHarmless(r) {
                  if (!r) return false;
                  var m = typeof r === 'string' ? r : (r && r.message) || String(r);
                  var l = m.toLowerCase();
                  return l.indexOf('provider destroyed') !== -1 ||
                         l.indexOf('interrupted by a call to pause') !== -1 ||
                         l.indexOf('interrupted by a new load request') !== -1 ||
                         l.indexOf('the play() request was interrupted') !== -1 ||
                         l.indexOf('the play() request was canceled') !== -1 ||
                         l.indexOf('the fetching process for the media resource was aborted') !== -1 ||
                         l.indexOf('aborterror') !== -1;
                }
                var origAdd = window.addEventListener;
                window.addEventListener = function(t, l, o) {
                  if (t === 'unhandledrejection' && typeof l === 'function') {
                    var wrapped = function(e) {
                      if (isHarmless(e && e.reason)) {
                        if (e.preventDefault) e.preventDefault();
                        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                        return;
                      }
                      return l.apply(this, arguments);
                    };
                    return origAdd.call(this, t, wrapped, o);
                  }
                  if (t === 'error' && typeof l === 'function') {
                    var wrappedErr = function(e) {
                      if (isHarmless(e && (e.error || e.message))) {
                        if (e.preventDefault) e.preventDefault();
                        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                        return;
                      }
                      return l.apply(this, arguments);
                    };
                    return origAdd.call(this, t, wrappedErr, o);
                  }
                  return origAdd.apply(this, arguments);
                };
                origAdd.call(window, 'unhandledrejection', function(e) {
                  if (isHarmless(e && e.reason)) {
                    if (e.preventDefault) e.preventDefault();
                    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                  }
                }, true);
              })();
            `,
          }}
        />
      </head>
      <body suppressHydrationWarning className="bg-[#070a0e] text-[#f8fafc] font-sans min-h-screen flex flex-col antialiased selection:bg-[#e5b869]/30 selection:text-[#e5b869]">
        <RuntimeErrorGuard>
          <StoreProvider>
            <AuthProvider>
              <LanguageProvider>
                <NavigationProgress />
                <Navbar />
                <main className="flex-1 pb-12">
                  {children}
                </main>
                <Footer />
              </LanguageProvider>
            </AuthProvider>
          </StoreProvider>
        </RuntimeErrorGuard>
      </body>
    </html>
  );
}
