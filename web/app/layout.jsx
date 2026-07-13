import "../src/index.css";
import Header from "../src/components/common/Header";
import Footer from "../src/components/common/Footer";

export const metadata = {
  title: "Faculty Directory - DIU",
  description: "Daffodil International University Faculty Directory",
};

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
      </head>
      <body class="bg-slate-50 antialiased">
        <div className="flex flex-col min-h-screen">
          <Header />
          <main className="flex-grow pt-16">
            {children}
          </main>
          <Footer />
        </div>
      </body>
    </html>
  );
}
