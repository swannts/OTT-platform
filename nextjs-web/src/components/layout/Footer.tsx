import React from 'react';
import Link from 'next/link';

export default function Footer() {
  return (
    <footer className="border-t border-white/5 bg-[#0a0b0e] py-12 text-gray-400 text-xs mt-20">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-8 mb-8">
          <div>
            <h4 className="text-white font-semibold mb-3 text-sm">Navigation</h4>
            <ul className="space-y-2">
              <li><Link href="/" className="hover:text-white transition-colors">Home</Link></li>
              <li><Link href="/movies" className="hover:text-white transition-colors">Movies</Link></li>
              <li><Link href="/tv-shows" className="hover:text-white transition-colors">TV Shows</Link></li>
              <li><Link href="/live-tv" className="hover:text-white transition-colors">Live TV Channels</Link></li>
            </ul>
          </div>
          <div>
            <h4 className="text-white font-semibold mb-3 text-sm">Subscriptions</h4>
            <ul className="space-y-2">
              <li><Link href="/pricing" className="hover:text-white transition-colors">Subscription Plans</Link></li>
              <li><Link href="/pricing" className="hover:text-white transition-colors">VIP Benefits</Link></li>
              <li><Link href="/movies" className="hover:text-white transition-colors">Pay-Per-View Rentals</Link></li>
            </ul>
          </div>
          <div>
            <h4 className="text-white font-semibold mb-3 text-sm">Legal & Policies</h4>
            <ul className="space-y-2">
              <li><Link href="/pages/privacy-policy" className="hover:text-white transition-colors">Privacy Policy</Link></li>
              <li><Link href="/pages/terms-and-conditions" className="hover:text-white transition-colors">Terms of Service</Link></li>
              <li><Link href="/pages/refund-policy" className="hover:text-white transition-colors">Refund Policy</Link></li>
            </ul>
          </div>
          <div>
            <h4 className="text-white font-semibold mb-3 text-sm">About Swann OTT</h4>
            <p className="text-gray-500 leading-relaxed mb-3">
              Premium multi-platform media streaming delivering movies, live broadcasting, and original shows in 4K HDR.
            </p>
            <p className="text-gray-600">
              © {new Date().getFullYear()} Swann OTT Platform. All rights reserved.
            </p>
          </div>
        </div>
      </div>
    </footer>
  );
}

