import type { Metadata } from "next";
import localFont from "next/font/local";
import "./globals.css";
import { Toaster } from "@/components/ui/sonner";

const golos = localFont({
  src: [
    { path: "../fonts/GolosText-cyrillic.woff2" },
    { path: "../fonts/GolosText-latin.woff2" },
  ],
  variable: "--font-golos",
  display: "swap",
  weight: "400",
});

const montserrat = localFont({
  src: [
    { path: "../fonts/MontserratVariable-cyrillic.woff2" },
    { path: "../fonts/MontserratVariable-latin.woff2" },
  ],
  variable: "--font-montserrat",
  display: "swap",
  weight: "100 900",
});

const playfair = localFont({
  src: [
    { path: "../fonts/PlayfairDisplay-cyrillic.woff2" },
    { path: "../fonts/PlayfairDisplay-latin.woff2" },
  ],
  variable: "--font-playfair",
  display: "swap",
  weight: "400",
});

const playfairItalic = localFont({
  src: [
    { path: "../fonts/PlayfairDisplay-Italic-cyrillic.woff2" },
    { path: "../fonts/PlayfairDisplay-Italic-latin.woff2" },
  ],
  variable: "--font-playfair-italic",
  display: "swap",
  weight: "400",
  style: "italic",
});

export const metadata: Metadata = {
  title: "Nilov Flowers — доставка цветов по Санкт-Петербургу от 60 минут",
  description:
    "Букеты из свежего среза с доставкой по СПб от 60 минут. Фото букета перед отправкой в WhatsApp, бесплатная открытка с вашим текстом, оплата частями Яндекс Сплит / Долями.",
  keywords: [
    "доставка цветов спб",
    "цветы санкт-петербург",
    "букеты спб",
    "nilov flowers",
    "цветы с доставкой",
    "шляпные коробки",
    "пионы спб",
  ],
  openGraph: {
    title: "Nilov Flowers — цветы по Петербургу от 60 минут",
    description:
      "Фото букета до отправки · бесплатная открытка · оплата частями. Свежий срез каждое утро.",
    type: "website",
    siteName: "Nilov Flowers",
    images: ["/img/editorial/petals-hero-4x3.webp"],
  },
  icons: { icon: "/favicon.svg" },
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="ru" suppressHydrationWarning>
      <body
        className={`${golos.variable} ${montserrat.variable} ${playfair.variable} ${playfairItalic.variable} font-sans antialiased`}
      >
        {children}
        <Toaster position="bottom-center" richColors />
      </body>
    </html>
  );
}
