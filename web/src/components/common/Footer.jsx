import { Link } from "react-router-dom";
import {
  FaFacebook,
  FaTwitter,
  FaInstagram,
  FaYoutube,
  FaPinterest,
} from "react-icons/fa";
import footerLinks from "../../data/footerLinks.json";

const Footer = () => {
  const currentYear = new Date().getFullYear();

  const renderLinkSection = (title, links) => (
    <div>
      <h3 className="text-lg font-semibold mb-4">{title}</h3>
      <ul className="space-y-2">
        {links.map((link) => (
          <li key={link.id}>
            <Link
              to={link.url}
              className="hover:text-blue-200 hover:underline transition-all"
            >
              {link.title}
            </Link>
          </li>
        ))}
      </ul>
    </div>
  );

  return (
    <footer className="bg-diuBlue text-white py-12">
      <div className="container mx-auto px-4 md:px-0">
        <div className="flex flex-col md:flex-row md:justify-between gap-8">
          {/* Subscribe Section */}
          <div className="md:col-span-1">
            <h3 className="text-lg font-semibold mb-4">Subscribe Us</h3>
            <div className="flex gap-2">
              <input
                type="email"
                placeholder="Enter Email Address"
                className="w-full px-4 py-2 rounded bg-white text-gray-800"
              />
              <button className="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                Subscribe
              </button>
            </div>
            <div className="mt-6">
              <h4 className="text-lg font-semibold mb-3">Social Links</h4>
              <div className="flex space-x-4">
                <a href="#" className="hover:text-blue-400 transition-colors">
                  <FaFacebook size={24} />
                </a>
                <a href="#" className="hover:text-blue-400 transition-colors">
                  <FaTwitter size={24} />
                </a>
                <a href="#" className="hover:text-blue-400 transition-colors">
                  <FaInstagram size={24} />
                </a>
                <a href="#" className="hover:text-blue-400 transition-colors">
                  <FaYoutube size={24} />
                </a>
                <a href="#" className="hover:text-blue-400 transition-colors">
                  <FaPinterest size={24} />
                </a>
              </div>
            </div>
          </div>

          {/* Links Section */}
          <div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-32 mb-8 justify-end">
              {renderLinkSection("Branding", footerLinks.brandingLinks)}
              {renderLinkSection("Useful Links", footerLinks.usefulLinks)}
              {renderLinkSection("Quick Links", footerLinks.quickLinks)}
            </div>
          </div>
        </div>

        {/* Copyright */}
        <div className="text-center text-sm mt-8 pt-8 border-t border-blue-800 flex flex-col md:flex-row justify-between gap-4">
          <p>Copyright © {currentYear} Daffodil International University.</p>
          <p>All Rights Reserved.</p>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
