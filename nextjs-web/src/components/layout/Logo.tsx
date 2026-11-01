import React from 'react';

export default function Logo({ className = 'h-8' }: { className?: string }) {
  return (
    <div className={`flex items-center space-x-2.5 ${className}`}>
      {/* Golden Arch / Mihrab SVG Icon */}
      <svg
        className="w-8 h-8 text-[#E5B869] flex-shrink-0"
        viewBox="0 0 32 32"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        {/* Outer Dome Arch */}
        <path
          d="M16 2C9.5 7.5 4 14 4 21V30H28V21C28 14 22.5 7.5 16 2Z"
          stroke="currentColor"
          strokeWidth="1.8"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
        {/* Middle Arch */}
        <path
          d="M16 7C11.5 11.5 8 16.5 8 22V30H24V22C24 16.5 20.5 11.5 16 7Z"
          stroke="currentColor"
          strokeWidth="1.6"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
        {/* Inner Arch */}
        <path
          d="M16 13C13.5 16 12 19.5 12 24V30H20V24C20 19.5 18.5 16 16 13Z"
          stroke="currentColor"
          strokeWidth="1.4"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </svg>

      {/* Brand Text: NOOR */}
      <span className="font-serif-title text-2xl font-black tracking-[0.2em] text-[#E5B869] select-none">
        NOOR
      </span>
    </div>
  );
}

