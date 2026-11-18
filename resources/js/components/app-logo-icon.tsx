import type { SVGAttributes } from 'react';

/** Vending machine glyph: cabinet, glass window with snacks, coin slot, keypad and tray. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 32 40" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M5 1h22a3 3 0 0 1 3 3v32a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V4a3 3 0 0 1 3-3ZM5.5 4.5v23h15v-23h-15ZM23 5.5v2h4v-2h-4ZM23 10v2h1.6v-2H23Zm2.4 0v2H27v-2h-1.6ZM23 13.5v2h1.6v-2H23Zm2.4 0v2H27v-2h-1.6ZM23 17v2h1.6v-2H23Zm2.4 0v2H27v-2h-1.6ZM6 31v4.5h20V31H6Z"
            />
            <rect x="7" y="6" width="3" height="4.5" rx="0.8" />
            <rect x="11.5" y="6" width="3" height="4.5" rx="0.8" />
            <rect x="16" y="6" width="3" height="4.5" rx="0.8" />
            <rect x="7" y="13" width="3" height="4.5" rx="0.8" />
            <rect x="11.5" y="13" width="3" height="4.5" rx="0.8" />
            <rect x="16" y="13" width="3" height="4.5" rx="0.8" />
            <rect x="7" y="20" width="3" height="4.5" rx="0.8" />
            <rect x="11.5" y="20" width="3" height="4.5" rx="0.8" />
            <rect x="5.5" y="11.2" width="15" height="0.8" />
            <rect x="5.5" y="18.2" width="15" height="0.8" />
            <rect x="5.5" y="25.2" width="15" height="0.8" />
        </svg>
    );
}
