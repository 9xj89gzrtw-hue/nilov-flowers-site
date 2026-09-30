import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "standalone",
  reactStrictMode: false,
  images: {
    // прод-готовность: кэш оптимизированных изображений и лимит размеров (критик 5)
    minimumCacheTTL: 2678400,
    imageSizes: [64, 96, 128, 256, 384],
    deviceSizes: [420, 640, 828, 1080, 1440, 1920],
  },
};

export default nextConfig;
