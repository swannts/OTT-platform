import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  transpilePackages: ['@videojs/react', '@videojs/hlsjs-video', 'video.js'],
  images: {
    remotePatterns: [
      { protocol: "http", hostname: "127.0.0.1", port: "8000" },
      { protocol: "http", hostname: "localhost", port: "8000" },
      { protocol: "http", hostname: "127.0.0.1" },
      { protocol: "http", hostname: "localhost" },
      { protocol: "http", hostname: "**" },
      { protocol: "https", hostname: "**" },
    ],
  },
};

export default nextConfig;
