import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "standalone",
  reactStrictMode: false,
  // Раунд 3 (критик 15, P2): базовые security-заголовки API
  async headers() {
    return [
      {
        source: "/api/:path*",
        headers: [{ key: "X-Content-Type-Options", value: "nosniff" }],
      },
    ]
  },
  images: {
    // прод-готовность: кэш оптимизированных изображений и лимит размеров (критик 5)
    minimumCacheTTL: 2678400,
    imageSizes: [64, 96, 128, 256, 384],
    deviceSizes: [420, 640, 828, 1080, 1440, 1920],
  },
};

export default nextConfig;
