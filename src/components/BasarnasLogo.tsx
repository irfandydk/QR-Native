import React from 'react';

interface BasarnasLogoProps {
  className?: string;
  size?: number;
  logoUrl?: string;
}

export const BASARNAS_EMBLEM_SVG_URI = `data:image/svg+xml;utf8,${encodeURIComponent(`
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="48" fill="#F97316" stroke="#C2410C" stroke-width="2" />
  <circle cx="50" cy="50" r="42" fill="#FEF3C7" stroke="#D97706" stroke-width="1.5" />
  <text x="50" y="21" text-anchor="middle" font-size="7.5" font-weight="900" fill="#9A3412" letter-spacing="0.8" font-family="Arial, sans-serif">SAR NASIONAL</text>
  <g transform="translate(50, 50)">
    <path d="M -30,-4 C -22,-14 -8,-8 0,4 C 8,-8 22,-14 30,-4 C 22,2 14,8 0,14 C -14,8 -22,2 -30,-4 Z" fill="#D97706" stroke="#78350F" stroke-width="0.8" />
    <path d="M -24,-2 C -18,-9 -6,-4 0,5 C 6,-4 18,-9 24,-2 C 18,3 10,7 0,10 C -10,7 -18,3 -24,-2 Z" fill="#F59E0B" />
    <circle cx="0" cy="-14" r="4" fill="none" stroke="#1E293B" stroke-width="1.8" />
    <line x1="-8" y1="-8" x2="8" y2="-8" stroke="#1E293B" stroke-width="2.2" stroke-linecap="round" />
    <line x1="0" y1="-14" x2="0" y2="12" stroke="#1E293B" stroke-width="2.5" stroke-linecap="round" />
    <path d="M -13,5 C -9,14 9,14 13,5" fill="none" stroke="#1E293B" stroke-width="2.2" stroke-linecap="round" />
    <polygon points="-13,3 -16,6 -11,7" fill="#1E293B" />
    <polygon points="13,3 16,6 11,7" fill="#1E293B" />
  </g>
  <text x="50" y="86" text-anchor="middle" font-size="5.5" font-weight="800" fill="#B45309" letter-spacing="0.6" font-family="Arial, sans-serif">INDONESIA</text>
</svg>
`)}`;

export const DEFAULT_BASARNAS_LOGO_URL = BASARNAS_EMBLEM_SVG_URI;
export const DEFAULT_BASARNAS_LOGO = 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d4/Logo_Basarnas.svg/300px-Logo_Basarnas.svg.png';

export const BasarnasLogo: React.FC<BasarnasLogoProps> = ({ className = '', size = 56, logoUrl }) => {
  const [hasError, setHasError] = React.useState<boolean>(false);

  // If no explicit logoUrl passed, attempt reading from localStorage or use official default
  const activeLogo =
    logoUrl ||
    (typeof window !== 'undefined' ? localStorage.getItem('sar_custom_logo_url') : null) ||
    DEFAULT_BASARNAS_LOGO_URL;

  React.useEffect(() => {
    setHasError(false);
  }, [logoUrl]);

  if (activeLogo && !hasError && activeLogo !== DEFAULT_BASARNAS_LOGO_URL) {
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
          crossOrigin="anonymous"
          onError={() => {
            setHasError(true);
          }}
        />
      </div>
    );
  }

  return (
    <div
      style={{ width: size, height: size }}
      className={`relative rounded-full flex items-center justify-center shrink-0 ${className}`}
      title="Badan Nasional Pencarian dan Pertolongan (BASARNAS)"
    >
      <svg
        viewBox="0 0 100 100"
        className="w-full h-full select-none"
        xmlns="http://www.w3.org/2000/svg"
      >
        {/* Outer Orange/Gold Ring */}
        <circle cx="50" cy="50" r="48" fill="#F97316" stroke="#C2410C" strokeWidth="2" />
        <circle cx="50" cy="50" r="42" fill="#FEF3C7" stroke="#D97706" strokeWidth="1.5" />

        {/* Top Text: SAR NASIONAL */}
        <text
          x="50"
          y="21"
          textAnchor="middle"
          fontSize="7.5"
          fontWeight="900"
          fill="#9A3412"
          letterSpacing="0.8"
          fontFamily="Arial, Helvetica, sans-serif"
        >
          SAR NASIONAL
        </text>

        {/* Wings & Anchor Symbol */}
        <g transform="translate(50, 50)">
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
          <circle cx="0" cy="-14" r="4" fill="none" stroke="#1E293B" strokeWidth="1.8" />
          <line x1="-8" y1="-8" x2="8" y2="-8" stroke="#1E293B" strokeWidth="2.2" strokeLinecap="round" />
          <line x1="0" y1="-14" x2="0" y2="12" stroke="#1E293B" strokeWidth="2.5" strokeLinecap="round" />
          <path
            d="M -13,5 C -9,14 9,14 13,5"
            fill="none"
            stroke="#1E293B"
            strokeWidth="2.2"
            strokeLinecap="round"
          />
          <polygon points="-13,3 -16,6 -11,7" fill="#1E293B" />
          <polygon points="13,3 16,6 11,7" fill="#1E293B" />
        </g>

        {/* Bottom Text: INDONESIA */}
        <text
          x="50"
          y="86"
          textAnchor="middle"
          fontSize="5.5"
          fontWeight="800"
          fill="#B45309"
          letterSpacing="0.6"
          fontFamily="Arial, Helvetica, sans-serif"
        >
          INDONESIA
        </text>
      </svg>
    </div>
  );
};
