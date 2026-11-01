import React from 'react';

export default function Logo({ className = 'h-8' }: { className?: string }) {
  return (
    <div className={`flex items-center space-x-2.5 select-none group cursor-pointer ${className}`}>
      {/* Golden Arch / Mihrab SVG Icon with Metallic Gradient */}
      <div className="relative w-8 h-8 flex-shrink-0 flex items-center justify-center">
        <svg
          className="w-full h-full transform transition-transform duration-300 group-hover:scale-105"
          viewBox="0 0 32 32"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <defs>
            <linearGradient id="noorGold" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stopColor="#ffe4a0" />
              <stop offset="50%" stopColor="#e5b869" />
              <stop offset="100%" stopColor="#b3822a" />
            </linearGradient>
            <linearGradient id="noorGlow" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stopColor="#e5b869" stopOpacity="0.25" />
              <stop offset="100%" stopColor="#e5b869" stopOpacity="0" />
            </linearGradient>
          </defs>

          {/* Soft inner arch fill */}
          <path
            d="M16 2C9.5 7.5 4 14 4 21V30H28V21C28 14 22.5 7.5 16 2Z"
            fill="url(#noorGlow)"
          />

          {/* Outer Dome Arch */}
          <path
            d="M16 2C9.5 7.5 4 14 4 21V30H28V21C28 14 22.5 7.5 16 2Z"
            stroke="url(#noorGold)"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
          {/* Middle Arch */}
          <path
            d="M16 7C11.5 11.5 8 16.5 8 22V30H24V22C24 16.5 20.5 11.5 16 7Z"
            stroke="url(#noorGold)"
            strokeWidth="1.6"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
          {/* Inner Arch */}
          <path
            d="M16 13C13.5 16 12 19.5 12 24V30H20V24C20 19.5 18.5 16 16 13Z"
            stroke="url(#noorGold)"
            strokeWidth="1.4"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
      </div>

      {/* Brand Text: NOOR with Gold Gradient */}
      <div className="flex flex-col">
        <span className="font-serif-title text-2xl font-black tracking-[0.25em] bg-gradient-to-r from-[#fff0cc] via-[#e5b869] to-[#c99540] bg-clip-text text-transparent drop-shadow-[0_2px_12px_rgba(229,184,105,0.35)] leading-none">
          NOOR
        </span>
        <span className="text-[7.5px] font-sans font-semibold tracking-[0.35em] text-[#e5b869]/70 uppercase mt-0.5 leading-none">
          Islamic Cinema
        </span>
      </div>
    </div>
  );
}

