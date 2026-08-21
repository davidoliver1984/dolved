import type { Metadata } from "next";
import { Sora, Source_Sans_3 } from "next/font/google";
import { ThemeProvider } from "@/components/ThemeProvider";
import "./globals.css";

const sourceSans = Source_Sans_3({
  variable: "--font-source-sans-3",
  subsets: ["latin"],
});

const sora = Sora({
  variable: "--font-wordmark",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: {
    default: "Dolved",
    template: "%s · Dolved",
  },
  description: "Grounded answers from the knowledge your team already owns.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="en"
      className={`${sourceSans.variable} ${sora.variable}`}
      suppressHydrationWarning
    >
      <body>
        <ThemeProvider>{children}</ThemeProvider>
      </body>
    </html>
  );
}
