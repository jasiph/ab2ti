<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
        <!-- Green Triangle -->
        <polygon points="100,10 190,180 10,180" fill="currentColor" opacity="0.9"/>
        <polygon points="100,25 178,172 22,172" fill="currentColor"/>

        <!-- Wheat/Grain Icon -->
        <g transform="translate(100,45)" fill="#CA9B1E" stroke="#CA9B1E" stroke-width="0.5">
            <!-- Center stem -->
            <line x1="0" y1="0" x2="0" y2="40" stroke-width="2" />
            <!-- Left leaves -->
            <ellipse cx="-8" cy="8" rx="6" ry="3" transform="rotate(-30, -8, 8)" />
            <ellipse cx="-10" cy="18" rx="6" ry="3" transform="rotate(-25, -10, 18)" />
            <ellipse cx="-8" cy="28" rx="5" ry="2.5" transform="rotate(-20, -8, 28)" />
            <!-- Right leaves -->
            <ellipse cx="8" cy="8" rx="6" ry="3" transform="rotate(30, 8, 8)" />
            <ellipse cx="10" cy="18" rx="6" ry="3" transform="rotate(25, 10, 18)" />
            <ellipse cx="8" cy="28" rx="5" ry="2.5" transform="rotate(20, 8, 28)" />
            <!-- Top grain -->
            <ellipse cx="0" cy="2" rx="3" ry="5" />
            <ellipse cx="-5" cy="5" rx="2.5" ry="4" transform="rotate(-15, -5, 5)" />
            <ellipse cx="5" cy="5" rx="2.5" ry="4" transform="rotate(15, 5, 5)" />
        </g>

        <!-- Human Figure (White) -->
        <g transform="translate(100,105)" fill="white" stroke="none">
            <!-- Head -->
            <circle cx="0" cy="0" r="8" />
            <!-- Body -->
            <line x1="0" y1="8" x2="0" y2="35" stroke="white" stroke-width="4" stroke-linecap="round"/>
            <!-- Arms raised -->
            <line x1="0" y1="15" x2="-20" y2="0" stroke="white" stroke-width="3.5" stroke-linecap="round"/>
            <line x1="0" y1="15" x2="20" y2="0" stroke="white" stroke-width="3.5" stroke-linecap="round"/>
            <!-- Legs -->
            <line x1="0" y1="35" x2="-14" y2="55" stroke="white" stroke-width="3.5" stroke-linecap="round"/>
            <line x1="0" y1="35" x2="14" y2="55" stroke="white" stroke-width="3.5" stroke-linecap="round"/>
        </g>

        <!-- AB2TI Text -->
        <text x="100" y="172" text-anchor="middle" fill="white" font-family="Arial, sans-serif" font-weight="bold" font-size="18" letter-spacing="3">AB2TI</text>
    </svg>
</template>
