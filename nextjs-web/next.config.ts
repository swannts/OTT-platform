import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  transpilePackages: ['@videojs/react', '@videojs/hlsjs-video', 'video.js'],
  images: {
    remotePatterns: [
      { protocol: "http", hostname: "**" },
      { protocol: "https", hostname: "**" },
    ],
  },
};

export default nextConfig;
