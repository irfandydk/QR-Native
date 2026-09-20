import React from 'react';

interface BasarnasLogoProps {
  className?: string;
  size?: number;
  logoUrl?: string;
}

export const BasarnasLogo: React.FC<BasarnasLogoProps> = ({ className = '', size = 56, logoUrl }) => {
  // If no explicit logoUrl passed, attempt reading from localStorage cache
  const activeLogo = logoUrl || (typeof window !== 'undefined' ? localStorage.getItem('sar_custom_logo_url') : null);

  if (activeLogo) {
    return (
      <div
        style={{ width: size, height: size }}
        className={`relative flex items-center justify-center shrink-0 ${className}`}
        title="Badan Nasional Pencarian dan Pertolongan (BASARNAS)"
      >
        <img
          src={activeLogo}
          alt="Logo Resmi Basarnas"
          className="w-full h-full object-contain"
          onError={(e) => {
            // Fallback if custom image fails to load
            (e.target as HTMLElement).style.display = 'none';
          }}
        />
      </div>
    );
  }

  return (
    <div
      style={{ width: size, height: size }}
      className={`relative rounded-full flex items-center justify-center shrink-0 shadow-sm border border-amber-500/30 ${className}`}
      title="Badan Nasional Pencarian dan Pertolongan (BASARNAS)"
    >
      <svg
        viewBox="0 0 100 100"
        className="w-full h-full select-none"
        xmlns="http://www.w3.org/2000/svg"
      >
        {/* Outer Orange/Gold Ring */}
        <circle cx="50" cy="50" r="48" fill="#F97316" stroke="#C2410C" strokeWidth="2" />
        <circle cx="50" cy="50" r="43" fill="#FEF3C7" stroke="#D97706" strokeWidth="1.5" />

        {/* Text Arc or Header Banner */}
        <path
          id="textPathArc"
          d="M 18,50 A 32,32 0 1,1 82,50"
          fill="none"
          stroke="none"
        />
        <text fontSize="7.5" fontWeight="900" fill="#9A3412" letterSpacing="0.8">
          <textPath href="#textPathArc" startOffset="50%" textAnchor="middle">
            SAR NASIONAL
          </textPath>
        </text>

        {/* Wings & Anchor Symbol */}
        <g transform="translate(50, 52)">
          {/* Wings */}
          <path
            d="M -30,-4 C -22,-14 -8,-8 0,4 C 8,-8 22,-14 30,-4 C 22,2 14,8 0,14 C -14,8 -22,2 -30,-4 Z"
            fill="#D97706"
            stroke="#78350F"
            strokeWidth="0.8"
          />
          {/* Inner Wing details */}
          <path
            d="M -24,-2 C -18,-9 -6,-4 0,5 C 6,-4 18,-9 24,-2 C 18,3 10,7 0,10 C -10,7 -18,3 -24,-2 Z"
            fill="#F59E0B"
          />

          {/* Anchor Center */}
          {/* Ring */}
          <circle cx="0" cy="-14" r="4" fill="none" stroke="#1E293B" strokeWidth="1.8" />
          {/* Crossbar */}
          <line x1="-8" y1="-8" x2="8" y2="-8" stroke="#1E293B" strokeWidth="2.2" strokeLinecap="round" />
          {/* Vertical shank */}
          <line x1="0" y1="-14" x2="0" y2="12" stroke="#1E293B" strokeWidth="2.5" strokeLinecap="round" />
          {/* Flukes / Curved Arms */}
          <path
            d="M -13,5 C -9,14 9,14 13,5"
            fill="none"
            stroke="#1E293B"
            strokeWidth="2.2"
            strokeLinecap="round"
          />
          {/* Arrow tips */}
          <polygon points="-13,3 -16,6 -11,7" fill="#1E293B" />
          <polygon points="13,3 16,6 11,7" fill="#1E293B" />
        </g>

        {/* Bottom Arc Banner */}
        <path
          id="textPathBottom"
          d="M 80,56 A 34,34 0 0,1 20,56"
          fill="none"
          stroke="none"
        />
        <text fontSize="5.5" fontWeight="800" fill="#B45309" letterSpacing="0.4">
          <textPath href="#textPathBottom" startOffset="50%" textAnchor="middle">
            INDONESIA
          </textPath>
        </text>
      </svg>
    </div>
  );
};
